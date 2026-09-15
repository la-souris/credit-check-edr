<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * The role a person plays in an EDR order; used to line the affordability ("ilt") figures up
 * with the persons on the order.
 */
enum PersonType: string
{
    case Default = 'Default';
    case DefaultPartner = 'DefaultPartner';
    case Guarantor = 'Guarantor';
    case GuarantorPartner = 'GuarantorPartner';
}
