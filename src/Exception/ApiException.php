<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Exception;

/**
 * Thrown for any non-2xx response from the EDR API.
 */
class ApiException extends EdrException
{
    /**
     * @param list<string>         $errors    Human readable messages from the EDR "message" field.
     * @param array<string, mixed> $body      The decoded response body, for detail.
     * @param string|null          $errorCode The EDR "errorCode" enum value, when present.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $errors = [],
        public readonly array $body = [],
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
