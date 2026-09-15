<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Unit;

use LaSouris\CreditCheck\Edr\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testBaseUrls(): void
    {
        self::assertSame('https://uatapi.edrgroup.nl', Environment::Uat->baseUrl());
        self::assertSame('https://api.edrgroup.nl', Environment::Production->baseUrl());
    }
}
