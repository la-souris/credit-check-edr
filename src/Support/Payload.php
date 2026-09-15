<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Support;

use JsonSerializable;

/**
 * Anything that can be rendered as (a fragment of) an EDR request body.
 */
interface Payload extends JsonSerializable
{
    /**
     * @return array<string, mixed> Body fragment using the field names the EDR API expects.
     */
    public function jsonSerialize(): array;
}
