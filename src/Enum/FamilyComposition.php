<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

enum FamilyComposition: string
{
    case Unknown = 'Unknown';
    case Single = 'Single';
    case LivingTogetherMarried = 'LivingTogetherMarried';
    case Resident = 'Resident';
}
