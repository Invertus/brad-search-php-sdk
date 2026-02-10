<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a query configuration request matching QueryConfigurationRequest schema.
 *
 * This immutable ValueObject contains the complete configuration for search behavior,
 * including search fields, popularity boost, and scoring options.
 */
final readonly class QueryConfigurationRequest extends ValueObject
{
    private const MIN_SCORE_MIN = 0.0;
    private const MIN_SCORE_MAX = 1.0;

    /**
     * @param array<SearchFieldConfig> $searchFields Array of search field configurations
     * @param PopularityBoostConfig|null $popularityBoost Optional popularity boost configuration
     * @param MultiWordOperator $multiWordOperator Operator for multi-word queries (defaults to 'and')
     * @param float|null $minScore Optional minimum score threshold (0.0 to 1.0)
     */
    public function __construct(
        public array $searchFields,
        public ?PopularityBoostConfig $popularityBoost = null,
        public MultiWordOperator $multiWordOperator = MultiWordOperator::AND,
        public ?float $minScore = null
    ) {
        $this->validateSearchFields($searchFields);
        $this->validateMinScore($minScore);
    }

    /**
     * Returns a new instance with different search fields.
     *
     * @param array<SearchFieldConfig> $searchFields
     */
    public function withSearchFields(array $searchFields): self
    {
        return new self(
            $searchFields,
            $this->popularityBoost,
            $this->multiWordOperator,
            $this->minScore
        );
    }

    /**
     * Returns a new instance with an additional search field.
     */
    public function withAddedSearchField(SearchFieldConfig $searchField): self
    {
        return new self(
            [...$this->searchFields, $searchField],
            $this->popularityBoost,
            $this->multiWordOperator,
            $this->minScore
        );
    }

    /**
     * Returns a new instance with different popularity boost configuration.
     */
    public function withPopularityBoost(?PopularityBoostConfig $popularityBoost): self
    {
        return new self(
            $this->searchFields,
            $popularityBoost,
            $this->multiWordOperator,
            $this->minScore
        );
    }

    /**
     * Returns a new instance with different multi-word operator.
     */
    public function withMultiWordOperator(MultiWordOperator $multiWordOperator): self
    {
        return new self(
            $this->searchFields,
            $this->popularityBoost,
            $multiWordOperator,
            $this->minScore
        );
    }

    /**
     * Returns a new instance with different minimum score.
     */
    public function withMinScore(?float $minScore): self
    {
        return new self(
            $this->searchFields,
            $this->popularityBoost,
            $this->multiWordOperator,
            $minScore
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'search_fields' => array_map(
                fn(SearchFieldConfig $field) => $field->jsonSerialize(),
                $this->searchFields
            ),
            'multi_word_operator' => $this->multiWordOperator->value,
        ];

        if ($this->popularityBoost !== null) {
            $result['popularity_boost'] = $this->popularityBoost->jsonSerialize();
        }

        if ($this->minScore !== null) {
            $result['min_score'] = $this->minScore;
        }

        return $result;
    }

    /**
     * Validates that at least one search field is provided and all are valid instances.
     *
     * @param array<mixed> $searchFields
     * @throws InvalidArgumentException If no search fields are provided or invalid types
     */
    private function validateSearchFields(array $searchFields): void
    {
        if (count($searchFields) === 0) {
            throw new InvalidArgumentException(
                'At least one search field is required.',
                'search_fields',
                $searchFields
            );
        }

        foreach ($searchFields as $index => $field) {
            if (!$field instanceof SearchFieldConfig) {
                throw new InvalidArgumentException(
                    sprintf('Search field at index %d must be an instance of SearchFieldConfig.', $index),
                    'search_fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates that the minimum score is within the valid range.
     *
     * @throws InvalidArgumentException If min_score is out of range
     */
    private function validateMinScore(?float $minScore): void
    {
        if ($minScore === null) {
            return;
        }

        if ($minScore < self::MIN_SCORE_MIN || $minScore > self::MIN_SCORE_MAX) {
            throw new InvalidArgumentException(
                sprintf(
                    'Minimum score must be between %.1f and %.1f, got %.2f.',
                    self::MIN_SCORE_MIN,
                    self::MIN_SCORE_MAX,
                    $minScore
                ),
                'min_score',
                $minScore
            );
        }
    }
}
