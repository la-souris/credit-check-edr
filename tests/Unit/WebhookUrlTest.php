<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Unit;

use LaSouris\CreditCheck\Edr\Support\WebhookUrl;
use PHPUnit\Framework\TestCase;

final class WebhookUrlTest extends TestCase
{
    public function testReferenceIsAppendedAsQueryParameter(): void
    {
        self::assertSame(
            'https://app.example.com/webhooks/edr?reference=ORDER-123',
            WebhookUrl::build('https://app.example.com/webhooks/edr', 'ORDER-123'),
        );
    }

    public function testReferenceIsAppendedToBaseUrlWithExistingQuery(): void
    {
        self::assertSame(
            'https://app.example.com/webhooks/edr?tenant=1&reference=ORDER-123',
            WebhookUrl::build('https://app.example.com/webhooks/edr?tenant=1', 'ORDER-123'),
        );
    }

    public function testReferenceIsUrlEncoded(): void
    {
        self::assertSame(
            'https://app.example.com/webhooks/edr?reference=ORDER%2F123%20A',
            WebhookUrl::build('https://app.example.com/webhooks/edr', 'ORDER/123 A'),
        );
    }
}
