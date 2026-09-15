<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Functional;

use DateTimeImmutable;
use LaSouris\CreditCheck\Edr\CreditCheck\EdrCreditChecker;
use LaSouris\CreditCheck\Edr\EdrClient;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Edr\Tests\Fake\FakeHttpClient;
use LaSouris\CreditCheck\Edr\Tests\Fake\FakeTokenProvider;
use LaSouris\CreditCheck\Edr\Tests\Fake\ResponseFactory;
use LaSouris\CreditCheck\Edr\Tests\Fake\SampleRequest;
use LaSouris\CreditCheck\Sdk\CreditCheck\Exception\ProviderValidationException;
use LaSouris\CreditCheck\Sdk\Provider\Capability;
use LaSouris\CreditCheck\Sdk\Provider\ProviderCapabilities;
use LaSouris\CreditCheck\Sdk\Response\CheckStatus;
use LaSouris\CreditCheck\Sdk\Response\Decision;
use LaSouris\CreditCheck\Sdk\Response\TrafficLight;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class EdrCreditCheckerTest extends TestCase
{
    private FakeHttpClient $http;
    private EdrCreditChecker $checker;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $psr17 = new Psr17Factory();
        $client = new EdrClient(Environment::Uat, new FakeTokenProvider(), $this->http, $psr17, $psr17);
        $this->checker = new EdrCreditChecker($client);
    }

    public function testSubmitCheckReturnsReferenceWithOrderId(): void
    {
        $this->http->queue(ResponseFactory::int32Result(4242));

        $response = $this->checker->submitCheck(SampleRequest::build());

        self::assertSame('edr', $response->provider);
        self::assertSame(['orderId' => 4242], $response->raw);
        self::assertSame('4242', $response->reference);
        self::assertSame(CheckStatus::Submitted, $response->status);
    }

    public function testGetResultMapsApprovedDecision(): void
    {
        $this->http->queue(ResponseFactory::orderStatus());

        $result = $this->checker->getResult('4242');

        self::assertSame('edr', $result->provider);
        self::assertSame('4242', $result->reference);
        self::assertSame(Decision::Approved, $result->decision);
        self::assertSame(CheckStatus::Completed, $result->status);
        self::assertSame(TrafficLight::Green, $result->trafficLight);
        self::assertNotNull($result->expendableIncome);
        self::assertSame('125050', $result->expendableIncome->getAmount());
        self::assertSame('EUR', $result->expendableIncome->getCurrency()->getCode());
        self::assertCount(1, $result->applicantResults);
        self::assertSame(TrafficLight::Green, $result->applicantResults[0]->trafficLight);
        self::assertNotNull($result->applicantResults[0]->expendableIncome);
        self::assertSame('125050', $result->applicantResults[0]->expendableIncome->getAmount());
    }

    public function testGetResultMapsRejectionReasons(): void
    {
        $this->http->queue(ResponseFactory::orderStatus([
            'conclusion' => [
                'trafficLight' => 'Red',
                'rejectionReasons' => ['ShortageOfIncome', 'BKRCommentsNoted'],
                'feedBack' => ['trafficLight' => 'Red', 'reason' => 'CrsResultsFailed'],
            ],
        ]));

        $result = $this->checker->getResult('4242');

        self::assertSame(Decision::Rejected, $result->decision);
        self::assertCount(3, $result->rejectionReasons);
        self::assertSame('ShortageOfIncome', $result->rejectionReasons[0]->code);
    }

    public function testPendingWhenNotCompleted(): void
    {
        $this->http->queue(ResponseFactory::orderStatus([
            'completedDate' => null,
            'conclusion' => ['trafficLight' => 'Green', 'rejectionReasons' => []],
        ]));

        $result = $this->checker->getResult('4242');

        self::assertSame(Decision::Pending, $result->decision);
        self::assertSame(CheckStatus::InProgress, $result->status);
    }

    public function testApplicantTrafficLightIsTheWorstScreeningStep(): void
    {
        $this->http->queue(ResponseFactory::orderStatus([
            'persons' => [
                [
                    'id' => 1,
                    'type' => 'Default',
                    'validationDetails' => [
                        'sanctie' => ['trafficLight' => 'Green'],
                        'pep' => ['trafficLight' => 'Orange'],
                        'crs' => ['trafficLight' => 'Green', 'overallScore' => 87],
                    ],
                ],
            ],
        ]));

        $result = $this->checker->getResult('4242');

        self::assertSame(TrafficLight::Orange, $result->applicantResults[0]->trafficLight);
    }

    public function testApplicantTrafficLightIsNullWithoutScreeningSteps(): void
    {
        $this->http->queue(ResponseFactory::orderStatus([
            'persons' => [['id' => 1, 'type' => 'Default', 'validationDetails' => []]],
        ]));

        $result = $this->checker->getResult('4242');

        self::assertNull($result->applicantResults[0]->trafficLight);
    }

    public function testGetChangedChecksSinceReturnsStringReferences(): void
    {
        $this->http->queue(ResponseFactory::json(200, [10, 20, 30]));

        $response = $this->checker->getChangedChecksSince(new DateTimeImmutable('2026-07-01T00:00:00Z'));

        self::assertSame(['10', '20', '30'], $response->references);
        self::assertSame([10, 20, 30], $response->raw);
        self::assertSame('edr', $response->provider);
    }

    public function testValidationErrorFromApiBecomesProviderValidationException(): void
    {
        $this->http->queue(ResponseFactory::error(422, 'Duplicate email', 'Order_Duplicate_EmailAddress'));

        $this->expectException(ProviderValidationException::class);

        $this->checker->submitCheck(SampleRequest::build());
    }

    public function testAdvertisesAllCapabilitiesThroughItsAttribute(): void
    {
        $reach = ProviderCapabilities::of($this->checker);

        self::assertSame('edr', $reach->name);
        self::assertTrue($reach->supports(Capability::CREATE_CHECK));
        self::assertTrue($reach->supports(Capability::GET_RESULT));
        self::assertTrue($reach->supports(Capability::LIST_CHANGED));
        self::assertTrue($reach->assesses('NL'));
        self::assertFalse($reach->assesses('DE'));
    }
}
