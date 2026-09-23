<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Unit\Webhook;

use LaSouris\CreditCheck\Edr\Webhook\Metadata;
use LaSouris\CreditCheck\Sdk\Provider\WebhookMetadata;
use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    public function testCarriesTheParsedFields(): void
    {
        $metadata = new Metadata(reference: 'ORDER-123', orderId: 'EDR-1', newStatus: 'Approved');

        self::assertSame('ORDER-123', $metadata->reference);
        self::assertSame('EDR-1', $metadata->orderId);
        self::assertSame('Approved', $metadata->newStatus);
        self::assertInstanceOf(WebhookMetadata::class, $metadata);
    }

    public function testFieldsAreIndependentlyOptional(): void
    {
        $metadata = new Metadata(reference: null, orderId: null, newStatus: null);

        self::assertNull($metadata->reference);
        self::assertNull($metadata->orderId);
        self::assertNull($metadata->newStatus);
    }
}
