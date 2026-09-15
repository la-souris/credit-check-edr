<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Auth;

/**
 * Keeps the token for the lifetime of the process only — the default for plain PHP use.
 */
final class InMemoryTokenStore implements TokenStore
{
    private ?AccessToken $token = null;

    public function get(): ?AccessToken
    {
        return $this->token;
    }

    public function put(AccessToken $token): void
    {
        $this->token = $token;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
