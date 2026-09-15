<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Unit;

use LaSouris\CreditCheck\Edr\Auth\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccessTokenTest extends TestCase
{
    public function testExpiryWithLeeway(): void
    {
        self::assertTrue((new AccessToken('t', time() + 10))->isExpired());
        self::assertFalse((new AccessToken('t', time() + 3600))->isExpired());
    }

    public function testCanRefreshRequiresRefreshToken(): void
    {
        self::assertFalse((new AccessToken('t', time()))->canRefresh());
        self::assertTrue((new AccessToken('t', time(), 'refresh', time() + 3600))->canRefresh());
        self::assertFalse((new AccessToken('t', time(), 'refresh', time()))->canRefresh());
    }

    public function testWithExpiredJwtKeepsTheRefreshToken(): void
    {
        $token = new AccessToken('jwt', time() + 3600, 'refresh', time() + 86400);

        $stale = $token->withExpiredJwt();

        self::assertTrue($stale->isExpired());
        self::assertTrue($stale->canRefresh());
        self::assertSame('jwt', $stale->value);
        self::assertSame('refresh', $stale->refreshToken);
        self::assertSame($token->refreshTokenExpiresAt, $stale->refreshTokenExpiresAt);
    }

    public function testArrayRoundTripKeepsBothTokens(): void
    {
        $token = new AccessToken('jwt', 1_800_000_000, 'refresh', 1_800_086_400);

        $restored = AccessToken::fromArray($token->toArray());

        self::assertNotNull($restored);
        self::assertSame('jwt', $restored->value);
        self::assertSame(1_800_000_000, $restored->expiresAt);
        self::assertSame('refresh', $restored->refreshToken);
        self::assertSame(1_800_086_400, $restored->refreshTokenExpiresAt);
    }

    public function testArrayRoundTripWithoutARefreshToken(): void
    {
        $restored = AccessToken::fromArray((new AccessToken('jwt', 1_800_000_000))->toArray());

        self::assertNotNull($restored);
        self::assertNull($restored->refreshToken);
        self::assertNull($restored->refreshTokenExpiresAt);
    }

    /**
     * A stale or foreign entry must read as "no token", never as a broken one.
     */
    #[DataProvider('malformedPayloads')]
    public function testFromArrayRejectsMalformedPayloads(mixed $payload): void
    {
        self::assertNull(AccessToken::fromArray($payload));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'null' => [null];
        yield 'scalar' => ['jwt'];
        yield 'empty array' => [[]];
        yield 'empty value' => [['value' => '', 'expiresAt' => 1]];
        yield 'missing expiry' => [['value' => 'jwt']];
        yield 'expiry not an int' => [['value' => 'jwt', 'expiresAt' => '1800000000']];
    }

    public function testFromArrayIgnoresUnusableRefreshFields(): void
    {
        $restored = AccessToken::fromArray([
            'value' => 'jwt',
            'expiresAt' => 1_800_000_000,
            'refreshToken' => '',
            'refreshTokenExpiresAt' => 'later',
        ]);

        self::assertNotNull($restored);
        self::assertNull($restored->refreshToken);
        self::assertNull($restored->refreshTokenExpiresAt);
    }
}
