<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Fake;

use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * In-memory PSR-18 client that returns queued responses and records the requests it was
 * asked to send, so tests can assert on both.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function queue(ResponseInterface|ClientExceptionInterface $response): void
    {
        $this->queue[] = $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new LogicException('FakeHttpClient received an unexpected request; no response was queued.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        if ($this->requests === []) {
            throw new LogicException('No request was sent.');
        }

        return $this->requests[array_key_last($this->requests)];
    }
}
