<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Builder for creating QueryConfigurationRequest ValueObjects with fluent API.
 */
final class QueryConfigurationRequestBuilder
{
    /** @var array<SearchFieldConfig> */
    private array $searchFields = [];

    private ?FuzzyMatchingConfig $fuzzyMatching = null;

    private ?PopularityBoostConfig $popularityBoost = null;

    private MultiWordOperator $multiWordOperator = MultiWordOperator::AND;

    private ?float $minScore = null;

    /**
     * Adds a search field configuration.
     */
    public function addSearchField(SearchFieldConfig $searchField): self
    {
        $this->searchFields[] = $searchField;
        return $this;
    }

    /**
     * Sets the fuzzy matching configuration.
     */
    public function fuzzyMatching(FuzzyMatchingConfig $fuzzyMatching): self
    {
        $this->fuzzyMatching = $fuzzyMatching;
        return $this;
    }

    /**
     * Sets the popularity boost configuration.
     */
    public function popularityBoost(PopularityBoostConfig $popularityBoost): self
    {
        $this->popularityBoost = $popularityBoost;
        return $this;
    }

    /**
     * Sets the multi-word operator.
     */
    public function multiWordOperator(MultiWordOperator $multiWordOperator): self
    {
        $this->multiWordOperator = $multiWordOperator;
        return $this;
    }

    /**
     * Sets the minimum score threshold.
     */
    public function minScore(float $minScore): self
    {
        $this->minScore = $minScore;
        return $this;
    }

    /**
     * Builds and returns the immutable QueryConfigurationRequest.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): QueryConfigurationRequest
    {
        if (count($this->searchFields) === 0) {
            throw new InvalidArgumentException(
                'At least one search field is required.',
                'search_fields',
                $this->searchFields
            );
        }

        return new QueryConfigurationRequest(
            $this->searchFields,
            $this->fuzzyMatching,
            $this->popularityBoost,
            $this->multiWordOperator,
            $this->minScore
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->searchFields = [];
        $this->fuzzyMatching = null;
        $this->popularityBoost = null;
        $this->multiWordOperator = MultiWordOperator::AND;
        $this->minScore = null;
        return $this;
    }
}
