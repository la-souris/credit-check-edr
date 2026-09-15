# Credit Check — EDR (EDR Group) provider

EDR implementation of the `la-souris/credit-check-sdk` `CreditChecker` contract, plus a
low-level `EdrClient` for the EDR Group Lease credit-check REST API.

## What it maps to

| SDK contract call | EDR endpoint |
|---|---|
| `submitCheck()` | `POST /api/v1/Lease/Create` → returns the new `orderId` (used as the SDK reference) |
| `getResult()` | `GET /api/v1/Lease/Get?orderId=` → decision, traffic light, reasons, per-person detail |
| `getChangedChecksSince()` | `GET /api/v1/Lease/GetChangedOrderIdsSinceUTC?since=` |

Authentication is email/password against the identity API
(`POST /api/identity/GenerateTokenByCredentials`); the JWT is kept until it expires, refreshed
with the refresh token when possible, and a 401 triggers one automatic re-auth + retry.

Where the JWT and refresh token are kept is a `TokenStore`. The default,
`InMemoryTokenStore`, holds them for the current process only; pass your own to
`EdrClient::create(..., tokenStore: $store)` to share them across requests and workers — the
Laravel package ships a cache-backed one (see [its README](../laravel#token-cache)).

## Environments

`Environment` selects the API base URL; pick it when constructing the client.

| Case | Use |
|---|---|
| `Environment::Uat` | Testing / integration against EDR's acceptance environment. |
| `Environment::Production` | Live checks. |

## Usage

```php
use LaSouris\CreditCheck\Edr\CreditCheck\EdrCreditChecker;
use LaSouris\CreditCheck\Edr\Environment;
use LaSouris\CreditCheck\Edr\EdrClient;

$client = EdrClient::create(
    Environment::Uat,          // or Environment::Production
    $email,
    $password,
    $httpClient,               // any PSR-18 client
    $requestFactory,           // any PSR-17 request factory
    $streamFactory,            // any PSR-17 stream factory
);

$checker = new EdrCreditChecker($client);

$submitted = $checker->submitCheck($request);   // ->reference is the EDR order id
$response  = $checker->getResult($submitted->reference);

$response;              // Sdk\Response\GetCreditCheckResponse — decision, status, etc. read directly
$response->raw;         // EDR's decoded Lease/Get body, untouched
```

Each call answers with its own flattened `Sdk\Response\*` class, no shared envelope. For EDR
that matters more than most: `raw` carries the decoded Lease body, so the many per-person fields
the SDK does not model stay reachable without a second call through `EdrClient`.

Reach and capabilities are declared on the class:

```php
#[Provider(
    name: 'edr',
    countries: ['NL'],
    currencies: ['EUR'],
    capabilities: [Capability::CREATE_CHECK, Capability::GET_RESULT, Capability::LIST_CHANGED],
)]
final class EdrCreditChecker implements CreditChecker
```

Out-of-reach input is rejected with a `ProviderValidationException` before any network call, by
the `GuardsProviderReach` trait reading that same attribute. `ProviderCapabilities::of('...')`
reads it without constructing the provider.

## How SDK objects become EDR payloads

Everything EDR-shaped lives in this package. The SDK stays vendor-neutral; `EdrPayloadMapper` is
the only class that knows both vocabularies.

| Layer | What it holds |
|---|---|
| `Edr\Enum\*` | EDR's exact wire strings as backed enums — `IncomeSource::EmploymentPermanent` is `'LoondienstOnbepaald'`, `OccupantCount::MoreAsFour` is `'MoreAsFour'`, `TransferDate::BetweenTwoAndSixMonths` is `'between2monthsand6months'`. |
| `Edr\Model\Lease\*` | The `Lease/Create` request bodies, typed with those enums. |
| `CreditCheck\EdrPayloadMapper` | SDK domain → wire models on the way out, EDR order status → `Sdk\Response\GetCreditCheckResponse` on the way back. |

An SDK `Applicant` is two `Applicant\Person`s — `person` and `partner` — and EDR nests the
partner inside the person, so `EdrPayloadMapper::person()` maps both halves. EDR's partner
fragment has no address of its own (the household is the person's address), so the partner's
SDK `Address` is deliberately not sent.

Identity, contact and address live on `Person`; three reshapes happen at that level:

```php
// one houseNumber string -> EDR's numeric field plus a separate suffix
'12A'  ->  houseNumber: 12, houseNumberAddOn: 'A'

// a plain occupant count -> EDR's bucketed enum
OccupantCount::fromCount(5);                  // 'MoreAsFour'

// a parsed libphonenumber\PhoneNumber -> EDR's plain-string field
PhoneNumberUtil::format($number, E164);       // '+31612345678'
```

Two SDK enums have a direct EDR counterpart:

```php
Gender::fromSdk(Sdk\Gender::Other);            // -> 'Unknown' (EDR records only M/F)
SalesChannel::fromSdk(Sdk\SalesChannel::Internet);  // -> 'Internet'
```

### Everything else EDR accepts

EDR takes far more per person than the SDK models — income source, the four affordability
figures, profession, living arrangement, marital status, divorce/alimony, AOW (state-pension)
status, the household yes/no flags (`kids`, `hasDuoLoan`, `fromEERCountry`, …), bank account,
identity document and house-transfer date. These are **optional constructor arguments on the
wire models**, not SDK concepts. Left unset they are omitted from the request
body entirely rather than sent as `null`. To supply them, build the wire models yourself and hand
them to `EdrClient::createOrder()`:

```php
use LaSouris\CreditCheck\Edr\Enum\{AowStatus, IncomeSource, MaritalStatus, Profession, YesNo};

new CreateOrderPersonModel(
    surname: 'de Vries',
    initials: 'J.',
    mobilenumber: '+31612345678',
    email: 'jan@example.com',
    dateofbirth: '1990-05-01',
    gender: Gender::Male,
    address: $address,
    firstname: 'Jan',
    sourceofincome: IncomeSource::EmploymentPermanent,
    profession: Profession::Nurses,
    totalNetMonthlyIncome: 3200.0,
    totalGrossYearIncome: 48000.0,
    maritalStatus: MaritalStatus::Married,
    aowStatus: AowStatus::NotApplicable,
    hasDuoLoan: YesNo::No,
    bankAccountNumber: 'NL91ABNA0417164300',
);
```

### Reading a result

`EdrPayloadMapper::result()` folds EDR's separate sanctions, PEP and credit-registry ("CRS")
screening lights into the worst of the three per person, attaches the affordability ("ilt")
figure for that person, and keeps the full, untouched EDR response for auditing. It does this by
constructing `Sdk\Response\GetCreditCheckResponse`/`Sdk\Response\ApplicantResult`/
`Sdk\Response\Decision`/`Sdk\Response\RejectionReason` — see the [SDK README](../sdk#reading-a-result)
for what each field means.

## Errors

The low-level `EdrClient` raises its own `Edr\Exception\*` hierarchy off the HTTP status of a
failed call, and transport failures are wrapped as a bare `EdrException`:

| HTTP | `EdrClient` throws |
|---|---|
| 401 / 403 | `AuthenticationException` |
| 404 | `NotFoundException` |
| 400 / 422 | `ValidationException` |
| 5xx | `ServerException` |
| other non-2xx | `ApiException` |

Each carries the status, the parsed `errors`, the decoded body and EDR's own error code.
`EdrCreditChecker` surfaces these to callers as the SDK's provider-agnostic
`Sdk\CreditCheck\Exception\Provider*` types, so application code can catch the SDK hierarchy and
stay independent of EDR.

See [`examples/perform_credit_check.php`](examples/perform_credit_check.php) for a runnable
end-to-end example (uses Guzzle + nyholm/psr7).
