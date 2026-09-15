<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Auth;

final readonly class AccessToken
{
    public function __construct(
        public string $value,
        public int $expiresAt,
        public ?string $refreshToken = null,
        public ?int $refreshTokenExpiresAt = null,
    ) {
    }

    public function isExpired(int $leewaySeconds = 30): bool
    {
        return time() >= ($this->expiresAt - $leewaySeconds);
    }

    /**
     * Whether the refresh token is present and still usable, so we can refresh
     * instead of doing a full re-login.
     */
    public function canRefresh(int $leewaySeconds = 30): bool
    {
        if ($this->refreshToken === null) {
            return false;
        }

        return $this->refreshTokenExpiresAt === null
            || time() < ($this->refreshTokenExpiresAt - $leewaySeconds);
    }

    /**
     * A copy whose JWT counts as expired while the refresh token is kept, so the next
     * call refreshes instead of logging in again. Used when EDR rejected the JWT with
     * a 401 even though we still considered it valid.
     */
    public function withExpiredJwt(): self
    {
        return new self(
            $this->value,
            time() - 1,
            $this->refreshToken,
            $this->refreshTokenExpiresAt,
        );
    }

    /**
     * Plain-array form for a {@see TokenStore} that has to serialize the token.
     *
     * @return array{value: string, expiresAt: int, refreshToken: ?string, refreshTokenExpiresAt: ?int}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'expiresAt' => $this->expiresAt,
            'refreshToken' => $this->refreshToken,
            'refreshTokenExpiresAt' => $this->refreshTokenExpiresAt,
        ];
    }

    /**
     * Rebuild from {@see toArray()}. Returns null for anything that is not a well-formed
     * payload, so a stale or foreign cache entry degrades to "no token" instead of a crash.
     *
     * @param mixed $data
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !is_string($data['value'] ?? null) || $data['value'] === '') {
            return null;
        }

        if (!is_int($data['expiresAt'] ?? null)) {
            return null;
        }

        $refreshToken = $data['refreshToken'] ?? null;
        $refreshTokenExpiresAt = $data['refreshTokenExpiresAt'] ?? null;

        return new self(
            $data['value'],
            $data['expiresAt'],
            is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
            is_int($refreshTokenExpiresAt) ? $refreshTokenExpiresAt : null,
        );
    }
}
