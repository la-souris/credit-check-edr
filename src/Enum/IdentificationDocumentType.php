<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

enum IdentificationDocumentType: string
{
    case Unknown = 'Unknown';
    case IdentityCard = 'IdentityCard';
    case Passport = 'Passport';
    case DriversLicense = 'DriversLicense';
    case ResidencePermit = 'ResidencePermit';
}
