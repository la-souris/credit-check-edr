<?php

declare(strict_types=1);

/**
 * Minimal end-to-end example: authenticate against EDR, submit a credit check for a lease
 * applicant and poll for the decision.
 *
 * Run with real PSR-18/17 implementations (Guzzle + nyholm/psr7 are dev deps here):
 *
 *     EDR_EMAIL=... EDR_PASSWORD=... php examples/perform_credit_check.php
 */

use GuzzleHttp\Client as GuzzleClient;
use LaSouris\CreditCheck\Edr\CreditCheck\EdrCreditChecker;
use LaSouris\CreditCheck\Edr\EdrClient;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Address;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\ContactInformation;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Gender;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Person;
use LaSouris\CreditCheck\Sdk\CreditCheck\SalesChannel;
use LaSouris\CreditCheck\Sdk\CreditCheck\Subject;
use LaSouris\CreditCheck\Sdk\Request\CreateCreditCheck;
use libphonenumber\PhoneNumberUtil;
use Money\Money;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/../vendor/autoload.php';

$psr17 = new Psr17Factory();
$http = new GuzzleClient();

$client = EdrClient::create(
    Environment::Uat,
    (string) getenv('EDR_EMAIL'),
    (string) getenv('EDR_PASSWORD'),
    $http,
    $psr17,
    $psr17,
);

$checker = new EdrCreditChecker($client);

// Money::EUR(cents) — moneyphp/money exposes per-currency factories that take minor units.
$person = new Person(
    initials: 'J.',
    firstName: 'Jan',
    surname: 'de Vries',
    gender: Gender::Male,
    dateOfBirth: new DateTimeImmutable('1990-05-01'),
    contactInformation: new ContactInformation(
        email: 'jan@example.com',
        mobileNumber: PhoneNumberUtil::getInstance()->parse('06 12345678', 'NL'),
    ),
    address: new Address(
        country: 'NL',
        houseNumber: '12A',
        street: 'Kerkstraat',
        postalCode: '1011AA',
        city: 'Amsterdam',
        occupants: 2,
    ),
);

$partner = new Person(
    initials: 'M.',
    firstName: 'Marieke',
    surname: 'de Vries',
    gender: Gender::Female,
    dateOfBirth: new DateTimeImmutable('1992-03-14'),
    contactInformation: new ContactInformation(
        email: 'marieke@example.com',
        mobileNumber: PhoneNumberUtil::getInstance()->parse('06 87654321', 'NL'),
    ),
    address: new Address(
        country: 'NL',
        houseNumber: '12A',
        street: 'Kerkstraat',
        postalCode: '1011AA',
        city: 'Amsterdam',
        occupants: 2,
    ),
);

$applicant = new Applicant($person, $partner);

$request = new CreateCreditCheck(
    'ORDER-' . date('YmdHis'),
    new Subject(
        label: 'Tesla',
        variant: 'Model 3',
        amount: Money::EUR(4500000),             // €45,000.00 in cents
        termInMonths: 60,
        startDate: new DateTimeImmutable('+1 week'),
        endDate: new DateTimeImmutable('+5 years'),
    ),
    SalesChannel::Internet,
    $applicant,
);

$receipt = $checker->submitCheck($request);
printf("Submitted; EDR order id = %s\n", $receipt->reference);

$result = $checker->getResult($receipt->reference);
printf("Decision: %s (traffic light: %s)\n", $result->decision->value, $result->trafficLight?->value ?? 'n/a');

foreach ($result->rejectionReasons as $reason) {
    printf("  - reason: %s\n", $reason->code);
}
