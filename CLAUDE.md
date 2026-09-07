# CLAUDE.md

Guidance for Claude Code in this repo. This file covers what rarely changes; deep ValueObject conventions live in `src/V2/ValueObjects/CLAUDE.md` — read it before touching a payload type.

## What this is

`bradsearch/search-sync-sdk` is a pure-PHP (>= 8.4), zero-runtime-dependency client library for the Brad Search synchronization/admin HTTP API. It never talks to the search backend directly — only to that HTTP API. Every payload it emits is a contract: the server's OpenAPI spec defines the shape, this repo's fixtures assert it byte-for-byte, and every consumer (a Laravel application, plus Shopify/Magento sync jobs) depends on it not drifting.

**V1 is deprecated.** `src/SynchronizationApiSdk.php` and its array/`DataValidator` payload style are legacy — kept alive only for customers not yet migrated, never extended. All new work targets V2.

## V2 architecture

- **Facade**: `src/SyncV2Sdk.php`.
- **Config**: `src/Config/SyncConfigV2.php` — `appId` (must be a UUID), `apiUrl`, `token`, optional `targetIndex`, `timeout` (30s), `connectTimeout` (10s), `retryPolicy`.
- **Transport**: `src/Client/HttpClient.php` builds an `HttpRequest`, hands it to a `Client\Transport\Transport` (default `CurlTransport`), and retries idempotent calls per `Client\RetryPolicy` (connection failures, 5xx, 429; equal-jitter exponential backoff). Read timeouts are deliberately not retried (the engine may still be processing the request); `TransportException::$connectionFailed` is the switch. POST is non-idempotent unless the facade passes `idempotent: true` — only bulk-operations, V1 sync/delete-products and normalize do. PUT is idempotent unless the facade passes `idempotent: false` — the V1 create-index PUT does, because brad-search answers 409 on a repeat. Never flag a configuration POST idempotent. `TransportException extends ApiException` with status 0 for "no response at all". Tests inject a fake `Transport` and `Sleeper` (see `tests/Client/Support/`).
- **Endpoints**: `/api/v2/applications/{appId}/...`.
- **Payloads**: strict immutable readonly ValueObjects in `src/V2/ValueObjects/` (BulkOperations, Index, Normalize, Product, Response, Search, SearchSettings, Synonym, Common), each with constructor validation, a builder, and a `jsonSerialize()` verified against a fixture. See `src/V2/ValueObjects/CLAUDE.md` for the conventions.
- **Adapters**: `PrestaShopAdapterV2`, `MagentoAdapterV2` (GraphQL-fed via `src/Magento/`), `ShopifyAdapter` — transform platform product data into V2 payloads.
- **Admin**: `src/AdminSdk.php` + `src/Client/AdminHttpClient.php` for `/api/v2/admin/indices` (raw physical index list/delete).

The V2 design contract lives in `tasks/prd-v2-valueobjects.md` — read it before changing ValueObject conventions (immutability, `with*()` methods, builders, exact API alignment).

## OpenAPI golden-fixture parity (the centerpiece discipline)

`tests/fixtures/openapi-examples/*.json` are not sample data — they ARE the contract test:

```
tests/fixtures/openapi-examples/
├── index-create-darbo-drabuziai.json
├── bulk-operations-darbo-drabuziai.json
├── configuration-advanced.json
├── search-configuration-request.json
├── search-settings-full.json
└── synonyms-ecommerce-en.json
```

Each file is copied verbatim from an example payload in the server's OpenAPI spec. `tests/V2/ApiPayloadVerificationTest.php` builds the same payload through the V2 ValueObjects/builders and asserts `jsonSerialize()` equals the decoded fixture — exact structural alignment, not "close enough." `tests/V2/DarboDrabuziaiWorkflowTest.php` chains the fixtures into a full end-to-end simulation (create index → configure → bulk-sync → create new version → sync → activate → verify → cleanup, plus a rollback scenario).

**If you change a V2 payload shape**, do this in lockstep, in one PR (after the server-side API change lands first):
1. Confirm the field/shape exists in the server's OpenAPI spec first — never invent a shape SDK-side.
2. Update the ValueObject in `src/V2/ValueObjects/<area>/`.
3. Update the matching builder and `with*()` methods.
4. Update the affected fixture — copied from the server's OpenAPI spec, never hand-authored from memory.
5. Update `ApiPayloadVerificationTest` (and `DarboDrabuziaiWorkflowTest` if the index-create/bulk-operations shape moved).
6. Decide explicitly whether the deprecated V1 side also needs the change (it usually doesn't).
7. Quality-gate triple green (below).

**Failure smell**: if a fixture test fails, do not "fix" it by editing the fixture to match your output. The fixture mirrors the API spec. Either copy the spec's new example verbatim, or fix your ValueObject — the fixture is never adjusted just to silence a test.

## Locale-suffix contract

Documented in `src/Adapters/README.md` ("Locale Handling"):

1. The first locale in an adapter's constructor array is the default locale.
2. Default-locale fields are unsuffixed: `name`, `description`.
3. Every other locale gets a suffixed field: `name_lt-LT`, `description_en-US`.
4. Fallback: if a product is missing the default locale's value, adapters fall back to the first available locale.

Enforced by `src/V2/ValueObjects/Common/LocalizedField.php`, which builds `<baseName>_<locale>` and validates the locale against `^[a-z]{2}(-[A-Z]{2})?$` (the region part is optional — `lt` is as valid as `lt-LT`), throwing `InvalidLocaleException` otherwise.

Getting this wrong does not error — it silently breaks search relevance in one language (fields land under the wrong name; the backend's per-language analysis never sees them). Treat any locale-touching diff as high-risk; cover both the unsuffixed default and the suffixed path with tests.

## targetIndex / alias semantics

The API exposes versioned physical indices behind an alias named after the appId. `SyncConfigV2->targetIndex` defaults to `null`, so bulk ops normally hit the alias (the LIVE index). During a zero-downtime reindex, construct a second `SyncConfigV2` with `targetIndex` set to the new versioned index so bulk-loading targets the inactive version while search keeps serving the old one; only `activateIndexVersion()` flips traffic. If a sync appears to do nothing, check whether it wrote to a non-active version via `getIndexInfo()`. `AdminSdk` lists raw physical indices, not aliases.

## Price correctness (recurring bug class)

- **Shopify money math is bcmath-only, mandatorily**: `ShopifyAdapter` refuses to construct without `bccomp` and compares prices with `bccomp(..., 2)`. Never replace bcmath comparisons with float `>`/`==`.
- bcmath is Shopify-only — `PrestaShopAdapterV2`/`MagentoAdapterV2` use native numerics with explicit zero-price guards. This asymmetry is historical, not principled; keep zero-price guards intact if you touch non-Shopify price code.
- Zero/empty prices are legitimate inputs from every platform — treat as "no discount", never divide by them.

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

`laravel/pint` is in require-dev but NOT wired into CI — phpcs is the authority. No Makefile, no docker-compose, no `.env`; tests are fully offline (HTTP is mocked at the facade level, or scripted through `tests/Client/Support/FakeTransport.php` at the transport level).

### Install
```bash
composer install
composer update
```

## Who consumes this SDK

- The primary consumer is a Laravel application, resolving `bradsearch/search-sync-sdk` from packagist.org (no `repositories` block). Local dev uses a composer path-repository symlink to your checkout.
- A separate PrestaShop module does NOT depend on this SDK — it targets an older PHP baseline and owns its own product transformation.
- Releases are git tags (`vMAJOR.MINOR.PATCH`); there is no publish workflow in this repo.

## Dependencies

- **PHP >= 8.4** — readonly properties, enums, constructor property promotion.
- **ext-json** — JSON encoding/decoding.
- **ext-curl** — HTTP client.
- **ext-bcmath** — required for Shopify decimal-safe price comparisons (`ShopifyAdapter`).
- **PHPUnit 11**, **PHPStan** (level 4), **PHP CodeSniffer** (PSR-12) — dev-only, see quality-gate triple above.
