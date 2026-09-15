<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Support;

final class Json
{
    /**
     * Drops null and empty values so fields the caller did not supply are left out of the
     * request body entirely, rather than sent as an explicit null.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function filter(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
