<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response configuration for search settings.
 *
 * This immutable ValueObject defines what fields should be included in search responses,
 * including source fields to return and sortable fields for result ordering.
 */
final readonly class ResponseConfig extends ValueObject
{
    /**
     * @param array<string> $sourceFields Array of field names to include in the response source
     * @param array<string> $sortableFields Array of field names that can be used for sorting
     */
    public function __construct(
        public array $sourceFields = [],
        public array $sortableFields = []
    ) {
        $this->validateSourceFields($sourceFields);
        $this->validateSortableFields($sortableFields);
    }

    /**
     * Returns a new instance with different source fields.
     *
     * @param array<string> $sourceFields
     */
    public function withSourceFields(array $sourceFields): self
    {
        return new self($sourceFields, $this->sortableFields);
    }

    /**
     * Returns a new instance with an additional source field.
     */
    public function withAddedSourceField(string $sourceField): self
    {
        return new self([...$this->sourceFields, $sourceField], $this->sortableFields);
    }

    /**
     * Returns a new instance with different sortable fields.
     *
     * @param array<string> $sortableFields
     */
    public function withSortableFields(array $sortableFields): self
    {
        return new self($this->sourceFields, $sortableFields);
    }

    /**
     * Returns a new instance with an additional sortable field.
     */
    public function withAddedSortableField(string $sortableField): self
    {
        return new self($this->sourceFields, [...$this->sortableFields, $sortableField]);
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

        if (count($this->sortableFields) > 0) {
            $result['sortable_fields'] = $this->sortableFields;
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
}
