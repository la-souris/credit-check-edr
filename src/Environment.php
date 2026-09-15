<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr;

/**
 * The EDR (EDR Group) API environments. Both the identity and lease endpoints live on the
 * same host.
 */
enum Environment: string
{
    case Uat = 'https://uatapi.edrgroup.nl';
    case Production = 'https://api.edrgroup.nl';

    public function baseUrl(): string
    {
        return $this->value;
    }
}
