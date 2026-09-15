<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Auth;

use DateTimeImmutable;
use Exception;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Edr\Exception\AuthenticationException;
use LaSouris\CreditCheck\Edr\Exception\EdrException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Obtains and caches a JWT from the EDR identity API using an email/password login, and
 * refreshes it with the refresh token when possible before falling back to a full re-login.
 *
 * Where the token is kept is the {@see TokenStore}'s business: per process by default,
 * or somewhere shared (e.g. the Laravel cache) when one is injected.
 */
final class PasswordCredentials implements TokenProvider
{
    private readonly TokenStore $store;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
        private readonly Environment $environment,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiVersion = '1.0',
        ?TokenStore $store = null,
    ) {
        $this->store = $store ?? new InMemoryTokenStore();
    }

    public function accessToken(): string
    {
        $token = $this->store->get();

        if ($token !== null && !$token->isExpired()) {
            return $token->value;
        }

        if ($token !== null && $token->canRefresh()) {
            try {
                return $this->remember($this->refresh($token))->value;
            } catch (EdrException) {
                // Refresh failed (e.g. revoked); fall through to a full login.
            }
        }

        return $this->remember($this->login())->value;
    }

    public function forget(): void
    {
        $token = $this->store->get();

        // EDR rejected the JWT, not necessarily the refresh token: keep the pair around as
        // expired so the next call refreshes, and only drop it when refreshing is impossible.
        if ($token !== null && $token->canRefresh()) {
            $this->store->put($token->withExpiredJwt());

            return;
        }

        $this->store->forget();
    }

    private function remember(AccessToken $token): AccessToken
    {
        $this->store->put($token);

        return $token;
    }

    private function login(): AccessToken
    {
        $url = $this->url('/api/identity/GenerateTokenByCredentials');
        $body = json_encode(['email' => $this->email, 'password' => $this->password], JSON_THROW_ON_ERROR);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        return $this->tokenFrom($this->exchange($request));
    }

    private function refresh(AccessToken $current): AccessToken
    {
        $url = $this->url('/api/identity/GenerateTokenByRefreshTokenAndJwtToken', [
            'refreshToken' => (string) $current->refreshToken,
            'jwtToken' => $current->value,
        ]);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Accept', 'application/json');

        return $this->tokenFrom($this->exchange($request));
    }

    /**
     * @return array<string, mixed> The decoded TokenResponse ("data") payload.
     */
    private function exchange(\Psr\Http\Message\RequestInterface $request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new EdrException('Could not reach the EDR identity endpoint: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        $decoded = is_array($decoded) ? $decoded : [];
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

        if ($status < 200 || $status >= 300 || !isset($data['jwToken'])) {
            $message = is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
                ? $decoded['message']
                : 'Could not obtain an access token from EDR.';

            throw new AuthenticationException(
                $message,
                $status,
                array_filter([is_string($decoded['message'] ?? null) ? $decoded['message'] : null]),
                $decoded,
                is_string($decoded['errorCode'] ?? null) ? $decoded['errorCode'] : null,
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tokenFrom(array $data): AccessToken
    {
        return new AccessToken(
            (string) $data['jwToken'],
            $this->timestamp($data['expiresOn'] ?? null) ?? (time() + 3600),
            is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : null,
            $this->timestamp($data['refreshTokenExpiresOn'] ?? null),
        );
    }

    private function timestamp(mixed $value): ?int
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param array<string, string> $query
     */
    private function url(string $path, array $query = []): string
    {
        $query['api-version'] = $this->apiVersion;

        return $this->environment->baseUrl() . $path . '?' . http_build_query($query);
    }
}
