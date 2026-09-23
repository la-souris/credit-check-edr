<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Model\Lease;

use LaSouris\CreditCheck\Edr\Enum\SalesChannel;
use LaSouris\CreditCheck\Edr\Support\Json;
use LaSouris\CreditCheck\Edr\Support\Payload;

/**
 * The "metaData" fragment on POST /api/v1/Lease/Create: what is being leased and for how long.
 */
final readonly class CreateMetaDataLeaseModel implements Payload
{
    public function __construct(
        public string $carBrand,
        public string $startDate,
        public string $endDate,
        public float $leaseAmount,
        public int $leasePeriodInMonths,
        public ?string $carType = null,
        public ?SalesChannel $orderSalesChannel = null,
        public ?string $statusChangeCallback = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return Json::filter([
            'carBrand' => $this->carBrand,
            'carType' => $this->carType,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'leaseAmount' => $this->leaseAmount,
            'leasePeriodInMonths' => $this->leasePeriodInMonths,
            'orderSalesChannel' => $this->orderSalesChannel?->value,
            'statusChangeCallback' => $this->statusChangeCallback,
        ]);
    }
}
