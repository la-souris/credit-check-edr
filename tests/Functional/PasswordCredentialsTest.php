<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Functional;

use LaSouris\CreditCheck\Edr\Auth\AccessToken;
use LaSouris\CreditCheck\Edr\Auth\InMemoryTokenStore;
use LaSouris\CreditCheck\Edr\Auth\PasswordCredentials;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Edr\Tests\Fake\FakeHttpClient;
use LaSouris\CreditCheck\Edr\Tests\Fake\ResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class PasswordCredentialsTest extends TestCase
{
    private FakeHttpClient $http;
    private InMemoryTokenStore $store;
    private PasswordCredentials $credentials;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->store = new InMemoryTokenStore();
        $psr17 = new Psr17Factory();
        $this->credentials = new PasswordCredentials(
            'api@example.com',
            'secret',
            Environment::Uat,
            $this->http,
            $psr17,
            $psr17,
            '1.0',
            $this->store,
        );
    }

    public function testLoginStoresBothTokens(): void
    {
        $this->http->queue(ResponseFactory::token());

        self::assertSame('jwt-1', $this->credentials->accessToken());

        $stored = $this->store->get();
        self::assertNotNull($stored);
        self::assertSame('jwt-1', $stored->value);
        self::assertSame('refresh-1', $stored->refreshToken);
        self::assertStringContainsString('/api/identity/GenerateTokenByCredentials', (string) $this->http->lastRequest()->getUri());
    }

    public function testStoredTokenIsReusedWithoutAnotherLogin(): void
    {
        $this->store->put(new AccessToken('cached-jwt', time() + 3600, 'cached-refresh', time() + 86400));

        self::assertSame('cached-jwt', $this->credentials->accessToken());
        self::assertSame([], $this->http->requests, 'A valid stored token must not trigger an HTTP call.');
    }

    public function testExpiredTokenIsRefreshedWithTheStoredRefreshToken(): void
    {
        $this->store->put(new AccessToken('old-jwt', time() - 10, 'cached-refresh', time() + 86400));
        $this->http->queue(ResponseFactory::token(['jwToken' => 'jwt-2', 'refreshToken' => 'refresh-2']));

        self::assertSame('jwt-2', $this->credentials->accessToken());

        $uri = (string) $this->http->lastRequest()->getUri();
        self::assertStringContainsString('/api/identity/GenerateTokenByRefreshTokenAndJwtToken', $uri);
        self::assertStringContainsString('refreshToken=cached-refresh', $uri);
        self::assertSame('refresh-2', $this->store->get()?->refreshToken);
    }

    public function testFailedRefreshFallsBackToAFullLogin(): void
    {
        $this->store->put(new AccessToken('old-jwt', time() - 10, 'revoked', time() + 86400));
        $this->http->queue(ResponseFactory::error(401, 'Refresh token revoked.'));
        $this->http->queue(ResponseFactory::token(['jwToken' => 'jwt-3']));

        self::assertSame('jwt-3', $this->credentials->accessToken());
        self::assertCount(2, $this->http->requests);
        self::assertStringContainsString('/api/identity/GenerateTokenByCredentials', (string) $this->http->lastRequest()->getUri());
    }

    public function testExpiredTokenWithoutRefreshTokenLogsInAgain(): void
    {
        $this->store->put(new AccessToken('old-jwt', time() - 10));
        $this->http->queue(ResponseFactory::token(['jwToken' => 'jwt-4']));

        self::assertSame('jwt-4', $this->credentials->accessToken());
        self::assertStringContainsString('/api/identity/GenerateTokenByCredentials', (string) $this->http->lastRequest()->getUri());
    }

    public function testForgetWithoutARefreshTokenClearsTheStore(): void
    {
        $this->store->put(new AccessToken('cached-jwt', time() + 3600));

        $this->credentials->forget();

        self::assertNull($this->store->get());
    }

    /**
     * A 401 says the JWT is gone, not the refresh token — so forgetting must leave a
     * refreshable pair behind instead of forcing a password login.
     */
    public function testForgetKeepsARefreshableTokenForTheNextCall(): void
    {
        $this->store->put(new AccessToken('cached-jwt', time() + 3600, 'cached-refresh', time() + 86400));

        $this->credentials->forget();

        $stale = $this->store->get();
        self::assertNotNull($stale);
        self::assertTrue($stale->isExpired());
        self::assertSame('cached-refresh', $stale->refreshToken);

        $this->http->queue(ResponseFactory::token(['jwToken' => 'jwt-5', 'refreshToken' => 'refresh-5']));

        self::assertSame('jwt-5', $this->credentials->accessToken());
        self::assertCount(1, $this->http->requests);
        self::assertStringContainsString(
            '/api/identity/GenerateTokenByRefreshTokenAndJwtToken',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    /**
     * The identity endpoint returns token fields at the top level, unlike the rest of the
     * EDR API which wraps everything in a "data" envelope.
     */
    public function testLoginAcceptsAnUnwrappedTokenResponse(): void
    {
        $this->http->queue(ResponseFactory::json(200, [
            'id' => '5cfa8bf3-b670-4107-bc7c-a5680cdbb4bd',
            'tenantid' => 22,
            'email' => 'api@example.com',
            'jwToken' => 'jwt-unwrapped',
            'issuedOn' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'expiresOn' => gmdate('Y-m-d\\TH:i:s\\Z', time() + 3600),
            'refreshToken' => 'refresh-unwrapped',
            'refreshTokenExpiresOn' => gmdate('Y-m-d\\TH:i:s\\Z', time() + 86400),
        ]));

        self::assertSame('jwt-unwrapped', $this->credentials->accessToken());

        $stored = $this->store->get();
        self::assertNotNull($stored);
        self::assertSame('refresh-unwrapped', $stored->refreshToken);
    }

    public function testForgetWithAnExpiredRefreshTokenClearsTheStore(): void
    {
        $this->store->put(new AccessToken('cached-jwt', time() + 3600, 'cached-refresh', time() - 10));

        $this->credentials->forget();

        self::assertNull($this->store->get());
    }

    public function testWithoutAStoreTheTokenIsStillReusedInProcess(): void
    {
        $psr17 = new Psr17Factory();
        $credentials = new PasswordCredentials('api@example.com', 'secret', Environment::Uat, $this->http, $psr17, $psr17);
        $this->http->queue(ResponseFactory::token());

        self::assertSame('jwt-1', $credentials->accessToken());
        self::assertSame('jwt-1', $credentials->accessToken());
        self::assertCount(1, $this->http->requests);
    }
}
