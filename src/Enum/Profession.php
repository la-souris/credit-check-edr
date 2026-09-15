<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * EDR's professional categories. The SDK does not model profession, so these are set by callers
 * driving the wire models directly.
 */
enum Profession: string
{
    case Unknown = 'Unknown';
    case Teacher = 'Teacher';
    case ChildcareLeadersAndTeachingAssistants = 'ChildcareLeadersAndTeachingAssistants';
    case PedagogicalOther = 'PedagogicalOther';
    case CreativeAndLinguistic = 'CreativeAndLinguistic';
    case Commercial = 'Commercial';
    case Police = 'Police';
    case FireDepartment = 'FireDepartment';
    case SocialServiceProvider = 'SocialServiceProvider';
    case PublicAdministrationSecurityAndLegalOther = 'PublicAdministrationSecurityAndLegalOther';
    case Technical = 'Technical';
    case Agricultural = 'Agricultural';
    case Nurses = 'Nurses';
    case Carers = 'Carers';
    case CareAndWelfareOther = 'CareAndWelfareOther';
    case ServiceProvider = 'ServiceProvider';
    case TransportAndLogistic = 'TransportAndLogistic';
    case Other = 'Other';
}
