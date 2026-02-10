<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the query configuration for search settings.
 *
 * This immutable ValueObject defines how search queries should be processed,
 * including field configurations and cross-fields matching rules.
 */
final readonly class QueryConfig extends ValueObject
{
    /**
     * @param array<QueryField> $fields Array of query field configurations
     * @param array<string> $crossFieldsMatching Array of field names for cross-field matching
     */
    public function __construct(
        public array $fields = [],
        public array $crossFieldsMatching = []
    ) {
        $this->validateFields($fields);
        $this->validateCrossFieldsMatching($crossFieldsMatching);
    }

    /**
     * Creates a QueryConfig from an array (typically from JSON).
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public static function fromArray(array $data): self
    {
        $fields = [];
        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $fields[] = QueryField::fromArray($fieldData);
            }
        }

        $crossFieldsMatching = [];
        if (isset($data['cross_fields_matching']) && is_array($data['cross_fields_matching'])) {
            $crossFieldsMatching = $data['cross_fields_matching'];
        }

        return new self(
            fields: $fields,
            crossFieldsMatching: $crossFieldsMatching
        );
    }

    /**
     * Returns a new instance with different fields.
     *
     * @param array<QueryField> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($fields, $this->crossFieldsMatching);
    }

    /**
     * Returns a new instance with an additional field.
     */
    public function withAddedField(QueryField $field): self
    {
        return new self([...$this->fields, $field], $this->crossFieldsMatching);
    }

    /**
     * Returns a new instance with different cross-fields matching configuration.
     *
     * @param array<string> $crossFieldsMatching
     */
    public function withCrossFieldsMatching(array $crossFieldsMatching): self
    {
        return new self($this->fields, $crossFieldsMatching);
    }

    /**
     * Returns a new instance with an additional cross-fields matching field.
     */
    public function withAddedCrossFieldsMatching(string $fieldName): self
    {
        return new self($this->fields, [...$this->crossFieldsMatching, $fieldName]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if (count($this->fields) > 0) {
            $result['fields'] = array_map(
                fn(QueryField $field) => $field->jsonSerialize(),
                $this->fields
            );
        }

        if (count($this->crossFieldsMatching) > 0) {
            $result['cross_fields_matching'] = $this->crossFieldsMatching;
        }

        return $result;
    }

    /**
     * Validates that all fields are valid QueryField instances.
     *
     * @param array<mixed> $fields
     * @throws InvalidArgumentException If any field is invalid
     */
    private function validateFields(array $fields): void
    {
        foreach ($fields as $index => $field) {
            if (!$field instanceof QueryField) {
                throw new InvalidArgumentException(
                    sprintf('Field at index %d must be an instance of QueryField.', $index),
                    'fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates that all cross-fields matching entries are valid strings.
     *
     * @param array<mixed> $crossFieldsMatching
     * @throws InvalidArgumentException If any entry is invalid
     */
    private function validateCrossFieldsMatching(array $crossFieldsMatching): void
    {
        foreach ($crossFieldsMatching as $index => $fieldName) {
            if (!is_string($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Cross-fields matching entry at index %d must be a string.', $index),
                    'cross_fields_matching',
                    $fieldName
                );
            }

            if ($fieldName === '') {
                throw new InvalidArgumentException(
                    sprintf('Cross-fields matching entry at index %d cannot be empty.', $index),
                    'cross_fields_matching',
                    $fieldName
                );
            }
        }
    }
}
