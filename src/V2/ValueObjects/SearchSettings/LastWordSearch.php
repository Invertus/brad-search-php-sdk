<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the multi-word handling configuration of a query field.
 *
 * Mirrors the engine's LastWordSearchConfig (brad-search models/query_config.go):
 * the engine marshals this as an object, so anything reading a configuration back
 * gets `{"enabled": bool, "searchTypes": {...}, "fuzzy_config": {...}}`. Modelling it
 * as a bool loses `enabled: false` plus the tuned per-position search types.
 *
 * The engine also accepts a bare `true`/`false` on input and expands `true` into a
 * hardcoded first/last/full set. That shorthand is preserved by QueryField, which
 * keeps a bool as a bool rather than normalising it into this object - expanding it
 * here would change what the engine stores.
 */
final readonly class LastWordSearch extends ValueObject
{
    /** Positions the engine understands inside `searchTypes`. */
    public const POSITIONS = ['first', 'last', 'full'];

    /**
     * @param bool $enabled Whether multi-word handling is active
     * @param array<string, array<SearchType|string>> $searchTypes Per-position search types, keyed first/last/full
     * @param array<string, mixed>|null $fuzzyConfig Per-position fuzzy overrides, passed through untouched
     */
    public function __construct(
        public bool $enabled = false,
        public array $searchTypes = [],
        public ?array $fuzzyConfig = null
    ) {
        $this->validateSearchTypes($searchTypes);
    }

    /**
     * Creates a LastWordSearch from an array (typically from JSON).
     *
     * Reads both the engine's camelCase `searchTypes` and the legacy snake_case
     * `search_types` this SDK used to emit.
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @throws InvalidArgumentException If a search type entry is not a string
     */
    public static function fromArray(array $data): self
    {
        $rawSearchTypes = $data['searchTypes'] ?? $data['search_types'] ?? [];

        $searchTypes = [];
        if (is_array($rawSearchTypes)) {
            foreach ($rawSearchTypes as $position => $types) {
                if (!is_array($types)) {
                    continue;
                }

                $searchTypes[(string) $position] = array_map(
                    fn(mixed $type) => SearchTypeParser::parse($type, 'lastWordSearch.searchTypes'),
                    array_values($types)
                );
            }
        }

        $fuzzyConfig = null;
        if (isset($data['fuzzy_config']) && is_array($data['fuzzy_config'])) {
            $fuzzyConfig = $data['fuzzy_config'];
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            searchTypes: $searchTypes,
            fuzzyConfig: $fuzzyConfig
        );
    }

    /**
     * Returns a new instance with a different enabled flag.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self($enabled, $this->searchTypes, $this->fuzzyConfig);
    }

    /**
     * Returns a new instance with different per-position search types.
     *
     * @param array<string, array<SearchType|string>> $searchTypes
     */
    public function withSearchTypes(array $searchTypes): self
    {
        return new self($this->enabled, $searchTypes, $this->fuzzyConfig);
    }

    /**
     * Returns a new instance with a different fuzzy config.
     *
     * @param array<string, mixed>|null $fuzzyConfig
     */
    public function withFuzzyConfig(?array $fuzzyConfig): self
    {
        return new self($this->enabled, $this->searchTypes, $fuzzyConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // `enabled` has no omitempty on the engine struct, so it is always emitted.
        $result = ['enabled' => $this->enabled];

        if (count($this->searchTypes) > 0) {
            $searchTypes = [];
            foreach ($this->searchTypes as $position => $types) {
                $searchTypes[$position] = array_map(
                    fn(SearchType|string $type) => $type instanceof SearchType ? $type->value : $type,
                    $types
                );
            }
            $result['searchTypes'] = $searchTypes;
        }

        if ($this->fuzzyConfig !== null) {
            $result['fuzzy_config'] = $this->fuzzyConfig;
        }

        return $result;
    }

    /**
     * Validates the per-position search type map.
     *
     * @param array<mixed> $searchTypes
     *
     * @throws InvalidArgumentException If a position is unknown or an entry is not a search type
     */
    private function validateSearchTypes(array $searchTypes): void
    {
        foreach ($searchTypes as $position => $types) {
            if (!in_array($position, self::POSITIONS, true)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown lastWordSearch position "%s"; expected one of: %s.', (string) $position, implode(', ', self::POSITIONS)),
                    'searchTypes',
                    $position
                );
            }

            if (!is_array($types)) {
                throw new InvalidArgumentException(
                    sprintf('lastWordSearch search types for position "%s" must be an array.', (string) $position),
                    'searchTypes',
                    $types
                );
            }

            foreach ($types as $index => $type) {
                if ($type instanceof SearchType) {
                    continue;
                }

                if (!is_string($type) || $type === '') {
                    throw new InvalidArgumentException(
                        sprintf('lastWordSearch search type at %s[%d] must be a SearchType or a non-empty string.', (string) $position, (int) $index),
                        'searchTypes',
                        $type
                    );
                }
            }
        }
    }
}
