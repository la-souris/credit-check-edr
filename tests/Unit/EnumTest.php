<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Unit;

use LaSouris\CreditCheck\Edr\Enum\Gender;
use LaSouris\CreditCheck\Edr\Enum\IncomeSource;
use LaSouris\CreditCheck\Edr\Enum\MaritalStatus;
use LaSouris\CreditCheck\Edr\Enum\OccupantCount;
use LaSouris\CreditCheck\Edr\Enum\Profession;
use LaSouris\CreditCheck\Edr\Enum\SalesChannel;
use LaSouris\CreditCheck\Edr\Enum\TrafficLight;
use LaSouris\CreditCheck\Edr\Enum\TransferDate;
use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Gender as SdkGender;
use LaSouris\CreditCheck\Sdk\CreditCheck\SalesChannel as SdkSalesChannel;
use LaSouris\CreditCheck\Sdk\Response\TrafficLight as SdkTrafficLight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    public function testEverySdkGenderWidensToAWireValue(): void
    {
        foreach (SdkGender::cases() as $case) {
            self::assertNotSame('', Gender::fromSdk($case)->value, $case->name);
        }
    }

    public function testEverySdkSalesChannelWidensToAWireValue(): void
    {
        foreach (SdkSalesChannel::cases() as $case) {
            self::assertNotSame('', SalesChannel::fromSdk($case)->value, $case->name);
        }
    }

    public function testGenderFoldsOtherOntoUnknown(): void
    {
        self::assertSame(Gender::Male, Gender::fromSdk(SdkGender::Male));
        self::assertSame(Gender::Female, Gender::fromSdk(SdkGender::Female));
        self::assertSame(Gender::Unknown, Gender::fromSdk(SdkGender::Other));
    }

    /**
     * These vocabularies have no SDK counterpart — they exist for callers driving the wire
     * models directly, so the test just pins the exact strings EDR expects.
     */
    public function testWireValuesThatDifferFromTheirCaseName(): void
    {
        self::assertSame('LoondienstOnbepaald', IncomeSource::EmploymentPermanent->value);
        self::assertSame('DuoCosts', IncomeSource::StudentLoanCosts->value);
        self::assertSame('Vermogen', IncomeSource::Assets->value);
        self::assertSame('Nurses', Profession::Nurses->value);
        self::assertSame('Livingtogether', MaritalStatus::LivingTogether->value);
        self::assertSame('between2monthsand6months', TransferDate::BetweenTwoAndSixMonths->value);
    }

    #[DataProvider('occupantProvider')]
    public function testOccupantCountIsBucketed(int $occupants, OccupantCount $expected): void
    {
        self::assertSame($expected, OccupantCount::fromCount($occupants));
    }

    /**
     * @return iterable<string, array{int, OccupantCount}>
     */
    public static function occupantProvider(): iterable
    {
        yield 'one' => [1, OccupantCount::One];
        yield 'two' => [2, OccupantCount::Two];
        yield 'three' => [3, OccupantCount::Three];
        yield 'four' => [4, OccupantCount::Four];
        yield 'five buckets up' => [5, OccupantCount::MoreAsFour];
        yield 'twelve buckets up' => [12, OccupantCount::MoreAsFour];
        yield 'zero floors to one' => [0, OccupantCount::One];
    }

    public function testTrafficLightFromWire(): void
    {
        self::assertSame(TrafficLight::Green, TrafficLight::fromWire('Green'));
        self::assertSame(TrafficLight::Red, TrafficLight::fromWire('Red'));
        self::assertNull(TrafficLight::fromWire('bogus'));
        self::assertNull(TrafficLight::fromWire(null));
        self::assertNull(TrafficLight::fromWire(42));
    }

    public function testTrafficLightToSdk(): void
    {
        self::assertSame(SdkTrafficLight::Green, TrafficLight::Green->toSdk());
        self::assertSame(SdkTrafficLight::Orange, TrafficLight::Orange->toSdk());
        self::assertSame(SdkTrafficLight::Red, TrafficLight::Red->toSdk());
    }

    public function testTrafficLightSeverityOrdersGreenBeforeRed(): void
    {
        self::assertLessThan(TrafficLight::Orange->severity(), TrafficLight::Green->severity());
        self::assertLessThan(TrafficLight::Red->severity(), TrafficLight::Orange->severity());
    }
}
