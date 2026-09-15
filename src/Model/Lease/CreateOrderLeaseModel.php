<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Model\Lease;

use LaSouris\CreditCheck\Edr\Support\Json;
use LaSouris\CreditCheck\Edr\Support\Payload;

/**
 * Request body for POST /api/v1/Lease/Create.
 */
final readonly class CreateOrderLeaseModel implements Payload
{
    /**
     * @param list<CreateOrderPersonModel> $persons
     */
    public function __construct(
        public string $reference,
        public array $persons,
        public CreateMetaDataLeaseModel $metaData,
    ) {
    }

    public function jsonSerialize(): array
    {
        return Json::filter([
            'reference' => $this->reference,
            'persons' => array_map(
                static fn (CreateOrderPersonModel $person): array => $person->jsonSerialize(),
                $this->persons,
            ),
            'metaData' => $this->metaData->jsonSerialize(),
        ]);
    }
}
