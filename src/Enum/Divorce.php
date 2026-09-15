<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * Divorce/alimony situation, which affects an applicant's monthly capacity.
 */
enum Divorce: string
{
    case Unknown = 'Unknown';
    case NoResolved = 'NoResolved';
    case ReceivePartnerAlimony = 'ReceivePartnerAlimony';
    case PayPartnerAlimony = 'PayPartnerAlimony';
    case ResolvedNoAlimony = 'ResolvedNoAlimony';
}
