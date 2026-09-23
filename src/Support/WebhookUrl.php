<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Support;

/**
 * Builds the callback URL sent to EDR as `statusChangeCallback` on order creation, so EDR can
 * tell our webhook endpoint which order a status change belongs to.
 *
 * EDR calls the URL back verbatim — it does not know our own reference, so it is carried as a
 * query parameter rather than assumed to be interpolated by EDR itself.
 */
final class WebhookUrl
{
    public static function build(string $baseUrl, string $reference): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'reference=' . rawurlencode($reference);
    }
}
