<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Functional;

use LaSouris\CreditCheck\Edr\CreditCheck\EdrPayloadMapper;
use LaSouris\CreditCheck\Edr\Tests\Fake\SampleRequest;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Address;
use PHPUnit\Framework\TestCase;

final class EdrPayloadMapperTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(string $houseNumber = '12'): array
    {
        return (new EdrPayloadMapper())->createOrder(SampleRequest::build($houseNumber))->jsonSerialize();
    }

    public function testCreateOrderProducesEdrWireShape(): void
    {
        $payload = $this->payload();

        self::assertSame('ORDER-123', $payload['reference']);

        $meta = $payload['metaData'];
        self::assertSame('Tesla', $meta['carBrand']);
        self::assertSame('Model 3', $meta['carType']);
        self::assertSame(45000.0, $meta['leaseAmount']);
        self::assertSame(60, $meta['leasePeriodInMonths']);
        self::assertSame('Internet', $meta['orderSalesChannel']);
        self::assertSame('2026-08-01T00:00:00', $meta['startDate']);
        self::assertSame('2031-08-01T00:00:00', $meta['endDate']);
    }

    public function testPersonCarriesIdentityAndContact(): void
    {
        $person = $this->payload()['persons'][0];

        self::assertSame('de Vries', $person['surname']);
        self::assertSame('Jan', $person['firstname']);
        self::assertSame('J.', $person['initials']);
        self::assertSame('Male', $person['gender']);
        self::assertSame('1990-05-01', $person['dateofbirth']);
        self::assertSame('+31612345678', $person['mobilenumber']);
        self::assertSame('jan@example.com', $person['email']);
    }

    public function testAddressIsMapped(): void
    {
        $address = $this->payload()['persons'][0]['address'];

        self::assertSame('Kerkstraat', $address['street']);
        self::assertSame(12, $address['houseNumber']);
        self::assertSame('1011AA', $address['postalCode']);
        self::assertSame('Amsterdam', $address['city']);
        self::assertSame('NL', $address['country']);
        self::assertSame('Two', $address['amountOccupants']);
        self::assertArrayNotHasKey('houseNumberAddOn', $address);
    }

    public function testHouseNumberSuffixIsSplitOut(): void
    {
        $address = $this->payload('12A')['persons'][0]['address'];

        self::assertSame(12, $address['houseNumber']);
        self::assertSame('A', $address['houseNumberAddOn']);

        $hyphenated = $this->payload('3 - bis')['persons'][0]['address'];

        self::assertSame(3, $hyphenated['houseNumber']);
        self::assertSame('- bis', $hyphenated['houseNumberAddOn']);
    }

    /**
     * The SDK models identity, contact, address and a partner only. Income, affordability
     * figures, the household flags and identification must be absent from the body rather
     * than sent as null.
     */
    public function testFieldsTheSdkDoesNotModelAreOmitted(): void
    {
        $payload = $this->payload();
        $person = $payload['persons'][0];

        self::assertArrayNotHasKey('tenantProductId', $payload);
        self::assertArrayNotHasKey('statusChangeCallback', $payload['metaData']);
        self::assertArrayNotHasKey('applicantReturnUrl', $payload['metaData']);
        self::assertArrayNotHasKey('options', $payload['metaData']);

        foreach ([
            'sourceofincome', 'livingArrangement', 'profession',
            'totalNetMonthlyIncome', 'totalGrossYearIncome', 'totalAssetIncome', 'totalDebt',
            'maritalStatus', 'divorce', 'familyComposition', 'aowStatus',
            'fromEERCountry', 'incomeSavingAccount', 'incomeEffectenDepot',
            'kids', 'hasCurrentLease', 'hasDuoLoan',
            'nickname', 'prefix', 'birthplace',
            'bankAccountNumber', 'bankAccountName',
            'identificationDocumentType', 'identificationDocumentNumber',
        ] as $key) {
            self::assertArrayNotHasKey($key, $person, $key);
        }

        foreach (['mortgagDebt', 'houseSold', 'transferdate', 'livingOnAddressSince'] as $key) {
            self::assertArrayNotHasKey($key, $person['address'], $key);
        }
    }

    public function testPartnerIsNestedInsideThePerson(): void
    {
        $partner = $this->payload()['persons'][0]['partner'];

        self::assertSame('de Vries', $partner['surname']);
        self::assertSame('Marie', $partner['firstname']);
        self::assertSame('M.', $partner['initials']);
        self::assertSame('Female', $partner['gender']);
        self::assertSame('1991-09-17', $partner['dateofbirth']);
        self::assertSame('+31687654321', $partner['mobilenumber']);
        self::assertSame('marie@example.com', $partner['email']);

        // EDR's partner fragment has no address of its own; the household is the person's.
        self::assertArrayNotHasKey('address', $partner);
    }

    public function testPayloadEncodesToJsonWithoutNulls(): void
    {
        $json = json_encode($this->payload(), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('null', $json);
    }

    /**
     * A Person only guarantees initials/firstName/surname; gender, date of birth, contact
     * information and address are all independently optional. None of that should crash the
     * mapper, and none of it should be sent as a fabricated default.
     */
    public function testOptionalPersonFieldsAreOmittedRatherThanFabricated(): void
    {
        $payload = (new EdrPayloadMapper())
            ->createOrder(SampleRequest::buildFor(new Applicant(SampleRequest::bareMinimumPerson())))
            ->jsonSerialize();
        $person = $payload['persons'][0];

        self::assertSame('Jansen', $person['surname']);
        self::assertSame('K.', $person['initials']);
        self::assertSame('Kees', $person['firstname']);

        foreach (['mobilenumber', 'email', 'dateofbirth', 'gender', 'address', 'partner'] as $key) {
            self::assertArrayNotHasKey($key, $person, $key);
        }
    }

    public function testPartnerIsOmittedWhenNotProvided(): void
    {
        $payload = (new EdrPayloadMapper())
            ->createOrder(SampleRequest::buildFor(new Applicant(SampleRequest::person())))
            ->jsonSerialize();

        self::assertArrayNotHasKey('partner', $payload['persons'][0]);
    }

    public function testOccupantsIsOmittedWhenNotProvided(): void
    {
        $person = new \LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Person(
            initials: 'J.',
            firstName: 'Jan',
            surname: 'de Vries',
            address: new Address(
                country: 'NL',
                houseNumber: '12',
                street: 'Kerkstraat',
                postalCode: '1011AA',
                city: 'Amsterdam',
            ),
        );

        $payload = (new EdrPayloadMapper())
            ->createOrder(SampleRequest::buildFor(new Applicant($person)))
            ->jsonSerialize();

        self::assertArrayNotHasKey('amountOccupants', $payload['persons'][0]['address']);
    }

    public function testBareMinimumPersonStillEncodesToJsonWithoutNulls(): void
    {
        $json = json_encode(
            (new EdrPayloadMapper())
                ->createOrder(SampleRequest::buildFor(new Applicant(SampleRequest::bareMinimumPerson())))
                ->jsonSerialize(),
            JSON_THROW_ON_ERROR,
        );

        self::assertStringNotContainsString('null', $json);
    }
}
