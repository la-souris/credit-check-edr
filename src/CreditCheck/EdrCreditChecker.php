<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\CreditCheck;

use DateTimeInterface;
use LaSouris\CreditCheck\Edr\EdrClient;
use LaSouris\CreditCheck\Edr\Exception\ApiException;
use LaSouris\CreditCheck\Edr\Exception\EdrException;
use LaSouris\CreditCheck\Sdk\CreditCheck\Exception\ProviderException;
use LaSouris\CreditCheck\Sdk\CreditCheck\Exception\ProviderValidationException;
use LaSouris\CreditCheck\Sdk\Provider\Capability;
use LaSouris\CreditCheck\Sdk\Provider\CreditChecker;
use LaSouris\CreditCheck\Sdk\Provider\Provider;
use LaSouris\CreditCheck\Sdk\Request\CreateCreditCheckRequest;
use LaSouris\CreditCheck\Sdk\Response\ChangedChecksResponse;
use LaSouris\CreditCheck\Sdk\Response\CheckStatus;
use LaSouris\CreditCheck\Sdk\Response\CreateCreditCheckResponse;
use LaSouris\CreditCheck\Sdk\Response\GetCreditCheckResponse;
use LaSouris\CreditCheck\Sdk\Support\GuardsProviderReach;

/**
 * EDR (EDR Group) implementation of the credit-check contract.
 *
 * EDR's Lease API is asynchronous: submitCheck() creates an order and returns its id as the
 * reference, getResult() reads the evaluated order, and getChangedChecksSince() lists the ids
 * of orders that changed. All three capabilities are supported.
 *
 * Each answer is the use-case response for that call, whose `raw` carries EDR's decoded body
 * so the many per-person fields the SDK does not model stay reachable without a second call.
 */
#[Provider(
    name: 'edr',
    countries: ['NL'],
    currencies: ['EUR'],
    capabilities: [Capability::CREATE_CHECK, Capability::GET_RESULT, Capability::LIST_CHANGED],
)]
final class EdrCreditChecker implements CreditChecker
{
    use GuardsProviderReach;

    public function __construct(
        private readonly EdrClient $client,
        private readonly EdrPayloadMapper $mapper = new EdrPayloadMapper(),
    ) {
    }

    public function submitCheck(CreateCreditCheckRequest $request): CreateCreditCheckResponse
    {
        $this->assertSupportedCurrency($request->subject->amount->getCurrency());

        $address = $request->primaryApplicant()->person->address;
        if ($address === null) {
            throw new ProviderValidationException(
                "EDR requires the primary applicant's address to determine which country to assess.",
            );
        }
        $this->assertSupportedCountry($address->country);

        return $this->guard(function () use ($request): CreateCreditCheckResponse {
            $orderId = $this->client->createOrder($this->mapper->createOrder($request));

            // Lease/Create answers with the bare order id, so that is the whole raw body.
            return new CreateCreditCheckResponse(
                $this->reach()->name,
                (string) $orderId,
                CheckStatus::Submitted,
                ['orderId' => $orderId],
            );
        });
    }

    public function getResult(string $reference): GetCreditCheckResponse
    {
        return $this->guard(function () use ($reference): GetCreditCheckResponse {
            $order = $this->client->getOrder((int) $reference);

            return $this->mapper->result($order, $this->reach()->name);
        });
    }

    public function getChangedChecksSince(DateTimeInterface $since): ChangedChecksResponse
    {
        return $this->guard(function () use ($since): ChangedChecksResponse {
            $orderIds = $this->client->getChangedOrderIdsSinceUtc($since);

            return new ChangedChecksResponse(
                $this->reach()->name,
                array_map(static fn (int $id): string => (string) $id, $orderIds),
                $orderIds,
            );
        });
    }

    /**
     * Run an EDR call, translating its exceptions into the SDK hierarchy.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function guard(callable $call): mixed
    {
        try {
            return $call();
        } catch (ApiException $e) {
            throw ProviderException::fromHttpStatus($e->getMessage(), $e->statusCode, $e->errors, $e->body, $e->errorCode, $e);
        } catch (\InvalidArgumentException $e) {
            throw new ProviderValidationException($e->getMessage(), previous: $e);
        } catch (EdrException $e) {
            // Transport failure or a malformed response.
            throw new ProviderException($e->getMessage(), previous: $e);
        }
    }
}
