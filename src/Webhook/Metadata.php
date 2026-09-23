<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Webhook;

use LaSouris\CreditCheck\Sdk\Provider\WebhookMetadata;

/**
 * EDR's own status-change callback fields, parsed from its query parameters (`Ref`, `orderId`,
 * `newStatus`). Any of these may be absent on a malformed or unexpected call.
 */
final readonly class Metadata implements WebhookMetadata
{
    public function __construct(
        public ?string $reference,
        public ?string $orderId,
        public ?string $newStatus,
    ) {
    }
}
