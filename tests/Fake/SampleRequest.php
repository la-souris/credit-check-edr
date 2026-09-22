<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Fake;

use DateTimeImmutable;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Address;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\ContactInformation;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Gender;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Person;
use LaSouris\CreditCheck\Sdk\CreditCheck\SalesChannel;
use LaSouris\CreditCheck\Sdk\CreditCheck\Subject;
use LaSouris\CreditCheck\Sdk\Request\CreateCreditCheckRequest;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * Builds a representative SDK credit-check request for the EDR provider tests.
 */
final class SampleRequest
{
    private static function eur(string $decimal): Money
    {
        return (new DecimalMoneyParser(new ISOCurrencies()))->parse($decimal, new Currency('EUR'));
    }

    private static function phone(string $number): PhoneNumber
    {
        return PhoneNumberUtil::getInstance()->parse($number, 'NL');
    }

    private static function address(string $houseNumber, string $country): Address
    {
        return new Address(
            country: $country,
            houseNumber: $houseNumber,
            street: 'Kerkstraat',
            postalCode: '1011AA',
            city: 'Amsterdam',
            occupants: 2,
        );
    }

    public static function person(string $houseNumber = '12', string $country = 'NL'): Person
    {
        return new Person(
            initials: 'J.',
            firstName: 'Jan',
            surname: 'de Vries',
            gender: Gender::Male,
            dateOfBirth: new DateTimeImmutable('1990-05-01'),
            contactInformation: new ContactInformation('jan@example.com', self::phone('06 12345678')),
            address: self::address($houseNumber, $country),
        );
    }

    /**
     * A person carrying only what the SDK guarantees — initials, first name, surname — with
     * everything else (gender, date of birth, contact information, address) left unset.
     */
    public static function bareMinimumPerson(): Person
    {
        return new Person(
            initials: 'K.',
            firstName: 'Kees',
            surname: 'Jansen',
        );
    }

    public static function partner(string $houseNumber = '12', string $country = 'NL'): Person
    {
        return new Person(
            initials: 'M.',
            firstName: 'Marie',
            surname: 'de Vries',
            gender: Gender::Female,
            dateOfBirth: new DateTimeImmutable('1991-09-17'),
            contactInformation: new ContactInformation('marie@example.com', self::phone('06 87654321')),
            address: self::address($houseNumber, $country),
        );
    }

    public static function build(string $houseNumber = '12', string $country = 'NL'): CreateCreditCheckRequest
    {
        return self::buildFor(new Applicant(
            person: self::person($houseNumber, $country),
            partner: self::partner($houseNumber, $country),
        ));
    }

    public static function buildFor(Applicant $applicant): CreateCreditCheckRequest
    {
        // $applicants is variadic, so reference, subject and sales channel go positionally.
        return new CreateCreditCheckRequest(
            'ORDER-123',
            new Subject(
                label: 'Tesla',
                variant: 'Model 3',
                amount: self::eur('45000.00'),
                termInMonths: 60,
                startDate: new DateTimeImmutable('2026-08-01T00:00:00'),
                endDate: new DateTimeImmutable('2031-08-01T00:00:00'),
            ),
            SalesChannel::Internet,
            $applicant,
        );
    }
}
