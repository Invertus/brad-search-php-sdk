<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from query configuration API endpoints.
 *
 * This immutable ValueObject contains the configuration data returned from the API:
 * - status: Operation status (e.g., "success")
 * - indexName: The index this configuration applies to
 * - cacheTtlHours: Cache time-to-live in hours
 * - searchFields: Array of search field configurations
 * - popularityBoost: Optional popularity boost configuration
 * - multiWordOperator: Operator for multi-word queries
 * - minScore: Optional minimum score threshold
 */
final readonly class QueryConfigurationResponse extends ValueObject
{
    /**
     * @param string $status Operation status
     * @param string $indexName Index name
     * @param int $cacheTtlHours Cache TTL in hours
     * @param array<SearchFieldConfig> $searchFields Array of search field configurations
     * @param PopularityBoostConfig|null $popularityBoost Optional popularity boost configuration
     * @param MultiWordOperator $multiWordOperator Operator for multi-word queries
     * @param float|null $minScore Optional minimum score threshold
     */
    public function __construct(
        public string $status,
        public string $indexName,
        public int $cacheTtlHours,
        public array $searchFields,
        public ?PopularityBoostConfig $popularityBoost = null,
        public MultiWordOperator $multiWordOperator = MultiWordOperator::AND,
        public ?float $minScore = null
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNotEmpty($indexName, 'index_name');
        $this->validateNonNegative($cacheTtlHours, 'cache_ttl_hours');
        $this->validateSearchFields($searchFields);
    }

    /**
     * Creates a QueryConfigurationResponse from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, [
            'status',
            'index_name',
            'cache_ttl_hours',
            'search_fields',
        ]);

        $searchFields = [];
        foreach ($data['search_fields'] as $fieldData) {
            $searchFields[] = SearchFieldConfig::fromArray($fieldData);
        }

        $popularityBoost = null;
        if (isset($data['popularity_boost']) && is_array($data['popularity_boost'])) {
            $popularityBoost = PopularityBoostConfig::fromArray($data['popularity_boost']);
        }

        $multiWordOperator = MultiWordOperator::AND;
        if (isset($data['multi_word_operator'])) {
            $multiWordOperator = MultiWordOperator::from($data['multi_word_operator']);
        }

        return new self(
            status: (string) $data['status'],
            indexName: (string) $data['index_name'],
            cacheTtlHours: (int) $data['cache_ttl_hours'],
            searchFields: $searchFields,
            popularityBoost: $popularityBoost,
            multiWordOperator: $multiWordOperator,
            minScore: isset($data['min_score']) ? (float) $data['min_score'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'status' => $this->status,
            'index_name' => $this->indexName,
            'cache_ttl_hours' => $this->cacheTtlHours,
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
     * Validates that a string field is not empty.
     *
     * @throws InvalidArgumentException If the value is empty
     */
    private function validateNotEmpty(string $value, string $fieldName): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(
                sprintf('%s cannot be empty.', $fieldName),
                $fieldName,
                $value
            );
        }
    }

    /**
     * Validates that an integer field is non-negative.
     *
     * @throws InvalidArgumentException If the value is negative
     */
    private function validateNonNegative(int $value, string $fieldName): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                sprintf('%s must be non-negative, got %d.', $fieldName, $value),
                $fieldName,
                $value
            );
        }
    }

    /**
     * Validates that all search fields are SearchFieldConfig instances.
     *
     * @param array<mixed> $searchFields
     *
     * @throws InvalidArgumentException If any field is not a SearchFieldConfig
     */
    private function validateSearchFields(array $searchFields): void
    {
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
     * Validates that all required fields are present in the data array.
     *
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     *
     * @throws InvalidArgumentException If a required field is missing
     */
    private static function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    sprintf('Missing required field: %s', $field),
                    $field,
                    null
                );
            }
        }
    }
}
