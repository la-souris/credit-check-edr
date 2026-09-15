<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

enum MaritalStatus: string
{
    case Unknown = 'Unknown';
    case Single = 'Single';
    case LivingTogether = 'Livingtogether';
    case RegisteredPartnership = 'Registeredpartnership';
    case Married = 'Married';
    case Divorced = 'Divorced';
    case Widowhood = 'Widowhood';
}
