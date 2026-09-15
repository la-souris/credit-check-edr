<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * A tri-state yes/no answer; EDR distinguishes "not stated" from an explicit no.
 */
enum YesNo: string
{
    case Unknown = 'Unknown';
    case Yes = 'Yes';
    case No = 'No';

    public static function fromBool(?bool $value): self
    {
        return match ($value) {
            true => self::Yes,
            false => self::No,
            null => self::Unknown,
        };
    }
}
