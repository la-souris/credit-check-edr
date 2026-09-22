<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

use LaSouris\CreditCheck\Sdk\CreditCheck\Applicant\Gender as SdkGender;

enum Gender: string
{
    case Unknown = 'Unknown';
    case Male = 'Male';
    case Female = 'Female';

    /**
     * EDR only records male/female; anything else is reported as unknown. A gender the SDK was
     * never told is left unmapped entirely — the caller omits the field rather than guessing.
     */
    public static function fromSdk(?SdkGender $gender): ?self
    {
        return match ($gender) {
            SdkGender::Male => self::Male,
            SdkGender::Female => self::Female,
            SdkGender::Other => self::Unknown,
            null => null,
        };
    }
}
