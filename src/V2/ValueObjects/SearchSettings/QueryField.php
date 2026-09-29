<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a query field configuration for search settings.
 *
 * This immutable ValueObject defines how a field should be searched,
 * supporting both text fields and nested fields with their specific configurations.
 *
 * Keys and types mirror the engine's FieldBehaviorConfig (brad-search
 * models/query_config.go). `searchTypes` and `lastWordSearch` are camelCase there,
 * every other key is snake_case; `locale_suffix` is a bool, not a locale string.
 */
final readonly class QueryField extends ValueObject
{
    private const MIN_POSITION = 0;
    private const MAX_POSITION = 3;
    private const MIN_PRIORITY = 0;
    private const MAX_PRIORITY = 4;

    /**
     * @param QueryFieldType $type The type of field (text or nested)
     * @param string $name The field name
     * @param bool|null $localeSuffix Whether to append the locale suffix to the field name; null omits the key
     * @param array<SearchType|string> $searchTypes Search types to apply; unknown strings (e.g. "exact_300") pass through
     * @param bool|LastWordSearch|null $lastWordSearch Multi-word handling: the engine's shorthand bool, or the full object
     * @param string|null $nestedPath Path for nested fields (required for nested type)
     * @param ScoreMode|null $scoreMode Score mode for nested fields
     * @param array<QueryField> $nestedFields Child fields for nested type
     * @param bool|null $localeAware Whether the field is locale-aware
     * @param int|null $position Boost bucket (0-3); null means no bucket boost
     * @param int|null $priority Order inside the bucket (0-4); null means the default ×1.0
     * @param array<string, mixed>|null $fuzzyConfig Per-search-type fuzzy overrides, passed through untouched
     * @param array<string, float|int>|null $boosts Per-search-type boost weights, passed through untouched
     * @param bool|null $crossFieldOnly Whether the field only participates in cross-field matching
     */
    public function __construct(
        public QueryFieldType $type,
        public string $name,
        public ?bool $localeSuffix = null,
        public array $searchTypes = [],
        public bool|LastWordSearch|null $lastWordSearch = null,
        public ?string $nestedPath = null,
        public ?ScoreMode $scoreMode = null,
        public array $nestedFields = [],
        public ?bool $localeAware = null,
        public ?int $position = null,
        public ?int $priority = null,
        public ?array $fuzzyConfig = null,
        public ?array $boosts = null,
        public ?bool $crossFieldOnly = null
    ) {
        $this->validateName($name);
        $this->validateSearchTypes($searchTypes);
        $this->validateNestedFields($nestedFields);
        $this->validateNestedConfiguration();
        $this->validatePosition($position);
        $this->validatePriority($priority);
    }

    /**
     * Creates a QueryField from an array (typically from JSON).
     *
     * Accepts both the engine's camelCase keys and the snake_case keys this SDK
     * emitted before BRD-1273, so payloads stored by an older SDK still parse.
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['type', 'name']);

        $type = QueryFieldType::from($data['type']);

        $rawSearchTypes = $data['searchTypes'] ?? $data['search_types'] ?? null;
        $searchTypes = [];
        if (is_array($rawSearchTypes)) {
            foreach ($rawSearchTypes as $searchType) {
                $searchTypes[] = SearchTypeParser::parse($searchType);
            }
        }

        $nestedFields = [];
        if (isset($data['nested_fields']) && is_array($data['nested_fields'])) {
            foreach ($data['nested_fields'] as $nestedFieldData) {
                $nestedFields[] = self::fromArray($nestedFieldData);
            }
        }

        $scoreMode = null;
        if (isset($data['score_mode'])) {
            $scoreMode = ScoreMode::from($data['score_mode']);
        }

        return new self(
            type: $type,
            name: (string) $data['name'],
            localeSuffix: self::parseLocaleSuffix($data),
            searchTypes: $searchTypes,
            lastWordSearch: self::parseLastWordSearch($data),
            nestedPath: isset($data['nested_path']) ? (string) $data['nested_path'] : null,
            scoreMode: $scoreMode,
            nestedFields: $nestedFields,
            localeAware: isset($data['locale_aware']) ? (bool) $data['locale_aware'] : null,
            position: isset($data['position']) ? (int) $data['position'] : null,
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            fuzzyConfig: isset($data['fuzzy_config']) && is_array($data['fuzzy_config']) ? $data['fuzzy_config'] : null,
            boosts: isset($data['boosts']) && is_array($data['boosts']) ? $data['boosts'] : null,
            crossFieldOnly: isset($data['cross_field_only']) ? (bool) $data['cross_field_only'] : null
        );
    }

    /**
     * Returns a new instance with a different name.
     */
    public function withName(string $name): self
    {
        return new self(
            $this->type,
            $name,
            $this->localeSuffix,
            $this->searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with a different locale suffix flag.
     */
    public function withLocaleSuffix(?bool $localeSuffix): self
    {
        return new self(
            $this->type,
            $this->name,
            $localeSuffix,
            $this->searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with different search types.
     *
     * @param array<SearchType|string> $searchTypes
     */
    public function withSearchTypes(array $searchTypes): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            $searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with an additional search type.
     */
    public function withAddedSearchType(SearchType|string $searchType): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            [...$this->searchTypes, $searchType],
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with different multi-word handling.
     */
    public function withLastWordSearch(bool|LastWordSearch|null $lastWordSearch): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            $this->searchTypes,
            $lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with different nested fields.
     *
     * @param array<QueryField> $nestedFields
     */
    public function withNestedFields(array $nestedFields): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            $this->searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $nestedFields,
            $this->localeAware,
            $this->position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with a different boost bucket.
     */
    public function withPosition(?int $position): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            $this->searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $position,
            $this->priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * Returns a new instance with a different priority inside the bucket.
     */
    public function withPriority(?int $priority): self
    {
        return new self(
            $this->type,
            $this->name,
            $this->localeSuffix,
            $this->searchTypes,
            $this->lastWordSearch,
            $this->nestedPath,
            $this->scoreMode,
            $this->nestedFields,
            $this->localeAware,
            $this->position,
            $priority,
            $this->fuzzyConfig,
            $this->boosts,
            $this->crossFieldOnly
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->type->value,
            'name' => $this->name,
        ];

        if ($this->localeSuffix !== null) {
            $result['locale_suffix'] = $this->localeSuffix;
        }

        if (count($this->searchTypes) > 0) {
            $result['searchTypes'] = array_map(
                fn(SearchType|string $type) => $type instanceof SearchType ? $type->value : $type,
                $this->searchTypes
            );
        }

        if ($this->lastWordSearch !== null) {
            $result['lastWordSearch'] = $this->lastWordSearch instanceof LastWordSearch
                ? $this->lastWordSearch->jsonSerialize()
                : $this->lastWordSearch;
        }

        if ($this->nestedPath !== null) {
            $result['nested_path'] = $this->nestedPath;
        }

        if ($this->scoreMode !== null) {
            $result['score_mode'] = $this->scoreMode->value;
        }

        if (count($this->nestedFields) > 0) {
            $result['nested_fields'] = array_map(
                fn(QueryField $field) => $field->jsonSerialize(),
                $this->nestedFields
            );
        }

        if ($this->localeAware !== null) {
            $result['locale_aware'] = $this->localeAware;
        }

        if ($this->position !== null) {
            $result['position'] = $this->position;
        }

        if ($this->priority !== null) {
            $result['priority'] = $this->priority;
        }

        if ($this->fuzzyConfig !== null) {
            $result['fuzzy_config'] = $this->fuzzyConfig;
        }

        if ($this->boosts !== null) {
            $result['boosts'] = $this->boosts;
        }

        if ($this->crossFieldOnly !== null) {
            $result['cross_field_only'] = $this->crossFieldOnly;
        }

        return $result;
    }

    /**
     * Reads `locale_suffix`, which the engine declares as a bool.
     *
     * A wrong key name is dropped quietly by the engine; a wrong type is not - it fails
     * json.Unmarshal and the configuration endpoint rejects the entire save with a 400.
     * Coercing a locale string like "lt-LT" to true would be a silent behaviour change,
     * so a non-bool is refused here instead.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException If locale_suffix is present and not a bool
     */
    private static function parseLocaleSuffix(array $data): ?bool
    {
        if (!isset($data['locale_suffix'])) {
            return null;
        }

        if (!is_bool($data['locale_suffix'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'locale_suffix must be a boolean, %s given. The engine declares it as bool and rejects the whole configuration otherwise.',
                    get_debug_type($data['locale_suffix'])
                ),
                'locale_suffix',
                $data['locale_suffix']
            );
        }

        return $data['locale_suffix'];
    }

    /**
     * Reads `lastWordSearch` (or the legacy `last_word_search`).
     *
     * The engine accepts a bool shorthand and expands `true` into a hardcoded
     * first/last/full set, but it always marshals the full object back. A bool stays a
     * bool and an object becomes a LastWordSearch, so a read-modify-write re-emits what
     * it read instead of collapsing a disabled, hand-tuned config into `true`.
     *
     * @param array<string, mixed> $data
     */
    private static function parseLastWordSearch(array $data): bool|LastWordSearch|null
    {
        $raw = $data['lastWordSearch'] ?? $data['last_word_search'] ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return LastWordSearch::fromArray($raw);
        }

        return (bool) $raw;
    }

    /**
     * Validates that all required fields are present in the data array.
     *
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     *
     * @throws InvalidArgumentException If a required field is missing
     */
    private static function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    sprintf('Missing required field: %s', $field),
                    $field,
                    null
                );
            }
        }
    }

    /**
     * Validates that the field name is not empty.
     *
     * @throws InvalidArgumentException If name is empty
     */
    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException(
                'Field name cannot be empty.',
                'name',
                $name
            );
        }
    }

    /**
     * Validates that all search types are a SearchType case or a non-empty string.
     *
     * Strings are allowed because the engine accepts boost-modifier forms
     * ("exact_300") that the enum cannot represent.
     *
     * @param array<mixed> $searchTypes
     * @throws InvalidArgumentException If any search type is invalid
     */
    private function validateSearchTypes(array $searchTypes): void
    {
        foreach ($searchTypes as $index => $searchType) {
            if ($searchType instanceof SearchType) {
                continue;
            }

            if (!is_string($searchType) || $searchType === '') {
                throw new InvalidArgumentException(
                    sprintf('Search type at index %d must be a SearchType instance or a non-empty string.', $index),
                    'searchTypes',
                    $searchType
                );
            }
        }
    }

    /**
     * Validates that all nested fields are valid instances.
     *
     * @param array<mixed> $nestedFields
     * @throws InvalidArgumentException If any nested field is invalid
     */
    private function validateNestedFields(array $nestedFields): void
    {
        foreach ($nestedFields as $index => $field) {
            if (!$field instanceof QueryField) {
                throw new InvalidArgumentException(
                    sprintf('Nested field at index %d must be an instance of QueryField.', $index),
                    'nested_fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates nested type configuration.
     *
     * @throws InvalidArgumentException If nested type is missing required configuration
     */
    private function validateNestedConfiguration(): void
    {
        if ($this->type === QueryFieldType::NESTED && $this->nestedPath === null) {
            throw new InvalidArgumentException(
                'Nested fields require a nested_path to be specified.',
                'nested_path',
                null
            );
        }
    }

    /**
     * Validates the boost bucket. brad-search knows buckets 0-3 (BucketBoostsV3).
     *
     * @throws InvalidArgumentException If position is outside 0-3
     */
    private function validatePosition(?int $position): void
    {
        if ($position !== null && ($position < self::MIN_POSITION || $position > self::MAX_POSITION)) {
            throw new InvalidArgumentException(
                sprintf('Position must be between %d and %d, got %d.', self::MIN_POSITION, self::MAX_POSITION, $position),
                'position',
                $position
            );
        }
    }

    /**
     * Validates the priority step. brad-search's ladder has steps 0-4 (PriorityMultipliers).
     *
     * @throws InvalidArgumentException If priority is outside 0-4
     */
    private function validatePriority(?int $priority): void
    {
        if ($priority !== null && ($priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY)) {
            throw new InvalidArgumentException(
                sprintf('Priority must be between %d and %d, got %d.', self::MIN_PRIORITY, self::MAX_PRIORITY, $priority),
                'priority',
                $priority
            );
        }
    }
}
