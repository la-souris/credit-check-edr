<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Exception;

/**
 * HTTP 401 — EDR rejected our token or credentials.
 */
class AuthenticationException extends ApiException
{
}
