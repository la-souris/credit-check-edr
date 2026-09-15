<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

enum LivingArrangement: string
{
    case Unknown = 'Unknown';
    case Rental = 'Rental';
    case Buy = 'Buy';
    case Resident = 'Resident';
}
