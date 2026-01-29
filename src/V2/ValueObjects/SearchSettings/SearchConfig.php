<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the search configuration containing fields, nested fields, and multi-match configs.
 *
 * This immutable ValueObject aggregates all search-related configurations including
 * standard fields, nested fields, and multi-match query configurations.
 */
final readonly class SearchConfig extends ValueObject
{
    /**
     * @param array<FieldConfig> $fields Array of field configurations
     * @param array<NestedFieldConfig> $nestedFields Array of nested field configurations
     * @param array<MultiMatchConfig> $multiMatchConfigs Array of multi-match configurations
     */
    public function __construct(
        public array $fields = [],
        public array $nestedFields = [],
        public array $multiMatchConfigs = []
    ) {
        $this->validateFields($fields);
        $this->validateNestedFields($nestedFields);
        $this->validateMultiMatchConfigs($multiMatchConfigs);
    }

    /**
     * Returns a new instance with different fields.
     *
     * @param array<FieldConfig> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($fields, $this->nestedFields, $this->multiMatchConfigs);
    }

    /**
     * Returns a new instance with an additional field.
     */
    public function withAddedField(FieldConfig $field): self
    {
        return new self(
            [...$this->fields, $field],
            $this->nestedFields,
            $this->multiMatchConfigs
        );
    }

    /**
     * Returns a new instance with different nested fields.
     *
     * @param array<NestedFieldConfig> $nestedFields
     */
    public function withNestedFields(array $nestedFields): self
    {
        return new self($this->fields, $nestedFields, $this->multiMatchConfigs);
    }

    /**
     * Returns a new instance with an additional nested field.
     */
    public function withAddedNestedField(NestedFieldConfig $nestedField): self
    {
        return new self(
            $this->fields,
            [...$this->nestedFields, $nestedField],
            $this->multiMatchConfigs
        );
    }

    /**
     * Returns a new instance with different multi-match configs.
     *
     * @param array<MultiMatchConfig> $multiMatchConfigs
     */
    public function withMultiMatchConfigs(array $multiMatchConfigs): self
    {
        return new self($this->fields, $this->nestedFields, $multiMatchConfigs);
    }

    /**
     * Returns a new instance with an additional multi-match config.
     */
    public function withAddedMultiMatchConfig(MultiMatchConfig $multiMatchConfig): self
    {
        return new self(
            $this->fields,
            $this->nestedFields,
            [...$this->multiMatchConfigs, $multiMatchConfig]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if (count($this->fields) > 0) {
            $result['fields'] = array_map(
                fn(FieldConfig $field) => $field->jsonSerialize(),
                $this->fields
            );
        }

        if (count($this->nestedFields) > 0) {
            $result['nested_fields'] = array_map(
                fn(NestedFieldConfig $nestedField) => $nestedField->jsonSerialize(),
                $this->nestedFields
            );
        }

        if (count($this->multiMatchConfigs) > 0) {
            $result['multi_match_configs'] = array_map(
                fn(MultiMatchConfig $config) => $config->jsonSerialize(),
                $this->multiMatchConfigs
            );
        }

        return $result;
    }

    /**
     * Validates that all fields are valid instances.
     *
     * @param array<mixed> $fields
     * @throws InvalidArgumentException If any field is invalid
     */
    private function validateFields(array $fields): void
    {
        foreach ($fields as $index => $field) {
            if (!$field instanceof FieldConfig) {
                throw new InvalidArgumentException(
                    sprintf('Field at index %d must be an instance of FieldConfig.', $index),
                    'fields',
                    $field
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
        foreach ($nestedFields as $index => $nestedField) {
            if (!$nestedField instanceof NestedFieldConfig) {
                throw new InvalidArgumentException(
                    sprintf('Nested field at index %d must be an instance of NestedFieldConfig.', $index),
                    'nested_fields',
                    $nestedField
                );
            }
        }
    }

    /**
     * Validates that all multi-match configs are valid instances.
     *
     * @param array<mixed> $multiMatchConfigs
     * @throws InvalidArgumentException If any multi-match config is invalid
     */
    private function validateMultiMatchConfigs(array $multiMatchConfigs): void
    {
        foreach ($multiMatchConfigs as $index => $config) {
            if (!$config instanceof MultiMatchConfig) {
                throw new InvalidArgumentException(
                    sprintf('Multi-match config at index %d must be an instance of MultiMatchConfig.', $index),
                    'multi_match_configs',
                    $config
                );
            }
        }
    }
}
