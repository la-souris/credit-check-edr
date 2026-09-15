<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * When a currently owned home is (expected to be) transferred to its buyer.
 */
enum TransferDate: string
{
    case Unknown = 'Unknown';
    case Done = 'Done';
    case WithinTwoMonths = 'twomonths';
    case BetweenTwoAndSixMonths = 'between2monthsand6months';
    case SixMonths = 'sixmonths';
}
