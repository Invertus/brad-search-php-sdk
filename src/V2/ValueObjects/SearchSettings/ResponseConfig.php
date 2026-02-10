<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response configuration for search settings.
 *
 * This immutable ValueObject defines what fields should be included in search responses,
 * including source fields to return, sortable fields for result ordering,
 * highlighting configuration, and variant enrichment settings.
 */
final readonly class ResponseConfig extends ValueObject
{
    /**
     * @param array<string> $sourceFields Array of field names to include in the response source
     * @param array<string> $sortableFields Array of field names that can be used for sorting
     * @param HighlightConfig|null $highlightConfig Optional highlighting configuration
     * @param VariantEnrichmentConfig|null $variantEnrichment Optional variant enrichment configuration
     * @param array<string, string>|null $sortableFieldsMap Optional key-value map for sortable fields
     */
    public function __construct(
        public array $sourceFields = [],
        public array $sortableFields = [],
        public ?HighlightConfig $highlightConfig = null,
        public ?VariantEnrichmentConfig $variantEnrichment = null,
        public ?array $sortableFieldsMap = null
    ) {
        $this->validateSourceFields($sourceFields);
        $this->validateSortableFields($sortableFields);
        $this->validateSortableFieldsMap($sortableFieldsMap);
    }

    /**
     * Creates a ResponseConfig from an array (typically from JSON).
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public static function fromArray(array $data): self
    {
        $sourceFields = [];
        if (isset($data['source_fields']) && is_array($data['source_fields'])) {
            $sourceFields = $data['source_fields'];
        }

        $sortableFields = [];
        if (isset($data['sortable_fields']) && is_array($data['sortable_fields'])) {
            // Handle both array of strings and key-value map formats
            if (self::isAssociativeArray($data['sortable_fields'])) {
                // It's a map like {"price": "asc", "name": "desc"}
                $sortableFields = array_keys($data['sortable_fields']);
            } else {
                $sortableFields = $data['sortable_fields'];
            }
        }

        $highlightConfig = null;
        if (isset($data['highlight_config']) && is_array($data['highlight_config'])) {
            $highlightConfig = HighlightConfig::fromArray($data['highlight_config']);
        }

        $variantEnrichment = null;
        if (isset($data['variant_enrichment']) && is_array($data['variant_enrichment'])) {
            $variantEnrichment = VariantEnrichmentConfig::fromArray($data['variant_enrichment']);
        }

        $sortableFieldsMap = null;
        if (isset($data['sortable_fields']) && is_array($data['sortable_fields'])) {
            if (self::isAssociativeArray($data['sortable_fields'])) {
                $sortableFieldsMap = $data['sortable_fields'];
            }
        }

        return new self(
            sourceFields: $sourceFields,
            sortableFields: $sortableFields,
            highlightConfig: $highlightConfig,
            variantEnrichment: $variantEnrichment,
            sortableFieldsMap: $sortableFieldsMap
        );
    }

    /**
     * Checks if an array is associative (has string keys).
     *
     * @param array<mixed> $array
     */
    private static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Returns a new instance with different source fields.
     *
     * @param array<string> $sourceFields
     */
    public function withSourceFields(array $sourceFields): self
    {
        return new self(
            $sourceFields,
            $this->sortableFields,
            $this->highlightConfig,
            $this->variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with an additional source field.
     */
    public function withAddedSourceField(string $sourceField): self
    {
        return new self(
            [...$this->sourceFields, $sourceField],
            $this->sortableFields,
            $this->highlightConfig,
            $this->variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with different sortable fields.
     *
     * @param array<string> $sortableFields
     */
    public function withSortableFields(array $sortableFields): self
    {
        return new self(
            $this->sourceFields,
            $sortableFields,
            $this->highlightConfig,
            $this->variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with an additional sortable field.
     */
    public function withAddedSortableField(string $sortableField): self
    {
        return new self(
            $this->sourceFields,
            [...$this->sortableFields, $sortableField],
            $this->highlightConfig,
            $this->variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with different highlight configuration.
     */
    public function withHighlightConfig(?HighlightConfig $highlightConfig): self
    {
        return new self(
            $this->sourceFields,
            $this->sortableFields,
            $highlightConfig,
            $this->variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with different variant enrichment configuration.
     */
    public function withVariantEnrichment(?VariantEnrichmentConfig $variantEnrichment): self
    {
        return new self(
            $this->sourceFields,
            $this->sortableFields,
            $this->highlightConfig,
            $variantEnrichment,
            $this->sortableFieldsMap
        );
    }

    /**
     * Returns a new instance with different sortable fields map.
     *
     * @param array<string, string>|null $sortableFieldsMap
     */
    public function withSortableFieldsMap(?array $sortableFieldsMap): self
    {
        return new self(
            $this->sourceFields,
            $this->sortableFields,
            $this->highlightConfig,
            $this->variantEnrichment,
            $sortableFieldsMap
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if (count($this->sourceFields) > 0) {
            $result['source_fields'] = $this->sourceFields;
        }

        // Prefer sortable_fields_map if available, otherwise use sortable_fields array
        if ($this->sortableFieldsMap !== null && count($this->sortableFieldsMap) > 0) {
            $result['sortable_fields'] = $this->sortableFieldsMap;
        } elseif (count($this->sortableFields) > 0) {
            $result['sortable_fields'] = $this->sortableFields;
        }

        if ($this->highlightConfig !== null) {
            $result['highlight_config'] = $this->highlightConfig->jsonSerialize();
        }

        if ($this->variantEnrichment !== null) {
            $variantEnrichmentArray = $this->variantEnrichment->jsonSerialize();
            if (count($variantEnrichmentArray) > 0) {
                $result['variant_enrichment'] = $variantEnrichmentArray;
            }
        }

        return $result;
    }

    /**
     * Validates that all source fields are valid strings.
     *
     * @param array<mixed> $sourceFields
     * @throws InvalidArgumentException If any source field is invalid
     */
    private function validateSourceFields(array $sourceFields): void
    {
        foreach ($sourceFields as $index => $field) {
            if (!is_string($field)) {
                throw new InvalidArgumentException(
                    sprintf('Source field at index %d must be a string.', $index),
                    'source_fields',
                    $field
                );
            }

            if ($field === '') {
                throw new InvalidArgumentException(
                    sprintf('Source field at index %d cannot be empty.', $index),
                    'source_fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates that all sortable fields are valid strings.
     *
     * @param array<mixed> $sortableFields
     * @throws InvalidArgumentException If any sortable field is invalid
     */
    private function validateSortableFields(array $sortableFields): void
    {
        foreach ($sortableFields as $index => $field) {
            if (!is_string($field)) {
                throw new InvalidArgumentException(
                    sprintf('Sortable field at index %d must be a string.', $index),
                    'sortable_fields',
                    $field
                );
            }

            if ($field === '') {
                throw new InvalidArgumentException(
                    sprintf('Sortable field at index %d cannot be empty.', $index),
                    'sortable_fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates that sortable fields map entries are valid.
     *
     * @param array<string, string>|null $sortableFieldsMap
     * @throws InvalidArgumentException If any entry is invalid
     */
    private function validateSortableFieldsMap(?array $sortableFieldsMap): void
    {
        if ($sortableFieldsMap === null) {
            return;
        }

        foreach ($sortableFieldsMap as $fieldName => $direction) {
            if (!is_string($fieldName) || $fieldName === '') {
                throw new InvalidArgumentException(
                    'Sortable field name must be a non-empty string.',
                    'sortable_fields_map',
                    $fieldName
                );
            }

            if (!is_string($direction)) {
                throw new InvalidArgumentException(
                    sprintf('Sortable field direction for "%s" must be a string.', $fieldName),
                    'sortable_fields_map',
                    $direction
                );
            }
        }
    }
}
