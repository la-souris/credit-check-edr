<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * State-pension ("AOW") status, which changes the applicant's income profile.
 */
enum AowStatus: string
{
    case NotApplicable = 'Nvt';
    case ReachesAowAgeWithinOrder = 'ReachesAowAgeWithingOrder';
    case HasReachedAowAge = 'HasReachedAowAge';
}
