<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Auth;

/**
 * Where a {@see TokenProvider} keeps the token it obtained.
 *
 * The default is per-process memory ({@see InMemoryTokenStore}); a framework
 * integration can swap in a shared store (e.g. the Laravel cache) so the JWT and
 * refresh token survive across requests and workers.
 */
interface TokenStore
{
    /**
     * The stored token, or null when nothing is stored (or it could no longer be read).
     */
    public function get(): ?AccessToken;

    /**
     * Store the token. Implementations that expire entries should keep it at least until
     * the refresh token is unusable, so a refresh is still possible after the JWT expired.
     */
    public function put(AccessToken $token): void;

    public function forget(): void;
}
