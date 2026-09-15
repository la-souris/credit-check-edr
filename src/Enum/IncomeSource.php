<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * EDR's income and financial-position vocabulary, on the wire in a mix of Dutch and English.
 *
 * The SDK does not model income, so nothing maps onto these automatically — they are here for
 * callers driving the wire models directly.
 */
enum IncomeSource: string
{
    case Unknown = 'Onbekend';
    case EmploymentPermanent = 'LoondienstOnbepaald';
    case EmploymentTemporary = 'LoondienstTijdelijk';
    case TemporaryAgencyWorker = 'Uitzendkracht';
    case SelfEmployedIncomeTax = 'IBOndernemer';
    case DirectorMajorShareholder = 'DirecteurGrootAandeelhouder';
    case Annuity = 'Lijfrente';
    case Pension = 'Pensioen';
    case SurvivorsPension = 'NabestaandenPensioen';
    case StatePension = 'AOW';
    case Benefit = 'Uitkering';
    case NoIncome = 'NoIncome';
    case SavingsAccountBalance = 'Saldospaarrekening';
    case HousingCosts = 'HousingCosts';
    case Alimony = 'Alimony';
    case MortgageBalance = 'Saldohypothecaire';
    case NegativeBankBalance = 'Negatifsaldobankaccount';
    case SecuritiesDepotBalance = 'Saldoeffectendepot';
    case InvestmentAccountBalance = 'Saldobeleggingsrekening';
    case BankAccountBalance = 'Saldobankrekening';
    case OwnHomeSalePrice = 'Verkoopprijseigenwoning';
    case StudentLoanCosts = 'DuoCosts';
    case SelfEmployed = 'Zelfstandig';
    case BenefitUwv = 'UitkeringUWV';
    case BenefitNonUwv = 'UitkeringNietUWV';
    case RentalIncome = 'InkomenUitVerhuur';
    case Assets = 'Vermogen';
    case ChildAlimony = 'KidsAlimony';
    case MedicalExpenses = 'MedicalExpenses';
    case EnergyCosts = 'EnergyCosts';
    case AdditionalMortgage = 'AdditionalMortgage';
    case ChildcareCosts = 'ChildcareCosts';
    case BkrOpenDebt = 'BkrCkiOpenDebt';
    case BkrOpenOtherAccountDebt = 'BkrCkiOpenOaDebt';
    case Rental = 'Rental';
    case Mortgage = 'Mortgage';
    case Gambling = 'Gambling';
    case PaymentCollectionAgency = 'PaymentCollectionAgency';
    case PaymentBailiff = 'Paymentbailiff';
    case OtherPaymentToCompanies = 'OtherPaymentToCompanies';
    case Other = 'Other';
    case OtherNonIncome = 'OtherNonIncome';
}
