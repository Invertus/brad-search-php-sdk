<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a multi-match configuration for search settings.
 *
 * This immutable ValueObject defines how multiple fields should be combined in a multi-match query,
 * including field references, match type, operator, and boost factor.
 */
final readonly class MultiMatchConfig extends ValueObject
{
    private const MIN_BOOST = 0.01;
    private const MAX_BOOST = 100.0;

    /**
     * @param string $id Unique identifier for this multi-match configuration
     * @param array<string> $fieldIds Array of field config IDs to combine
     * @param MultiMatchType $type The type of multi-match query
     * @param string|null $operator Optional operator for the query (e.g., 'and', 'or')
     * @param float|null $boost Optional boost factor for relevance scoring (0.01 to 100.0)
     */
    public function __construct(
        public string $id,
        public array $fieldIds,
        public MultiMatchType $type = MultiMatchType::BEST_FIELDS,
        public ?string $operator = null,
        public ?float $boost = null
    ) {
        $this->validateId($id);
        $this->validateFieldIds($fieldIds);
        $this->validateBoost($boost);
    }

    /**
     * Returns a new instance with a different id.
     */
    public function withId(string $id): self
    {
        return new self($id, $this->fieldIds, $this->type, $this->operator, $this->boost);
    }

    /**
     * Returns a new instance with different field IDs.
     *
     * @param array<string> $fieldIds
     */
    public function withFieldIds(array $fieldIds): self
    {
        return new self($this->id, $fieldIds, $this->type, $this->operator, $this->boost);
    }

    /**
     * Returns a new instance with an additional field ID.
     */
    public function withAddedFieldId(string $fieldId): self
    {
        return new self(
            $this->id,
            [...$this->fieldIds, $fieldId],
            $this->type,
            $this->operator,
            $this->boost
        );
    }

    /**
     * Returns a new instance with a different type.
     */
    public function withType(MultiMatchType $type): self
    {
        return new self($this->id, $this->fieldIds, $type, $this->operator, $this->boost);
    }

    /**
     * Returns a new instance with a different operator.
     */
    public function withOperator(?string $operator): self
    {
        return new self($this->id, $this->fieldIds, $this->type, $operator, $this->boost);
    }

    /**
     * Returns a new instance with a different boost.
     */
    public function withBoost(?float $boost): self
    {
        return new self($this->id, $this->fieldIds, $this->type, $this->operator, $boost);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'field_ids' => $this->fieldIds,
            'type' => $this->type->value,
        ];

        if ($this->operator !== null) {
            $result['operator'] = $this->operator;
        }

        if ($this->boost !== null) {
            $result['boost'] = $this->boost;
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
                'Multi-match config id cannot be empty.',
                'id',
                $id
            );
        }
    }

    /**
     * Validates that field IDs array is not empty and contains only strings.
     *
     * @param array<mixed> $fieldIds
     * @throws InvalidArgumentException If field IDs are invalid
     */
    private function validateFieldIds(array $fieldIds): void
    {
        if (count($fieldIds) === 0) {
            throw new InvalidArgumentException(
                'At least one field ID is required for multi-match config.',
                'field_ids',
                $fieldIds
            );
        }

        foreach ($fieldIds as $index => $fieldId) {
            if (!is_string($fieldId)) {
                throw new InvalidArgumentException(
                    sprintf('Field ID at index %d must be a string.', $index),
                    'field_ids',
                    $fieldId
                );
            }

            if ($fieldId === '') {
                throw new InvalidArgumentException(
                    sprintf('Field ID at index %d cannot be empty.', $index),
                    'field_ids',
                    $fieldId
                );
            }
        }
    }

    /**
     * Validates that the boost is within the valid range.
     *
     * @throws InvalidArgumentException If boost is out of range
     */
    private function validateBoost(?float $boost): void
    {
        if ($boost === null) {
            return;
        }

        if ($boost < self::MIN_BOOST || $boost > self::MAX_BOOST) {
            throw new InvalidArgumentException(
                sprintf(
                    'Boost must be between %.2f and %.2f, got %.2f.',
                    self::MIN_BOOST,
                    self::MAX_BOOST,
                    $boost
                ),
                'boost',
                $boost
            );
        }
    }
}
