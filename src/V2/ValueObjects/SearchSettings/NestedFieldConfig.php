<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a nested field configuration for search settings.
 *
 * This immutable ValueObject defines how nested fields should be configured for search,
 * including the path to the nested object, score mode, and child field configurations.
 */
final readonly class NestedFieldConfig extends ValueObject
{
    /**
     * @param string $id Unique identifier for this nested field configuration
     * @param string $path The path to the nested object in the index
     * @param string|null $localeSuffix Optional locale suffix (e.g., 'en', 'lt')
     * @param ScoreMode $scoreMode How to combine scores from nested documents
     * @param array<FieldConfig> $fields Array of field configurations within the nested object
     */
    public function __construct(
        public string $id,
        public string $path,
        public ?string $localeSuffix = null,
        public ScoreMode $scoreMode = ScoreMode::AVG,
        public array $fields = []
    ) {
        $this->validateId($id);
        $this->validatePath($path);
        $this->validateFields($fields);
    }

    /**
     * Returns a new instance with a different id.
     */
    public function withId(string $id): self
    {
        return new self($id, $this->path, $this->localeSuffix, $this->scoreMode, $this->fields);
    }

    /**
     * Returns a new instance with a different path.
     */
    public function withPath(string $path): self
    {
        return new self($this->id, $path, $this->localeSuffix, $this->scoreMode, $this->fields);
    }

    /**
     * Returns a new instance with a different locale suffix.
     */
    public function withLocaleSuffix(?string $localeSuffix): self
    {
        return new self($this->id, $this->path, $localeSuffix, $this->scoreMode, $this->fields);
    }

    /**
     * Returns a new instance with a different score mode.
     */
    public function withScoreMode(ScoreMode $scoreMode): self
    {
        return new self($this->id, $this->path, $this->localeSuffix, $scoreMode, $this->fields);
    }

    /**
     * Returns a new instance with different fields.
     *
     * @param array<FieldConfig> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($this->id, $this->path, $this->localeSuffix, $this->scoreMode, $fields);
    }

    /**
     * Returns a new instance with an additional field.
     */
    public function withAddedField(FieldConfig $field): self
    {
        return new self(
            $this->id,
            $this->path,
            $this->localeSuffix,
            $this->scoreMode,
            [...$this->fields, $field]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'path' => $this->path,
            'score_mode' => $this->scoreMode->value,
        ];

        if ($this->localeSuffix !== null) {
            $result['locale_suffix'] = $this->localeSuffix;
        }

        if (count($this->fields) > 0) {
            $result['fields'] = array_map(
                fn(FieldConfig $field) => $field->jsonSerialize(),
                $this->fields
            );
        }

        return $result;
    }

    /**
     * Validates that the id is not empty.
     *
     * @throws InvalidArgumentException If id is empty
     */
    private function validateId(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException(
                'Nested field config id cannot be empty.',
                'id',
                $id
            );
        }
    }

    /**
     * Validates that the path is not empty.
     *
     * @throws InvalidArgumentException If path is empty
     */
    private function validatePath(string $path): void
    {
        if ($path === '') {
            throw new InvalidArgumentException(
                'Nested field path cannot be empty.',
                'path',
                $path
            );
        }
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
}
