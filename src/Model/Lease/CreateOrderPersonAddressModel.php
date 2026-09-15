<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Model\Lease;

use LaSouris\CreditCheck\Edr\Enum\OccupantCount;
use LaSouris\CreditCheck\Edr\Enum\TransferDate;
use LaSouris\CreditCheck\Edr\Enum\YesNo;
use LaSouris\CreditCheck\Edr\Support\Json;
use LaSouris\CreditCheck\Edr\Support\Payload;

/**
 * The "address" fragment of a person on POST /api/v1/Lease/Create.
 *
 * Only the house number is structurally required; everything the SDK does not model is
 * optional and simply left out of the body when not supplied.
 */
final readonly class CreateOrderPersonAddressModel implements Payload
{
    public function __construct(
        public int $houseNumber,
        public ?string $street = null,
        public ?string $houseNumberAddOn = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?OccupantCount $amountOccupants = null,
        public ?string $livingOnAddressSince = null,
        public ?YesNo $mortgagDebt = null,
        public ?YesNo $houseSold = null,
        public ?TransferDate $transferdate = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return Json::filter([
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'houseNumberAddOn' => $this->houseNumberAddOn,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'livingOnAddressSince' => $this->livingOnAddressSince,
            'amountOccupants' => $this->amountOccupants?->value,
            'mortgagDebt' => $this->mortgagDebt?->value,
            'houseSold' => $this->houseSold?->value,
            'transferdate' => $this->transferdate?->value,
        ]);
    }
}
