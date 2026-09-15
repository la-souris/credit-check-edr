<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Functional;

use DateTimeImmutable;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Edr\Exception\NotFoundException;
use LaSouris\CreditCheck\Edr\Exception\ValidationException;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateMetaDataLeaseModel;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderLeaseModel;
use LaSouris\CreditCheck\Edr\EdrClient;
use LaSouris\CreditCheck\Edr\Tests\Fake\FakeHttpClient;
use LaSouris\CreditCheck\Edr\Tests\Fake\FakeTokenProvider;
use LaSouris\CreditCheck\Edr\Tests\Fake\ResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class EdrClientTest extends TestCase
{
    private FakeHttpClient $http;
    private FakeTokenProvider $tokens;
    private EdrClient $client;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->tokens = new FakeTokenProvider();
        $psr17 = new Psr17Factory();
        $this->client = new EdrClient(Environment::Uat, $this->tokens, $this->http, $psr17, $psr17);
    }

    public function testCreateOrderReturnsOrderIdAndSendsBearerToken(): void
    {
        $this->http->queue(ResponseFactory::int32Result(4242));

        $orderId = $this->client->createOrder($this->order());

        self::assertSame(4242, $orderId);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('/api/v1/Lease/Create', (string) $request->getUri());
        self::assertStringContainsString('api-version=1.0', (string) $request->getUri());
        self::assertSame('Bearer test-jwt', $request->getHeaderLine('Authorization'));
    }

    public function testGetOrderReturnsDecodedBody(): void
    {
        $this->http->queue(ResponseFactory::orderStatus());

        $order = $this->client->getOrder(4242);

        self::assertSame(4242, $order['orderId']);
        self::assertStringContainsString('orderId=4242', (string) $this->http->lastRequest()->getUri());
    }

    public function testGetChangedOrderIdsSinceUtc(): void
    {
        $this->http->queue(ResponseFactory::json(200, [1, 2, 3]));

        $ids = $this->client->getChangedOrderIdsSinceUtc(new DateTimeImmutable('2026-07-01T09:00:00+02:00'));

        self::assertSame([1, 2, 3], $ids);
        $uri = (string) $this->http->lastRequest()->getUri();
        self::assertStringContainsString('GetChangedOrderIdsSinceUTC', $uri);
        // 09:00 in +02:00 is 07:00:00Z in UTC.
        self::assertStringContainsString('since=2026-07-01T07%3A00%3A00Z', $uri);
    }

    public function testRefreshesTokenAndRetriesOnUnauthorized(): void
    {
        $this->http->queue(ResponseFactory::error(401, 'Token expired', 'Token_Expired'));
        $this->http->queue(ResponseFactory::int32Result(99));

        $orderId = $this->client->createOrder($this->order());

        self::assertSame(99, $orderId);
        self::assertSame(1, $this->tokens->forgotten);
        self::assertCount(2, $this->http->requests);
        self::assertSame('Bearer refreshed-jwt', $this->http->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testValidationErrorBecomesValidationException(): void
    {
        $this->http->queue(ResponseFactory::error(400, 'Order incomplete', 'Order_Incomplete'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Order incomplete');

        $this->client->createOrder($this->order());
    }

    public function testNotFoundBecomesNotFoundException(): void
    {
        $this->http->queue(ResponseFactory::error(404, 'Order not found', 'Order_Not_Found'));

        $this->expectException(NotFoundException::class);

        $this->client->getOrder(1);
    }

    private function order(): CreateOrderLeaseModel
    {
        return new CreateOrderLeaseModel(
            'ORDER-1',
            [],
            new CreateMetaDataLeaseModel('Tesla', '2026-08-01T00:00:00', '2031-08-01T00:00:00', 45000.0, 60),
        );
    }
}
