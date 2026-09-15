<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Exception;

/**
 * HTTP 404 — the referenced order/person does not exist at EDR.
 */
class NotFoundException extends ApiException
{
}
