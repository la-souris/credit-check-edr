<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\CreditCheck;

use DateTimeImmutable;
use Exception;
use LaSouris\CreditCheck\Edr\Enum\Gender;
use LaSouris\CreditCheck\Edr\Enum\OccupantCount;
use LaSouris\CreditCheck\Edr\Enum\PersonType;
use LaSouris\CreditCheck\Edr\Enum\SalesChannel;
use LaSouris\CreditCheck\Edr\Enum\TrafficLight;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateMetaDataLeaseModel;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderLeaseModel;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderPartnerModel;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderPersonAddressModel;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderPersonModel;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Address;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Person;
use LaSouris\CreditCheck\Sdk\Request\CreateCreditCheckRequest;
use LaSouris\CreditCheck\Sdk\Response\ApplicantResult;
use LaSouris\CreditCheck\Sdk\Response\CheckStatus;
use LaSouris\CreditCheck\Sdk\Response\Decision;
use LaSouris\CreditCheck\Sdk\Response\GetCreditCheckResponse;
use LaSouris\CreditCheck\Sdk\Response\RejectionReason;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * Translates the SDK's provider-agnostic domain into the EDR wire models, and the EDR order
 * status back into a normalised {@see GetCreditCheckResponse}.
 *
 * This is the only place that knows both vocabularies. Everything the SDK deliberately does
 * not model — income and affordability figures, marital status, divorce/alimony, AOW status,
 * the yes/no household flags, identification — is simply omitted from the request body. To
 * send any of it, build the wire models yourself and pass them to EdrClient.
 *
 * Every field the SDK *does* model beyond `surname`/`initials`/`firstName` is itself optional
 * on `Person` and `Address` — a caller may not have a date of birth, contact details, gender or
 * even an address on hand yet. Each is carried through as-is: present data is mapped, absent
 * data is left out of the body, never sent as a fabricated default.
 */
final class EdrPayloadMapper
{
    private readonly DecimalMoneyFormatter $formatter;
    private readonly DecimalMoneyParser $parser;
    private readonly PhoneNumberUtil $phoneNumbers;

    public function __construct()
    {
        $currencies = new ISOCurrencies();
        $this->formatter = new DecimalMoneyFormatter($currencies);
        $this->parser = new DecimalMoneyParser($currencies);
        $this->phoneNumbers = PhoneNumberUtil::getInstance();
    }

    public function createOrder(CreateCreditCheckRequest $request): CreateOrderLeaseModel
    {
        return new CreateOrderLeaseModel(
            $request->reference,
            array_map($this->person(...), $request->applicants),
            $this->metaData($request),
        );
    }

    private function metaData(CreateCreditCheckRequest $request): CreateMetaDataLeaseModel
    {
        $subject = $request->subject;

        return new CreateMetaDataLeaseModel(
            carBrand: $subject->label,
            startDate: $subject->startDate->format('Y-m-d\TH:i:s'),
            endDate: $subject->endDate->format('Y-m-d\TH:i:s'),
            leaseAmount: $this->toFloat($subject->amount),
            leasePeriodInMonths: $subject->termInMonths,
            carType: $subject->variant,
            orderSalesChannel: SalesChannel::fromSdk($request->salesChannel),
        );
    }

    /**
     * An SDK applicant is a person plus the partner sharing their household; EDR nests the
     * partner inside the person, so both halves are mapped here.
     */
    private function person(Applicant $applicant): CreateOrderPersonModel
    {
        $person = $applicant->person;

        return new CreateOrderPersonModel(
            surname: $person->surname,
            initials: $person->initials,
            mobilenumber: $this->phoneNumber($person->contactInformation?->mobileNumber),
            email: $person->contactInformation?->email,
            dateofbirth: $person->dateOfBirth?->format('Y-m-d'),
            gender: Gender::fromSdk($person->gender),
            address: $person->address !== null ? $this->address($person->address) : null,
            firstname: $person->firstName,
            partner: $applicant->partner !== null ? $this->partner($applicant->partner) : null,
        );
    }

    /**
     * EDR's partner fragment carries no address of its own — the household is the person's
     * address — so the partner's SDK address is deliberately not sent.
     */
    private function partner(Person $partner): CreateOrderPartnerModel
    {
        return new CreateOrderPartnerModel(
            surname: $partner->surname,
            initials: $partner->initials,
            mobilenumber: $this->phoneNumber($partner->contactInformation?->mobileNumber),
            email: $partner->contactInformation?->email,
            dateofbirth: $partner->dateOfBirth?->format('Y-m-d'),
            gender: Gender::fromSdk($partner->gender),
            firstname: $partner->firstName,
        );
    }

    /**
     * EDR takes phone numbers as plain strings; E.164 is the unambiguous rendering. Null in,
     * null out — no contact information means nothing to format.
     */
    private function phoneNumber(?PhoneNumber $number): ?string
    {
        return $number !== null ? $this->phoneNumbers->format($number, PhoneNumberFormat::E164) : null;
    }

    private function address(Address $address): CreateOrderPersonAddressModel
    {
        [$number, $addOn] = $this->splitHouseNumber($address->houseNumber);

        return new CreateOrderPersonAddressModel(
            houseNumber: $number,
            street: $address->street,
            houseNumberAddOn: $addOn,
            postalCode: $address->postalCode,
            city: $address->city,
            country: $address->country,
            amountOccupants: OccupantCount::fromCount($address->occupants),
        );
    }

    /**
     * EDR splits a house number into a numeric part and a separate suffix, where the SDK keeps
     * it as one string: "12A" becomes 12 + "A", "3-bis" becomes 3 + "-bis".
     *
     * @return array{int, ?string}
     */
    private function splitHouseNumber(string $houseNumber): array
    {
        if (preg_match('/^\s*(\d+)\s*(.*)$/', $houseNumber, $matches) !== 1) {
            return [0, trim($houseNumber) !== '' ? trim($houseNumber) : null];
        }

        $addOn = trim($matches[2]);

        return [(int) $matches[1], $addOn !== '' ? $addOn : null];
    }

    /**
     * Map the decoded OrderStatusLeaseModel into a normalised result.
     *
     * The provider name is passed in rather than held on the mapper: it lives on the
     * checker's #[Provider] attribute, and copying it into a constructor here would give it
     * a second home that can drift.
     *
     * @param array<string, mixed> $order
     */
    public function result(array $order, string $provider): GetCreditCheckResponse
    {
        $conclusion = $this->arr($order['conclusion'] ?? null);
        $validation = $this->arr($order['validationDetails'] ?? null);
        $ilt = $this->arr($validation['ilt'] ?? null);

        $completedAt = $this->date($order['completedDate'] ?? null);
        $completed = $completedAt !== null;
        $trafficLight = TrafficLight::fromWire($conclusion['trafficLight'] ?? null)?->toSdk();

        return new GetCreditCheckResponse(
            provider: $provider,
            reference: (string) ($order['orderId'] ?? ''),
            decision: Decision::fromTrafficLight($trafficLight, $completed),
            status: $completed ? CheckStatus::Completed : CheckStatus::InProgress,
            trafficLight: $trafficLight,
            expendableIncome: $this->money($ilt['monthlyCapacity'] ?? null),
            rejectionReasons: $this->rejectionReasons($conclusion),
            applicantResults: $this->applicantResults($order, $ilt),
            startedAt: $this->date($order['startDate'] ?? null),
            completedAt: $completedAt,
            raw: $order,
        );
    }

    /**
     * @param array<string, mixed> $conclusion
     *
     * @return list<RejectionReason>
     */
    private function rejectionReasons(array $conclusion): array
    {
        $reasons = [];

        foreach ($this->arr($conclusion['rejectionReasons'] ?? null) as $reason) {
            if (is_string($reason) && $reason !== '') {
                $reasons[] = new RejectionReason($reason);
            }
        }

        $feedback = $this->arr($conclusion['feedBack'] ?? null);
        $feedbackReason = $this->str($feedback['reason'] ?? null);
        if ($feedbackReason !== null && $feedbackReason !== '' && $feedbackReason !== 'None') {
            $reasons[] = new RejectionReason($feedbackReason);
        }

        return $reasons;
    }

    /**
     * Per-person results, in the order EDR lists them (which mirrors the submitted applicants).
     *
     * The affordability figures live in a separate "ilt" node keyed by person type, so they are
     * looked up by type and attached to the matching person.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $ilt
     *
     * @return list<ApplicantResult>
     */
    private function applicantResults(array $order, array $ilt): array
    {
        $capacityByType = [];
        foreach ($this->arr($ilt['persons'] ?? null) as $iltPerson) {
            $iltPerson = $this->arr($iltPerson);
            $type = PersonType::tryFrom((string) $this->str($iltPerson['type'] ?? null));
            if ($type !== null) {
                $capacityByType[$type->value] = $this->money($iltPerson['monthlyCapacity'] ?? null);
            }
        }

        $results = [];
        foreach ($this->arr($order['persons'] ?? null) as $person) {
            $person = $this->arr($person);
            $type = PersonType::tryFrom((string) $this->str($person['type'] ?? null));

            $results[] = new ApplicantResult(
                trafficLight: $this->screeningVerdict($this->arr($person['validationDetails'] ?? null))?->toSdk(),
                expendableIncome: $type !== null ? ($capacityByType[$type->value] ?? null) : null,
            );
        }

        return $results;
    }

    /**
     * Folds EDR's individual screening steps — sanctions list, politically-exposed-person and
     * credit-registry ("CRS") — into the single worst light among them.
     *
     * @param array<string, mixed> $details
     */
    private function screeningVerdict(array $details): ?TrafficLight
    {
        $worst = null;

        foreach (['sanctie', 'pep', 'crs'] as $key) {
            $light = TrafficLight::fromWire($this->arr($details[$key] ?? null)['trafficLight'] ?? null);

            if ($light !== null && ($worst === null || $light->severity() > $worst->severity())) {
                $worst = $light;
            }
        }

        return $worst;
    }

    private function money(mixed $value): ?Money
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        return $this->parser->parse(number_format((float) $value, 2, '.', ''), new Currency('EUR'));
    }

    private function toFloat(Money $money): float
    {
        return (float) $this->formatter->format($money);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
