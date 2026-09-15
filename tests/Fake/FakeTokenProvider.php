<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Fake;

use LaSouris\CreditCheck\Edr\Auth\TokenProvider;

/**
 * A token provider that hands out a fixed token and counts how often it was forgotten,
 * so client tests need not exercise the real identity endpoint.
 */
final class FakeTokenProvider implements TokenProvider
{
    public int $forgotten = 0;

    public function __construct(private string $token = 'test-jwt')
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }

    public function forget(): void
    {
        ++$this->forgotten;
        $this->token = 'refreshed-jwt';
    }
}
