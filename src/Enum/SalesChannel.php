<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

use LaSouris\CreditCheck\Sdk\CreditCheck\SalesChannel as SdkSalesChannel;

enum SalesChannel: string
{
    case Unknown = 'Unknown';
    case Internet = 'Internet';
    case Dealership = 'Dealership';

    public static function fromSdk(SdkSalesChannel $value): self
    {
        return match ($value) {
            SdkSalesChannel::Unknown => self::Unknown,
            SdkSalesChannel::Internet => self::Internet,
            SdkSalesChannel::Dealership => self::Dealership,
        };
    }
}
