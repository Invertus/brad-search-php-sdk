# CLAUDE.md

This file provides guidance to Claude Code when working with code in this repository (`bradsearch/search-sync-sdk`). Facts below verified against the working tree as of 2026-07-03.

## What this is

Pure-PHP (>= 8.4), zero runtime composer deps, client library for the Brad Search synchronization/admin HTTP API. Every payload it emits is an API contract: the server's OpenAPI v2 spec defines the shape, this repo's fixtures assert it byte-for-byte, and downstream consumers depend on it.

## V1 / V2 — two generations coexist

**V2 is the current, active surface. Center new work here.** V1 is legacy and stays alive only while customers remain on it — neither generation may be deleted.

| | V1 (legacy) | V2 (current) |
|---|---|---|
| Facade | `src/SynchronizationApiSdk.php` | `src/SyncV2Sdk.php` |
| Config | `src/Config/SyncConfig.php` | `src/Config/SyncConfigV2.php` (appId **must be a UUID**; apiUrl, token, optional `targetIndex`) |
| Endpoints | `/api/v1/sync/...` | `/api/v2/applications/{appId}/...` |
| Payload style | raw arrays + `src/Validators/DataValidator.php` | **strict immutable readonly ValueObjects** in `src/V2/ValueObjects/` (BulkOperations, Index, Normalize, Product, Response, Search, SearchSettings, Synonym, Common) with constructor validation, builders, and `jsonSerialize()` asserted against fixtures |
| Adapters | `PrestaShopAdapter`, `MagentoAdapter` | `PrestaShopAdapterV2`, `MagentoAdapterV2`, `ShopifyAdapter` (V2-only) |

Also: `src/AdminSdk.php` + `src/Client/AdminHttpClient.php` for `/api/v2/admin/indices` (raw physical index list/delete).

**A field/feature that both generations expose must land twice** — once in the V1 array path, once in the V2 ValueObject path. Confirm with the owner before duplicating; some features (synonyms, search settings, alias versioning) are V2-only. Which generation a given customer uses is decided server-side by the consuming application, not here.

The V2 design contract lives in `tasks/prd-v2-valueobjects.md` — read it before changing VO conventions (immutability, `with*()` methods, builders, exact OpenAPI alignment).

## OpenAPI golden-fixture parity (the centerpiece discipline)

`tests/fixtures/openapi-examples/*.json` are not sample data — they ARE the cross-repo contract test:

```
tests/fixtures/openapi-examples/
├── index-create-darbo-drabuziai.json
├── bulk-operations-darbo-drabuziai.json
├── configuration-advanced.json
├── search-configuration-request.json
├── search-settings-full.json
└── synonyms-ecommerce-en.json
```

Each file is copied verbatim from an example payload in the server's OpenAPI v2 spec. `tests/V2/ApiPayloadVerificationTest.php` builds the same payload through the V2 ValueObjects/builders and asserts `jsonSerialize()` equals the decoded fixture — exact byte-level/structural alignment, not "close enough." `tests/V2/DarboDrabuziaiWorkflowTest.php` chains the fixtures into a full end-to-end simulation (create index v1 → configure → bulk-sync → create index v2 → sync → activate v2 → verify → cleanup, plus a rollback scenario).

**If you change a V2 payload shape**, do this in lockstep, in one PR (after the server-side API change lands first):
1. Confirm the field/shape exists in the server's OpenAPI v2 spec first — never invent a shape SDK-side.
2. Update the ValueObject in `src/V2/ValueObjects/<area>/`.
3. Update the matching builder and `with*()` methods.
4. Update the affected fixture — copied from the server's OpenAPI spec, never hand-authored from memory.
5. Update `ApiPayloadVerificationTest` (and `DarboDrabuziaiWorkflowTest` if index-create/bulk-operations shape moved).
6. Decide explicitly whether the V1 side also needs the change.
7. Quality-gate triple green (below).

**Failure smell**: if a fixture test fails, do not "fix" it by editing the fixture to match your output. The fixture mirrors the API spec. Either copy the spec's new example verbatim, or fix your VO — the fixture is never adjusted just to silence a test.

## Locale-suffix contract

Documented in `src/Adapters/README.md` ("Locale Handling"):

1. The first locale in an adapter's constructor array is the default locale.
2. Default-locale fields are unsuffixed: `name`, `description`.
3. Every other locale gets a suffixed field: `name_lt-LT`, `description_en-US`.
4. Fallback: if a product is missing the default locale's value, adapters fall back to the first available locale.

Enforced by `src/V2/ValueObjects/Common/LocalizedField.php`, which builds `<baseName>_<locale>` and validates the locale against `^[a-z]{2}(-[A-Z]{2})?$` (region part is optional — `lt` is as valid as `lt-LT`), throwing `InvalidLocaleException` otherwise.

Getting this wrong does not error — it silently breaks search relevance in one language (fields land under the wrong name; the engine's per-language analyzers never see them). Treat any locale-touching diff as high-risk; cover both the unsuffixed default and the suffixed path with tests.

**Known debt**: V1's embeddable-fields builder hardcodes the locale pair — `src/SynchronizationApiSdk.php:348-349` (`$locales = ['en-US', 'lt-LT'];`). This blocks V1 customers outside that pair. It is a known gap; fixing it needs its own ticket (changes V1 index mappings) — do not fix it as a drive-by.

## Development commands

### Testing
```bash
vendor/bin/phpunit --testdox
```
PHPUnit 11. `phpunit.xml` sets `failOnRisky` + `failOnWarning` — warnings fail the build.

### Quality-gate triple — all three required, every PR

This is exactly what CI runs (`.github/workflows/tests.yml`: a `tests` job and a `code-quality` job, PHP 8.4, extensions json/curl/bcmath):

```bash
vendor/bin/phpunit --testdox     # expect all green
vendor/bin/phpstan analyse       # level 4, src/ only (phpstan.neon); expect "[OK] No errors"
vendor/bin/phpcs src tests       # PSR-12 (phpcs.xml); expect empty output / exit 0
```

`laravel/pint` is in require-dev but NOT wired into CI — phpcs is the authority. No Makefile, no docker-compose, no `.env`; tests are fully offline (HTTP is mocked).

### Install
```bash
composer install
composer update
```

## Code architecture

### Key components
- **`SynchronizationApiSdk`** (V1) / **`SyncV2Sdk`** (V2) — main facades.
- **Field Configuration** (`src/Models/`) — `FieldConfig`, `FieldConfigBuilder`, `FieldType` enum (V1).
- **ValueObjects** (`src/V2/ValueObjects/`) — V2 payload types with constructor validation and builders.
- **Validation** (`src/Validators/DataValidator.php`) — V1 client-side validation before any API call.
- **HTTP** (`src/Client/`) — `HttpClient` (V1), `AdminHttpClient` (admin API).
- **Adapters** (`src/Adapters/`) — `PrestaShopAdapter`/`PrestaShopAdapterV2`, `MagentoAdapter`/`MagentoAdapterV2` (GraphQL-fed via `src/Magento/`), `ShopifyAdapter` (V2-only).

### targetIndex / alias semantics

The API exposes versioned physical indices behind an alias named after the appId. `SyncConfigV2->targetIndex` defaults to `null`, so bulk ops normally hit the alias (the LIVE index). During a zero-downtime reindex, construct a second `SyncConfigV2` with `targetIndex` set to the new versioned index so bulk-loading targets the inactive version while search keeps serving the old one; only `activateIndexVersion()` flips traffic. If a sync appears to do nothing, check whether it wrote to a non-active version via `getIndexInfo()`. `AdminSdk` lists raw physical indices, not aliases.

### Price correctness (recurring bug class)

- **Shopify money math is bcmath-only, mandatorily**: `ShopifyAdapter` refuses to construct without `bccomp` and compares prices with `bccomp(..., 2)`. Never replace bcmath comparisons with float `>`/`==`.
- bcmath is Shopify-only — `PrestaShopAdapterV2`/`MagentoAdapterV2` use native numerics with explicit zero-price guards. This asymmetry is historical, not principled; keep zero-price guards intact if you touch non-Shopify price code.
- Zero/empty prices are legitimate inputs from every platform — treat as "no discount", never divide by them.

### Who consumes this SDK

- The primary consumer is a **Laravel application**, resolving `bradsearch/search-sync-sdk` from packagist.org (no `repositories` block). Local dev uses a composer path-repository symlink to your checkout.
- The **PrestaShop module** does NOT depend on this SDK — it targets PHP >= 7.1 and owns its own product transformation.
- Releases are git tags (`vMAJOR.MINOR.PATCH`); there is no publish workflow in this repo.

## Dependencies

- **PHP >= 8.4** — readonly properties, enums, constructor property promotion.
- **ext-json** — JSON encoding/decoding.
- **ext-curl** — HTTP client.
- **ext-bcmath** — required for Shopify decimal-safe price comparisons (`ShopifyAdapter`).
- **PHPUnit 11**, **PHPStan** (level 4), **PHP CodeSniffer** (PSR-12) — dev-only, see quality-gate triple above.
