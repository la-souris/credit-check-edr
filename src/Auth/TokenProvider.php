<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Auth;

interface TokenProvider
{
    /**
     * A valid JWT for the Authorization header, obtaining or refreshing one if needed.
     */
    public function accessToken(): string;

    /**
     * Invalidate the stored JWT, e.g. after a 401. A usable refresh token is kept, so the
     * next {@see accessToken()} refreshes rather than performing a full login.
     */
    public function forget(): void;
}
