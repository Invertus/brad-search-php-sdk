# PRD: Strict ValueObjects for Brad Search PHP SDK v2 Endpoints

## Overview
Refactor the Brad Search PHP SDK v2 endpoint implementation to use strict, immutable ValueObjects instead of raw arrays. This ensures type safety, validation at construction time, and precise alignment with the API documentation. The implementation must match the OpenAPI v2 specification exactly, verified through comprehensive unit tests comparing SDK output against documented examples.

## Goals
- Replace all raw array usage in SyncV2Sdk with typed, immutable ValueObjects
- Implement constructor validation that throws exceptions for invalid data
- Use Builder pattern for complex nested structures
- Provide `with*()` methods for immutable modifications
- Create `LocalizedField` helper for locale-suffixed field names
- Ensure SDK-generated JSON matches OpenAPI documentation examples exactly
- Achieve comprehensive test coverage with verification against documented payloads

## Quality Gates

These commands must pass for every user story:
- `vendor/bin/phpunit` - All unit tests pass
- `vendor/bin/phpstan analyse` - Static analysis passes
- `vendor/bin/phpcs src tests` - Code style compliance

Additional verification for each user story:
- Unit tests MUST compare SDK-generated JSON against OpenAPI example payloads
- Each ValueObject MUST be tested for correct serialization
- Each Builder MUST be tested for fluent API and final object construction

## User Stories

### US-001: Create base ValueObject infrastructure
**Description:** As a developer, I want a base infrastructure for immutable ValueObjects so that all domain objects follow consistent patterns.

**Acceptance Criteria:**
- [ ] Create `src/V2/ValueObjects/` directory structure
- [ ] Create base `JsonSerializable` interface implementation pattern
- [ ] Create `InvalidArgumentException` subclasses for validation errors (e.g., `InvalidFieldTypeException`, `InvalidLocaleException`)
- [ ] All ValueObjects use `readonly` properties
- [ ] All ValueObjects implement `JsonSerializable` returning API-compatible structure

---

### US-002: Create LocalizedField helper
**Description:** As a developer, I want a LocalizedField helper so that I can easily generate locale-suffixed field names (e.g., `name_lt-LT`).

**Acceptance Criteria:**
- [ ] Create `LocalizedField` class in `src/V2/ValueObjects/`
- [ ] Constructor accepts base field name and locale (e.g., `new LocalizedField('name', 'lt-LT')`)
- [ ] Validates locale format matches pattern `^[a-z]{2}-[A-Z]{2}$`
- [ ] Provides `toString()` method returning suffixed name (e.g., `name_lt-LT`)
- [ ] Provides `getBaseName()` and `getLocale()` accessors
- [ ] Supports `withLocale()` method for immutable locale changes
- [ ] Unit tests verify locale validation and string generation

---

### US-003: Create FieldDefinition ValueObject and Builder
**Description:** As a developer, I want FieldDefinition ValueObjects so that index field mappings are type-safe and validated.

**Acceptance Criteria:**
- [ ] Create `FieldType` enum with values: `text`, `keyword`, `double`, `integer`, `boolean`, `image_url`, `variants`
- [ ] Create `FieldDefinition` immutable ValueObject with properties: `name`, `type`
- [ ] Create `VariantAttribute` ValueObject with: `id`, `type`, `locale_aware`
- [ ] Create `FieldDefinitionBuilder` with fluent API:
  - `name(string)`, `type(FieldType)`, `addAttribute(VariantAttribute)`
  - `build()` returns immutable `FieldDefinition`
- [ ] Constructor validation: name required, type must be valid enum
- [ ] `jsonSerialize()` outputs exact structure matching OpenAPI `FieldDefinition` schema
- [ ] Unit test compares output against OpenAPI example for Darbo drabuziai fields

---

### US-004: Create IndexCreateRequest ValueObject and Builder
**Description:** As a developer, I want IndexCreateRequest ValueObject so that index creation requests are validated and match API schema.

**Acceptance Criteria:**
- [ ] Create `IndexCreateRequest` immutable ValueObject with: `locales`, `fields`
- [ ] Create `IndexCreateRequestBuilder` with fluent API:
  - `addLocale(string)`, `addField(FieldDefinition)`, `build()`
- [ ] Validates locales match pattern `^[a-z]{2}-[A-Z]{2}$`
- [ ] Validates at least one locale and one field required
- [ ] `jsonSerialize()` outputs exact structure matching `IndexCreateRequestV2App` schema
- [ ] Unit test compares output against OpenAPI "Darbo drabuziai client" example
- [ ] Update `SyncV2Sdk::createIndex()` to accept `IndexCreateRequest`

---

### US-005: Create SearchFieldConfig ValueObject and Builder
**Description:** As a developer, I want SearchFieldConfig ValueObject so that search field configurations are type-safe.

**Acceptance Criteria:**
- [ ] Create `MatchMode` enum with values: `exact`, `fuzzy`, `phrase_prefix`
- [ ] Create `SearchFieldConfig` immutable ValueObject with: `field`, `position`, `boost_multiplier`, `match_mode`
- [ ] Create `SearchFieldConfigBuilder` with fluent API and `with*()` methods
- [ ] Validates: position >= 1, boost_multiplier between 0.01 and 100.0
- [ ] `match_mode` defaults to `fuzzy` if not specified
- [ ] `jsonSerialize()` outputs structure matching `SearchFieldConfigV2` schema
- [ ] Unit tests verify validation boundaries and JSON output

---

### US-006: Create FuzzyMatchingConfig ValueObject
**Description:** As a developer, I want FuzzyMatchingConfig ValueObject so that fuzzy matching settings are validated.

**Acceptance Criteria:**
- [ ] Create `FuzzyMode` enum with values: `auto`, `fixed`
- [ ] Create `FuzzyMatchingConfig` immutable ValueObject with: `enabled`, `mode`, `min_similarity`
- [ ] Validates: min_similarity between 0 and 2
- [ ] Provides `with*()` methods: `withEnabled()`, `withMode()`, `withMinSimilarity()`
- [ ] Defaults: `enabled=true`, `mode=auto`, `min_similarity=2`
- [ ] `jsonSerialize()` outputs structure matching `FuzzyMatchingConfig` schema
- [ ] Unit tests verify defaults and validation

---

### US-007: Create PopularityBoostConfig ValueObject
**Description:** As a developer, I want PopularityBoostConfig ValueObject so that popularity boost settings are validated.

**Acceptance Criteria:**
- [ ] Create `BoostAlgorithm` enum with values: `logarithmic`, `linear`, `square_root`
- [ ] Create `PopularityBoostConfig` immutable ValueObject with: `enabled`, `field`, `algorithm`, `max_boost`
- [ ] Validates: max_boost between 1.0 and 10.0
- [ ] Provides `with*()` methods for all properties
- [ ] Defaults: `algorithm=logarithmic`, `max_boost=2.0`
- [ ] `jsonSerialize()` outputs structure matching `PopularityBoostConfig` schema
- [ ] Unit tests verify validation and defaults

---

### US-008: Create QueryConfigurationRequest ValueObject and Builder
**Description:** As a developer, I want QueryConfigurationRequest ValueObject so that query configurations are complete and validated.

**Acceptance Criteria:**
- [ ] Create `MultiWordOperator` enum with values: `and`, `or`
- [ ] Create `QueryConfigurationRequest` immutable ValueObject with all properties from schema
- [ ] Create `QueryConfigurationRequestBuilder` with fluent API:
  - `addSearchField(SearchFieldConfig)`, `fuzzyMatching(FuzzyMatchingConfig)`
  - `popularityBoost(PopularityBoostConfig)`, `multiWordOperator()`, `minScore()`, etc.
- [ ] Validates: at least one search_field required, min_score between 0.0 and 1.0
- [ ] `jsonSerialize()` outputs structure matching `QueryConfigurationRequest` schema
- [ ] Unit test compares output against OpenAPI "advanced" configuration example
- [ ] Update `SyncV2Sdk::setConfiguration()` to accept `QueryConfigurationRequest`

---

### US-009: Create SynonymConfiguration ValueObject
**Description:** As a developer, I want SynonymConfiguration ValueObject so that synonym settings are validated.

**Acceptance Criteria:**
- [ ] Create `SynonymConfiguration` immutable ValueObject with: `language`, `synonyms`
- [ ] Validates: language matches pattern `^[a-z]{2}$` (ISO 639-1)
- [ ] Validates: synonyms array not empty, each entry is non-empty string
- [ ] Provides `withLanguage()`, `withSynonyms()`, `addSynonym()` methods
- [ ] `jsonSerialize()` outputs structure matching `SynonymConfiguration` schema
- [ ] Unit test compares output against OpenAPI "ecommerce-en" example
- [ ] Update `SyncV2Sdk::setSynonyms()` to accept `SynonymConfiguration`

---

### US-010: Create BulkOperation ValueObjects
**Description:** As a developer, I want BulkOperation ValueObjects so that bulk sync operations are type-safe and match the documented structure.

**Acceptance Criteria:**
- [ ] Create `BulkOperationType` enum with value: `index_products` (extendable for future types)
- [ ] Create `ProductVariant` immutable ValueObject with: `id`, `sku`, `price`, `basePrice`, `priceTaxExcluded`, `basePriceTaxExcluded`, `productUrl`, `imageUrl`, `attrs`
- [ ] Create `Product` immutable ValueObject with all product fields from example + `variants` collection
- [ ] Create `ProductBuilder` with fluent API for complex product construction
- [ ] Create `IndexProductsPayload` ValueObject containing products array
- [ ] Create `BulkOperation` ValueObject with: `type`, `payload`
- [ ] Create `BulkOperationsRequest` ValueObject containing operations array
- [ ] `jsonSerialize()` outputs exact structure matching the "darbo-drabuziai-indexing" example
- [ ] Unit test compares output against OpenAPI bulk operations example payload
- [ ] Update `SyncV2Sdk::bulkOperations()` to accept `BulkOperationsRequest`

---

### US-011: Create ImageUrl ValueObject
**Description:** As a developer, I want ImageUrl ValueObject so that image URL structures are consistent.

**Acceptance Criteria:**
- [ ] Create `ImageUrl` immutable ValueObject with: `small`, `medium` (optional: `large`, `thumbnail`)
- [ ] Validates URLs are valid format
- [ ] Provides `with*()` methods for each size
- [ ] `jsonSerialize()` outputs object with size keys matching API examples
- [ ] Unit tests verify URL validation and JSON structure

---

### US-012: Create SearchSettingsRequest ValueObject and Builders
**Description:** As a developer, I want SearchSettingsRequest ValueObject so that complex search configurations are type-safe.

**Acceptance Criteria:**
- [ ] Create `SearchBehaviorType` enum: `exact`, `match`, `fuzzy`, `ngram`, `phrase_prefix`, `phrase`
- [ ] Create `SearchBehavior` ValueObject with: `type`, `subfield`, `operator`, `boost`, `fuzziness`, `prefix_length`
- [ ] Create `FieldConfig` ValueObject with: `id`, `field_name`, `locale_suffix`, `search_behaviors`
- [ ] Create `NestedFieldConfig` ValueObject with: `id`, `path`, `locale_suffix`, `score_mode`, `fields`
- [ ] Create `MultiMatchConfig` ValueObject with: `id`, `field_ids`, `type`, `operator`, `boost`
- [ ] Create `SearchConfig` ValueObject containing fields, nested_fields, multi_match_configs
- [ ] Create `FunctionScoreConfig` ValueObject with: `field`, `modifier`, `factor`, `missing`, `boost_mode`, `max_boost`
- [ ] Create `ScoringConfig` ValueObject with: `function_score`, `min_score`
- [ ] Create `ResponseConfig` ValueObject with: `source_fields`, `sortable_fields`
- [ ] Create `SearchSettingsRequest` ValueObject with all nested configs
- [ ] Create `SearchSettingsRequestBuilder` with fluent API for full configuration
- [ ] `jsonSerialize()` outputs structure matching `SearchSettingsRequest` schema
- [ ] Unit test compares output against OpenAPI "full configuration" example
- [ ] Update `SyncV2Sdk::createSearchSettings()` to accept `SearchSettingsRequest`

---

### US-013: Create API Response ValueObjects
**Description:** As a developer, I want response ValueObjects so that API responses can be parsed into typed objects.

**Acceptance Criteria:**
- [ ] Create `IndexCreationResponse` ValueObject: `status`, `physical_index_name`, `alias_name`, `version`, `fields_created`, `message`
- [ ] Create `IndexInfoResponse` ValueObject with nested `IndexVersion` objects
- [ ] Create `VersionActivateResponse` ValueObject
- [ ] Create `QueryConfigurationResponse` ValueObject
- [ ] Create `SynonymResponse` ValueObject
- [ ] Create `BulkOperationsResponse` ValueObject with nested `OperationResult` objects
- [ ] Create `ErrorResponse` ValueObject with: `status`, `error`, `details`
- [ ] All responses have static `fromArray()` factory method for parsing API responses
- [ ] Update SyncV2Sdk methods to return typed response objects instead of arrays
- [ ] Unit tests verify parsing of example responses from OpenAPI docs

---

### US-014: Update SyncV2Sdk to use ValueObjects
**Description:** As a developer, I want SyncV2Sdk updated to use ValueObjects so that the public API is fully typed.

**Acceptance Criteria:**
- [ ] Update `createIndex(IndexCreateRequest $request): IndexCreationResponse`
- [ ] Update `setConfiguration(QueryConfigurationRequest $config): QueryConfigurationResponse`
- [ ] Update `updateConfiguration(QueryConfigurationRequest $config): QueryConfigurationResponse`
- [ ] Update `setSynonyms(SynonymConfiguration $synonyms): SynonymResponse`
- [ ] Update `bulkOperations(BulkOperationsRequest $operations): BulkOperationsResponse`
- [ ] Update `createSearchSettings(SearchSettingsRequest $settings): SettingsResponse`
- [ ] Update `updateSearchSettings(string $appId, SearchSettingsRequest $settings): SettingsResponse`
- [ ] Remove all array-based method signatures (v2 not released, no backward compatibility needed)
- [ ] All existing tests updated to use new ValueObject API
- [ ] PHPDoc updated with proper type hints

---

### US-015: Comprehensive API payload verification tests
**Description:** As a developer, I want verification tests so that SDK output is guaranteed to match API documentation.

**Acceptance Criteria:**
- [ ] Create `tests/V2/ApiPayloadVerificationTest.php`
- [ ] Test: IndexCreateRequest JSON matches "Darbo drabuziai client" example exactly
- [ ] Test: QueryConfigurationRequest JSON matches "advanced" example exactly
- [ ] Test: SynonymConfiguration JSON matches "ecommerce-en" example exactly
- [ ] Test: BulkOperationsRequest JSON matches "darbo-drabuziai-indexing" example exactly
- [ ] Test: SearchSettingsRequest JSON matches "full configuration" example exactly
- [ ] Each test loads expected JSON from fixtures, builds equivalent via SDK, compares
- [ ] Create `tests/fixtures/openapi-examples/` directory with JSON files extracted from OpenAPI
- [ ] Tests fail if any structural difference detected between SDK output and documented examples

---

### US-016: Full workflow simulation test for Darbo Drabuziai client
**Description:** As a developer, I want a full end-to-end workflow simulation test so that I can verify the entire SDK flow works correctly in the order defined by OpenAPI documentation.

**Acceptance Criteria:**
- [ ] Create `tests/V2/DarboDrabuziaiWorkflowTest.php`
- [ ] **Step 1 - Create Index v1:** Simulate `POST /api/v2/applications/{app_id}/index` with Darbo Drabuziai field definitions (id, name_lt-LT, brand_lt-LT, sku, imageUrl, description_lt-LT, categories_lt-LT, price, variants with attrs)
- [ ] **Step 2 - Set Configuration:** Simulate `POST /api/v2/applications/{app_id}/configuration` with Darbo Drabuziai search config (search_fields with boosting, fuzzy_matching, nested variants search)
- [ ] **Step 3 - Sync Initial Data:** Simulate `POST /api/v2/applications/{app_id}/sync/bulk-operations` with index_products operation containing Darbo Drabuziai products with variants
- [ ] **Step 4 - Verify Index Info:** Simulate `GET /api/v2/applications/{app_id}/index/info` returns v1 as active
- [ ] **Step 5 - Create Index v2 (Migration):** Simulate creating new index version for zero-downtime migration
- [ ] **Step 6 - Sync Data to v2:** Simulate bulk operations to populate v2 index with updated/new products
- [ ] **Step 7 - Update Configuration:** Simulate `PUT /api/v2/applications/{app_id}/configuration` with modified search config
- [ ] **Step 8 - Activate v2:** Simulate `POST /api/v2/applications/{app_id}/index/activate` with version 2
- [ ] **Step 9 - Verify Activation:** Assert response shows previous_version=1, new_version=2
- [ ] **Step 10 - Cleanup v1:** Simulate `DELETE /api/v2/applications/{app_id}/index/version/1`
- [ ] Test uses mock HttpClient to capture all requests in sequence
- [ ] Test asserts correct API endpoint order matches OpenAPI workflow documentation
- [ ] Test asserts all request payloads match expected JSON structure
- [ ] Test asserts response parsing works correctly for each step
- [ ] Test covers rollback scenario: activate v1 after v2 issues

## Functional Requirements

- FR-1: All ValueObjects MUST be immutable with `readonly` properties
- FR-2: All ValueObjects MUST implement `JsonSerializable` interface
- FR-3: All ValueObjects MUST validate data in constructor, throwing typed exceptions
- FR-4: All complex ValueObjects MUST have corresponding Builder classes
- FR-5: All ValueObjects MUST provide `with*()` methods for creating modified copies
- FR-6: LocalizedField MUST generate field names matching pattern `{base}_{locale}`
- FR-7: JSON serialization MUST produce snake_case keys matching OpenAPI specification
- FR-8: Enums MUST use string-backed values matching API accepted values
- FR-9: Optional properties MUST be omitted from JSON when null (not serialized as null)
- FR-10: SyncV2Sdk MUST accept ValueObjects and return typed Response objects

## Non-Goals

- Implementing actual API calls or integration tests against live API
- Modifying v1 SDK (SynchronizationApiSdk)
- Creating CLI tools or console commands
- Implementing caching or retry logic
- Supporting PHP versions below 8.4

## Technical Considerations

- Use PHP 8.4 readonly properties and constructor property promotion
- Follow existing codebase patterns from `src/Models/` where applicable
- Enums should be string-backed for JSON serialization compatibility
- Consider using `JsonSerializable` trait for common serialization logic
- Builder pattern should support both single-call and chained construction
- Response parsing should handle missing optional fields gracefully

## Directory Structure

```
src/V2/
├── ValueObjects/
│   ├── Index/
│   │   ├── FieldType.php (enum)
│   │   ├── FieldDefinition.php
│   │   ├── FieldDefinitionBuilder.php
│   │   ├── VariantAttribute.php
│   │   ├── IndexCreateRequest.php
│   │   └── IndexCreateRequestBuilder.php
│   ├── Configuration/
│   │   ├── MatchMode.php (enum)
│   │   ├── FuzzyMode.php (enum)
│   │   ├── MultiWordOperator.php (enum)
│   │   ├── BoostAlgorithm.php (enum)
│   │   ├── SearchFieldConfig.php
│   │   ├── SearchFieldConfigBuilder.php
│   │   ├── FuzzyMatchingConfig.php
│   │   ├── PopularityBoostConfig.php
│   │   ├── QueryConfigurationRequest.php
│   │   └── QueryConfigurationRequestBuilder.php
│   ├── Synonyms/
│   │   └── SynonymConfiguration.php
│   ├── BulkOperations/
│   │   ├── BulkOperationType.php (enum)
│   │   ├── ImageUrl.php
│   │   ├── ProductVariant.php
│   │   ├── Product.php
│   │   ├── ProductBuilder.php
│   │   ├── IndexProductsPayload.php
│   │   ├── BulkOperation.php
│   │   └── BulkOperationsRequest.php
│   ├── SearchSettings/
│   │   ├── SearchBehaviorType.php (enum)
│   │   ├── ScoreMode.php (enum)
│   │   ├── MultiMatchType.php (enum)
│   │   ├── ScoreModifier.php (enum)
│   │   ├── SearchBehavior.php
│   │   ├── FieldConfig.php
│   │   ├── NestedFieldConfig.php
│   │   ├── MultiMatchConfig.php
│   │   ├── SearchConfig.php
│   │   ├── FunctionScoreConfig.php
│   │   ├── ScoringConfig.php
│   │   ├── ResponseConfig.php
│   │   ├── SearchSettingsRequest.php
│   │   └── SearchSettingsRequestBuilder.php
│   ├── Responses/
│   │   ├── IndexCreationResponse.php
│   │   ├── IndexInfoResponse.php
│   │   ├── IndexVersion.php
│   │   ├── VersionActivateResponse.php
│   │   ├── QueryConfigurationResponse.php
│   │   ├── SynonymResponse.php
│   │   ├── BulkOperationsResponse.php
│   │   ├── OperationResult.php
│   │   ├── SettingsResponse.php
│   │   └── ErrorResponse.php
│   ├── Common/
│   │   ├── LocalizedField.php
│   │   └── ImageUrl.php
│   └── Exceptions/
│       ├── InvalidFieldTypeException.php
│       ├── InvalidLocaleException.php
│       ├── InvalidBoostValueException.php
│       └── ValidationException.php
tests/
├── V2/
│   ├── ValueObjects/
│   │   ├── Index/
│   │   ├── Configuration/
│   │   ├── Synonyms/
│   │   ├── BulkOperations/
│   │   └── SearchSettings/
│   └── ApiPayloadVerificationTest.php
└── fixtures/
    └── openapi-examples/
        ├── index-create-darbo-drabuziai.json
        ├── configuration-advanced.json
        ├── synonyms-ecommerce-en.json
        ├── bulk-operations-darbo-drabuziai.json
        └── search-settings-full.json
```

## Success Metrics

- All 16 user stories implemented and passing quality gates
- 100% of OpenAPI example payloads matched by SDK output
- Zero PHPStan errors at maximum level
- Zero PHPCS violations
- All v2 functionality uses ValueObjects exclusively (no array-based methods)

## Decisions

- **Array-based methods:** Removed entirely from v2 SDK (not released yet, no backward compatibility needed)
- **Fluent factory methods:** Not implementing shortcuts like `FieldDefinition::text('name')`. Use Builders for all construction.

---

### US-017: Create fromJSON factory methods for Search Configuration ValueObjects
**Description:** As a developer, I want static `fromJSON`/`fromArray` factory methods that parse JSON configuration into ValueObjects so that I can load search configurations from external sources.

The JSON format supports:
- `supported_locales`: Array of locale strings
- `query_config`: Fields configuration with searchTypes, nested fields, cross-fields matching
- `response_config`: Source fields, highlight config, sortable fields, variant enrichment

**Implementation Details:**

#### New ValueObjects to Create

1. **`src/V2/ValueObjects/SearchSettings/SearchConfigurationRequest.php`**
   - Main container for the full JSON structure
   - Properties: `supportedLocales`, `queryConfig`, `responseConfig`
   - Static `fromArray(array $data): self` method
   - Static `fromJson(string $json): self` method

2. **`src/V2/ValueObjects/SearchSettings/QueryConfig.php`**
   - Properties: `fields` (array of QueryField), `crossFieldsMatching` (array of strings)
   - Static `fromArray(array $data): self` method

3. **`src/V2/ValueObjects/SearchSettings/QueryField.php`**
   - Properties: `type`, `name`, `localeSuffix`, `searchTypes`, `lastWordSearch`, `nestedPath`, `scoreMode`, `nestedFields`, `localeAware`
   - Static `fromArray(array $data): self` method

4. **`src/V2/ValueObjects/SearchSettings/HighlightConfig.php`**
   - Properties: `enabled`, `fields` (array of HighlightField)
   - Static `fromArray(array $data): self` method

5. **`src/V2/ValueObjects/SearchSettings/HighlightField.php`**
   - Properties: `fieldName`, `localeSuffix`, `preTags`, `postTags`
   - Static `fromArray(array $data): self` method

6. **`src/V2/ValueObjects/SearchSettings/VariantEnrichmentConfig.php`**
   - Properties: `replaceFields` (array of strings)
   - Static `fromArray(array $data): self` method

#### Modifications to Existing ValueObjects

1. **`ResponseConfig.php`** - Add properties:
   - `?HighlightConfig $highlightConfig`
   - `?VariantEnrichmentConfig $variantEnrichment`
   - `?array $sortableFieldsMap` (for key-value sortable fields)
   - Add `fromArray(array $data): self` method

#### Enums to Create

1. **`src/V2/ValueObjects/SearchSettings/QueryFieldType.php`**
   - Values: `text`, `nested`

2. **`src/V2/ValueObjects/SearchSettings/SearchType.php`**
   - Values: `match`, `match-fuzzy`, `autocomplete`, `exact`, `autocomplete-nospace`, `substring`

**Acceptance Criteria:**
- [ ] Create `SearchConfigurationRequest` with `fromArray()` and `fromJson()` methods
- [ ] Create `QueryConfig` with `fromArray()` method
- [ ] Create `QueryField` with `fromArray()` method supporting both text and nested field types
- [ ] Create `HighlightConfig` and `HighlightField` ValueObjects with `fromArray()` methods
- [ ] Create `VariantEnrichmentConfig` with `fromArray()` method
- [ ] Update `ResponseConfig` to include highlight and variant enrichment configs
- [ ] Create `QueryFieldType` and `SearchType` enums
- [ ] All ValueObjects are immutable with readonly properties
- [ ] Unit tests verify JSON parsing matches expected ValueObject structure
- [ ] Unit test with exact JSON from user example produces correct ValueObjects
- [ ] `vendor/bin/phpunit` passes
- [ ] `vendor/bin/phpstan analyse` passes
- [ ] `vendor/bin/phpcs src tests` passes

---

### US-018: Create PrestaShopAdapterV2 service for V2 bulk operations
**Description:** As a developer, I want a `PrestaShopAdapterV2` service that transforms PrestaShop data format into V2 `Product` and `ProductVariant` ValueObjects so that PrestaShop data can be synced using the `BulkOperationsRequest` endpoint.

**Implementation Details:**

#### Files to Create

1. **`src/Adapters/PrestaShopAdapterV2.php`**
   - Transform PrestaShop product data to V2 `Product` entities
   - Return `BulkOperationsRequest` ready for `SyncV2Sdk::bulkOperations()`

#### Transformation Mapping

**PrestaShop → V2 Product:**
| PrestaShop Field | V2 Product Field |
|------------------|------------------|
| `remoteId` | `id` |
| `price` | `price` |
| `imageUrl` | `imageUrl` (as ImageUrl ValueObject) |
| `localizedNames` | `additionalFields['name']` / `additionalFields['name_lt-LT']` |
| `brand.localizedNames` | `additionalFields['brand']` / `additionalFields['brand_lt-LT']` |
| `description` | `additionalFields['description_*']` |
| `categories` | `additionalFields['categories_*']` |
| `sku`, `basePrice`, etc. | `additionalFields['sku']`, `additionalFields['basePrice']` |
| `variants` | `variants` (as ProductVariant[] with locale-specific handling) |

**PrestaShop Variant → V2 ProductVariant:**
| PrestaShop Field | V2 ProductVariant Field |
|------------------|------------------------|
| `remoteId` | `id` |
| `sku` | `sku` |
| `price` | `price` |
| `basePrice` | `basePrice` |
| `priceTaxExcluded` | `priceTaxExcluded` |
| `basePriceTaxExcluded` | `basePriceTaxExcluded` |
| `productUrl.localizedValues[locale]` | `productUrl` |
| `imageUrl` | `imageUrl` (as ImageUrl ValueObject) |
| `attributes[name].localizedValues[locale]` | `attrs` |

#### Key Differences from V1 Adapter

1. Returns typed `Product` ValueObjects instead of raw arrays
2. Creates `BulkOperationsRequest` directly
3. Handles locale-specific variants properly (separate variants per locale)
4. Uses `ImageUrl` ValueObject for image URLs
5. Maps variant attributes to `attrs` array format

**Acceptance Criteria:**
- [ ] Create `PrestaShopAdapterV2` class in `src/Adapters/`
- [ ] Method `transform(array $prestaShopData): BulkOperationsRequest`
- [ ] Method `transformProduct(array $product): Product` for single product transformation
- [ ] Method `transformVariant(array $variant, string $locale): ProductVariant`
- [ ] Handles localized fields correctly (name, brand, description, categories)
- [ ] Handles variants with locale-specific URLs and attributes
- [ ] Creates proper `ImageUrl` ValueObjects from image data
- [ ] Returns transformation errors alongside successful products
- [ ] Unit tests verify transformation from PrestaShop format to V2 format
- [ ] Unit test uses sample PrestaShop data and verifies BulkOperationsRequest structure
- [ ] `vendor/bin/phpunit` passes
- [ ] `vendor/bin/phpstan analyse` passes
- [ ] `vendor/bin/phpcs src tests` passes