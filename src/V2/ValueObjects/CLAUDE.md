# V2 ValueObjects

The V2 payload layer. Every class here represents one shape from the server's OpenAPI spec — see the root `CLAUDE.md` for the fixture-parity discipline these exist to satisfy.

## Conventions (design contract: `tasks/prd-v2-valueobjects.md`)

- All ValueObjects extend `ValueObject` (this directory) and are declared `readonly`: immutable, constructed once, never mutated in place.
- `jsonSerialize()` (required by `ValueObject`) must return the exact API-compatible array — key names, nesting, and presence/omission of optional keys must match the OpenAPI example byte-for-byte. `toArray()` is a named alias for the same thing.
- Validate in the constructor, not in a separate step — an invalid ValueObject should be impossible to construct.
- Prefer a `with*()` method over a public setter for any change to an existing instance (see `LocalizedField::withLocale()` for the pattern) — it returns a new instance, the original is untouched.
- Non-trivial request types get a companion `*Builder` (e.g. `IndexCreateRequestBuilder`, `ProductBuilder`, `QueryConfigurationRequestBuilder`, `SearchSettingsRequestBuilder`, `FieldDefinitionBuilder`, `SearchFieldConfigBuilder`) so callers can assemble a payload incrementally instead of a single large constructor call.
- Response-side types (`Response/`) are parsed the other direction: a `fromArray()` (or equivalent named constructor) instead of `jsonSerialize()`. Harden these against malformed/partial API responses — server responses are not as tightly controlled as the requests we build ourselves, and a response type should degrade gracefully rather than throw on an unexpected shape.

## Directory map

| Directory | Covers |
|---|---|
| `BulkOperations/` | Index/update/delete product payloads sent to the bulk-sync endpoint |
| `Index/` | Index creation: field definitions, field types, variant attributes, search analysis |
| `Search/` | Query configuration (boost algorithm, match mode, multi-word operator, field config) |
| `SearchSettings/` | The larger per-application search-behavior document: query/scoring/response config, highlighting, multi-match, function-score, variant enrichment |
| `Synonym/` | Synonym configuration |
| `Normalize/` | Field-value normalization requests |
| `Product/` | Shared product-level value types (pricing, image URLs) |
| `Response/` | Parsed API responses for all of the above |
| `Common/` | Cross-cutting helpers — currently `LocalizedField` (see root `CLAUDE.md`'s locale-suffix contract) |

## Known trap: dual-shape parsing

At least one config type (synonym groups) can arrive from the API as either a comma-separated string or an array of strings, and both forms must normalize to the same internal representation. When a ValueObject accepts more than one input shape for the same concept, keep the normalization in one shared place (see `Synonym/NormalizesSynonymGroups.php`) rather than duplicating the branch logic — a fix applied to only one branch is a recurring source of bugs here.

## Adding or changing a ValueObject

Don't do this in isolation — follow the full lockstep checklist in the root `CLAUDE.md` (OpenAPI spec first, then ValueObject, builder, fixture, tests). A ValueObject change that isn't backed by a fixture is not verified, no matter how correct it looks.
