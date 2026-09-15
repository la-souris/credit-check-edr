<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Tests\Fake;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds PSR-7 responses that mimic the EDR API's envelope shapes.
 */
final class ResponseFactory
{
    public static function json(int $status, mixed $body): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public static function int32Result(int $data, int $status = 200): ResponseInterface
    {
        return self::json($status, [
            'failed' => false,
            'message' => null,
            'errorCode' => 'No_Error',
            'succeeded' => true,
            'statusCode' => $status,
            'data' => $data,
        ]);
    }

    /**
     * The identity API's TokenResponse envelope.
     *
     * @param array<string, mixed> $overrides Fields of the inner "data" payload.
     */
    public static function token(array $overrides = []): ResponseInterface
    {
        return self::json(200, [
            'failed' => false,
            'message' => null,
            'errorCode' => 'No_Error',
            'succeeded' => true,
            'statusCode' => 200,
            'data' => array_replace([
                'jwToken' => 'jwt-1',
                'expiresOn' => gmdate('Y-m-d\\TH:i:s\\Z', time() + 3600),
                'refreshToken' => 'refresh-1',
                'refreshTokenExpiresOn' => gmdate('Y-m-d\\TH:i:s\\Z', time() + 86400),
            ], $overrides),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function orderStatus(array $overrides = []): ResponseInterface
    {
        $default = [
            'orderId' => 4242,
            'reference' => 'ORDER-123',
            'startDate' => '2026-07-20T10:00:00Z',
            'completedDate' => '2026-07-20T11:30:00Z',
            'conclusion' => [
                'trafficLight' => 'Green',
                'rejectionReasons' => [],
                'feedBack' => ['trafficLight' => 'Green', 'reason' => 'None'],
            ],
            'validationDetails' => [
                'validationSteps' => [
                    ['completed' => true, 'validateStep' => 'Income', 'trafficLight' => 'Green'],
                ],
                'ilt' => [
                    'monthlyCapacity' => 1250.50,
                    'trafficLight' => 'Green',
                    'persons' => [
                        ['type' => 'Default', 'monthlyCapacity' => 1250.50],
                    ],
                ],
            ],
            'persons' => [
                [
                    'id' => 1,
                    'type' => 'Default',
                    'surname' => 'de Vries',
                    'validationDetails' => [
                        'validateSteps' => [],
                        'sanctie' => ['trafficLight' => 'Green'],
                        'pep' => ['trafficLight' => 'Green'],
                        'crs' => ['trafficLight' => 'Green', 'overallScore' => 87],
                    ],
                ],
            ],
            'metaData' => [
                'carBrand' => 'Tesla',
                'carType' => 'Model 3',
                'leaseAmount' => 45000.0,
                'leasePeriodinMonths' => 60,
            ],
        ];

        return self::json(200, array_replace($default, $overrides));
    }

    public static function error(int $status, string $message, string $errorCode = 'Unknown_Error'): ResponseInterface
    {
        return self::json($status, [
            'failed' => true,
            'message' => $message,
            'errorCode' => $errorCode,
            'succeeded' => false,
            'statusCode' => $status,
        ]);
    }
}
