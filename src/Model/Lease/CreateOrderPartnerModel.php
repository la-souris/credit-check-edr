<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Model\Lease;

use LaSouris\CreditCheck\Edr\Enum\AowStatus;
use LaSouris\CreditCheck\Edr\Enum\Divorce;
use LaSouris\CreditCheck\Edr\Enum\FamilyComposition;
use LaSouris\CreditCheck\Edr\Enum\Gender;
use LaSouris\CreditCheck\Edr\Enum\IdentificationDocumentType;
use LaSouris\CreditCheck\Edr\Enum\IncomeSource;
use LaSouris\CreditCheck\Edr\Enum\LivingArrangement;
use LaSouris\CreditCheck\Edr\Enum\MaritalStatus;
use LaSouris\CreditCheck\Edr\Enum\Profession;
use LaSouris\CreditCheck\Edr\Enum\YesNo;
use LaSouris\CreditCheck\Edr\Support\Json;
use LaSouris\CreditCheck\Edr\Support\Payload;

/**
 * The "partner" fragment of a person on POST /api/v1/Lease/Create.
 *
 * Identical to {@see CreateOrderPersonModel} but without an address or nested partner.
 *
 * Only `surname` and `initials` are structurally required — the SDK guarantees those on every
 * `Person`. Everything else is optional for the same reasons as on {@see CreateOrderPersonModel}.
 */
final readonly class CreateOrderPartnerModel implements Payload
{
    public function __construct(
        public string $surname,
        public string $initials,
        public ?string $mobilenumber = null,
        public ?string $email = null,
        public ?string $dateofbirth = null,
        public ?Gender $gender = null,
        public ?string $firstname = null,
        public ?IncomeSource $sourceofincome = null,
        public ?LivingArrangement $livingArrangement = null,
        public ?Profession $profession = null,
        public ?float $totalNetMonthlyIncome = null,
        public ?float $totalGrossYearIncome = null,
        public ?float $totalAssetIncome = null,
        public ?float $totalDebt = null,
        public ?MaritalStatus $maritalStatus = null,
        public ?Divorce $divorce = null,
        public ?FamilyComposition $familyComposition = null,
        public ?AowStatus $aowStatus = null,
        public ?YesNo $fromEERCountry = null,
        public ?YesNo $incomeSavingAccount = null,
        public ?YesNo $incomeEffectenDepot = null,
        public ?YesNo $kids = null,
        public ?YesNo $hasCurrentLease = null,
        public ?YesNo $hasDuoLoan = null,
        public ?string $nickname = null,
        public ?string $prefix = null,
        public ?string $birthplace = null,
        public ?string $bankAccountNumber = null,
        public ?string $bankAccountName = null,
        public ?IdentificationDocumentType $identificationDocumentType = null,
        public ?string $identificationDocumentNumber = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return Json::filter([
            'firstname' => $this->firstname,
            'nickname' => $this->nickname,
            'surname' => $this->surname,
            'prefix' => $this->prefix,
            'initials' => $this->initials,
            'mobilenumber' => $this->mobilenumber,
            'email' => $this->email,
            'dateofbirth' => $this->dateofbirth,
            'birthplace' => $this->birthplace,
            'gender' => $this->gender?->value,
            'sourceofincome' => $this->sourceofincome?->value,
            'maritalStatus' => $this->maritalStatus?->value,
            'divorce' => $this->divorce?->value,
            'familyComposition' => $this->familyComposition?->value,
            'livingArrangement' => $this->livingArrangement?->value,
            'profession' => $this->profession?->value,
            'totalNetMonthlyIncome' => $this->totalNetMonthlyIncome,
            'totalGrossYearIncome' => $this->totalGrossYearIncome,
            'totalAssetIncome' => $this->totalAssetIncome,
            'totalDebt' => $this->totalDebt,
            'bankAccountNumber' => $this->bankAccountNumber,
            'bankAccountName' => $this->bankAccountName,
            'identificationDocumentType' => $this->identificationDocumentType?->value,
            'identificationDocumentNumber' => $this->identificationDocumentNumber,
            'fromEERCountry' => $this->fromEERCountry?->value,
            'incomeSavingAccount' => $this->incomeSavingAccount?->value,
            'incomeEffectenDepot' => $this->incomeEffectenDepot?->value,
            'kids' => $this->kids?->value,
            'hasCurrentLease' => $this->hasCurrentLease?->value,
            'hasDuoLoan' => $this->hasDuoLoan?->value,
            'aowStatus' => $this->aowStatus?->value,
        ]);
    }
}
