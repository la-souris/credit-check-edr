<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LaSouris\CreditCheck\Edr\Auth\PasswordCredentials;
use LaSouris\CreditCheck\Edr\Auth\TokenProvider;
use LaSouris\CreditCheck\Edr\Auth\TokenStore;
use LaSouris\CreditCheck\Edr\Exception\ApiException;
use LaSouris\CreditCheck\Edr\Exception\AuthenticationException;
use LaSouris\CreditCheck\Edr\Exception\NotFoundException;
use LaSouris\CreditCheck\Edr\Exception\EdrException;
use LaSouris\CreditCheck\Edr\Exception\ServerException;
use LaSouris\CreditCheck\Edr\Exception\ValidationException;
use LaSouris\CreditCheck\Edr\Model\Lease\CreateOrderLeaseModel;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Client for the EDR (EDR Group) Lease credit-check REST API.
 *
 * All method and property names are in English; the field names required by the API are
 * applied by the wire models during serialization only.
 */
final class EdrClient
{
    public function __construct(
        private readonly Environment $environment,
        private readonly TokenProvider $tokenProvider,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiVersion = '1.0',
    ) {
    }

    /**
     * Convenience constructor for the common email/password setup.
     *
     * Pass a $tokenStore to keep the JWT and refresh token somewhere shared (e.g. a cache)
     * instead of per process.
     */
    public static function create(
        Environment $environment,
        string $email,
        string $password,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $apiVersion = '1.0',
        ?TokenStore $tokenStore = null,
    ): self {
        return new self(
            $environment,
            new PasswordCredentials($email, $password, $environment, $httpClient, $requestFactory, $streamFactory, $apiVersion, $tokenStore),
            $httpClient,
            $requestFactory,
            $streamFactory,
            $apiVersion,
        );
    }

    /**
     * POST /api/v1/Lease/Create — create a lease order; returns the new order id.
     */
    public function createOrder(CreateOrderLeaseModel $order): int
    {
        $body = json_encode($order, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = $this->send('POST', '/api/v1/Lease/Create', body: $body, retryOnUnauthorized: true);
        $decoded = $this->decode($response);

        if (!isset($decoded['data']) || !is_int($decoded['data'])) {
            throw new EdrException('EDR returned a create response without an order id.');
        }

        return $decoded['data'];
    }

    /**
     * GET /api/v1/Lease/Get — the compact status object for one order.
     *
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array
    {
        $response = $this->send('GET', '/api/v1/Lease/Get', query: ['orderId' => (string) $orderId], retryOnUnauthorized: true);

        return $this->decode($response);
    }

    /**
     * GET /api/v1/Lease/GetChangedOrderIdsSinceUTC — ids of orders changed since the given UTC moment.
     *
     * @return list<int>
     */
    public function getChangedOrderIdsSinceUtc(DateTimeInterface $since): array
    {
        $utc = DateTimeImmutable::createFromInterface($since)->setTimezone(new DateTimeZone('UTC'));
        $response = $this->send(
            'GET',
            '/api/v1/Lease/GetChangedOrderIdsSinceUTC',
            query: ['since' => $utc->format('Y-m-d\TH:i:s\Z')],
            retryOnUnauthorized: true,
        );

        $decoded = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $this->decode($response); // throws with the decoded error detail
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $decoded));
    }

    /**
     * @param array<string, string> $query
     */
    private function send(string $method, string $path, array $query = [], ?string $body = null, bool $retryOnUnauthorized = false): ResponseInterface
    {
        $query['api-version'] = $this->apiVersion;
        $url = $this->environment->baseUrl() . $path . '?' . http_build_query($query);

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->tokenProvider->accessToken())
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        $response = $this->dispatch($request);

        // An expired token that we still consider valid: refresh once and retry.
        if ($response->getStatusCode() === 401 && $retryOnUnauthorized) {
            $this->tokenProvider->forget();

            return $this->send($method, $path, $query, $body, retryOnUnauthorized: false);
        }

        return $response;
    }

    private function dispatch(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new EdrException('Request to EDR failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }

        $message = is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
            ? $decoded['message']
            : sprintf('EDR returned HTTP %d.', $status);
        $errorCode = is_string($decoded['errorCode'] ?? null) ? $decoded['errorCode'] : null;
        $errors = array_filter([is_string($decoded['message'] ?? null) ? $decoded['message'] : null]);

        throw match (true) {
            $status === 401, $status === 403 => new AuthenticationException($message, $status, $errors, $decoded, $errorCode),
            $status === 404 => new NotFoundException($message, $status, $errors, $decoded, $errorCode),
            $status === 400, $status === 422 => new ValidationException($message, $status, $errors, $decoded, $errorCode),
            $status >= 500 => new ServerException($message, $status, $errors, $decoded, $errorCode),
            default => new ApiException($message, $status, $errors, $decoded, $errorCode),
        };
    }
}
