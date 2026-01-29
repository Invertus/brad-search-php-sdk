<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a field configuration for search settings.
 *
 * This immutable ValueObject defines how a specific field should be configured for search,
 * including its identifier, field name, optional locale suffix, and search behaviors.
 */
final readonly class FieldConfig extends ValueObject
{
    /**
     * @param string $id Unique identifier for this field configuration
     * @param string $fieldName The name of the field in the index
     * @param string|null $localeSuffix Optional locale suffix (e.g., 'en', 'lt')
     * @param array<SearchBehavior> $searchBehaviors Array of search behavior configurations
     */
    public function __construct(
        public string $id,
        public string $fieldName,
        public ?string $localeSuffix = null,
        public array $searchBehaviors = []
    ) {
        $this->validateId($id);
        $this->validateFieldName($fieldName);
        $this->validateSearchBehaviors($searchBehaviors);
    }

    /**
     * Returns a new instance with a different id.
     */
    public function withId(string $id): self
    {
        return new self($id, $this->fieldName, $this->localeSuffix, $this->searchBehaviors);
    }

    /**
     * Returns a new instance with a different field name.
     */
    public function withFieldName(string $fieldName): self
    {
        return new self($this->id, $fieldName, $this->localeSuffix, $this->searchBehaviors);
    }

    /**
     * Returns a new instance with a different locale suffix.
     */
    public function withLocaleSuffix(?string $localeSuffix): self
    {
        return new self($this->id, $this->fieldName, $localeSuffix, $this->searchBehaviors);
    }

    /**
     * Returns a new instance with different search behaviors.
     *
     * @param array<SearchBehavior> $searchBehaviors
     */
    public function withSearchBehaviors(array $searchBehaviors): self
    {
        return new self($this->id, $this->fieldName, $this->localeSuffix, $searchBehaviors);
    }

    /**
     * Returns a new instance with an additional search behavior.
     */
    public function withAddedSearchBehavior(SearchBehavior $searchBehavior): self
    {
        return new self(
            $this->id,
            $this->fieldName,
            $this->localeSuffix,
            [...$this->searchBehaviors, $searchBehavior]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'field_name' => $this->fieldName,
        ];

        if ($this->localeSuffix !== null) {
            $result['locale_suffix'] = $this->localeSuffix;
        }

        if (count($this->searchBehaviors) > 0) {
            $result['search_behaviors'] = array_map(
                fn(SearchBehavior $behavior) => $behavior->jsonSerialize(),
                $this->searchBehaviors
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
                'Field config id cannot be empty.',
                'id',
                $id
            );
        }
    }

    /**
     * Validates that the field name is not empty.
     *
     * @throws InvalidArgumentException If field name is empty
     */
    private function validateFieldName(string $fieldName): void
    {
        if ($fieldName === '') {
            throw new InvalidArgumentException(
                'Field name cannot be empty.',
                'field_name',
                $fieldName
            );
        }
    }

    /**
     * Validates that all search behaviors are valid instances.
     *
     * @param array<mixed> $searchBehaviors
     * @throws InvalidArgumentException If any search behavior is invalid
     */
    private function validateSearchBehaviors(array $searchBehaviors): void
    {
        foreach ($searchBehaviors as $index => $behavior) {
            if (!$behavior instanceof SearchBehavior) {
                throw new InvalidArgumentException(
                    sprintf('Search behavior at index %d must be an instance of SearchBehavior.', $index),
                    'search_behaviors',
                    $behavior
                );
            }
        }
    }
}
