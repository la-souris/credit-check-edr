<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Exception;

/**
 * HTTP 400 / 422 — the submitted order did not satisfy EDR's rules.
 */
class ValidationException extends ApiException
{
}
