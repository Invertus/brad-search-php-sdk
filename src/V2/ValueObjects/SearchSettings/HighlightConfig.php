<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the highlighting configuration for search results.
 *
 * This immutable ValueObject defines whether highlighting is enabled
 * and which fields should be highlighted with their specific configurations.
 */
final readonly class HighlightConfig extends ValueObject
{
    /**
     * @param bool $enabled Whether highlighting is enabled
     * @param array<HighlightField> $fields Array of field configurations for highlighting
     */
    public function __construct(
        public bool $enabled = false,
        public array $fields = []
    ) {
        $this->validateFields($fields);
    }

    /**
     * Creates a HighlightConfig from an array (typically from JSON).
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
                $fields[] = HighlightField::fromArray($fieldData);
            }
        }

        return new self(
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : false,
            fields: $fields
        );
    }

    /**
     * Returns a new instance with different enabled state.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self($enabled, $this->fields);
    }

    /**
     * Returns a new instance with different fields.
     *
     * @param array<HighlightField> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($this->enabled, $fields);
    }

    /**
     * Returns a new instance with an additional field.
     */
    public function withAddedField(HighlightField $field): self
    {
        return new self($this->enabled, [...$this->fields, $field]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'enabled' => $this->enabled,
        ];

        if (count($this->fields) > 0) {
            $result['fields'] = array_map(
                fn(HighlightField $field) => $field->jsonSerialize(),
                $this->fields
            );
        }

        return $result;
    }

    /**
     * Validates that all fields are valid HighlightField instances.
     *
     * @param array<mixed> $fields
     * @throws InvalidArgumentException If any field is invalid
     */
    private function validateFields(array $fields): void
    {
        foreach ($fields as $index => $field) {
            if (!$field instanceof HighlightField) {
                throw new InvalidArgumentException(
                    sprintf('Field at index %d must be an instance of HighlightField.', $index),
                    'fields',
                    $field
                );
            }
        }
    }
}
