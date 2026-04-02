<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Builder for creating SearchSettingsRequest ValueObjects with fluent API.
 *
 * Provides a convenient way to construct complex search settings configurations
 * with method chaining and clear, descriptive methods.
 */
final class SearchSettingsRequestBuilder
{
    private ?string $appId = null;

    /** @var array<FieldConfig> */
    private array $fields = [];

    /** @var array<NestedFieldConfig> */
    private array $nestedFields = [];

    /** @var array<MultiMatchConfig> */
    private array $multiMatchConfigs = [];

    private ?FunctionScoreConfig $functionScore = null;

    private ?float $minScore = null;

    /** @var array<string> */
    private array $sourceFields = [];

    /** @var array<string> */
    private array $sortableFields = [];

    /** @var array<string>|null */
    private ?array $supportedLocales = null;

    /** @var array<string, mixed>|null */
    private ?array $rawQueryConfig = null;

    /** @var array<string, mixed>|null */
    private ?array $filterConfig = null;
    
    /** @var array<string, array<string, string>>|null */
    private ?array $featuresKeyValueMap = null;

    /** @var array<string, array<string, string>>|null */
    private ?array $attributeKeyValueMap = null;

    /**
     * Sets the application ID.
     */
    public function appId(string $appId): self
    {
        $this->appId = $appId;
        return $this;
    }

    /**
     * Adds a field configuration.
     */
    public function addField(FieldConfig $field): self
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Adds a nested field configuration.
     */
    public function addNestedField(NestedFieldConfig $nestedField): self
    {
        $this->nestedFields[] = $nestedField;
        return $this;
    }

    /**
     * Adds a multi-match configuration.
     */
    public function addMultiMatchConfig(MultiMatchConfig $multiMatchConfig): self
    {
        $this->multiMatchConfigs[] = $multiMatchConfig;
        return $this;
    }

    /**
     * Sets the function score configuration.
     */
    public function functionScore(FunctionScoreConfig $functionScore): self
    {
        $this->functionScore = $functionScore;
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
     * Adds a source field.
     */
    public function addSourceField(string $sourceField): self
    {
        $this->sourceFields[] = $sourceField;
        return $this;
    }

    /**
     * Sets all source fields at once.
     *
     * @param array<string> $sourceFields
     */
    public function sourceFields(array $sourceFields): self
    {
        $this->sourceFields = $sourceFields;
        return $this;
    }

    /**
     * Adds a sortable field.
     */
    public function addSortableField(string $sortableField): self
    {
        $this->sortableFields[] = $sortableField;
        return $this;
    }

    /**
     * Sets all sortable fields at once.
     *
     * @param array<string> $sortableFields
     */
    public function sortableFields(array $sortableFields): self
    {
        $this->sortableFields = $sortableFields;
        return $this;
    }

    /**
     * Sets supported locales.
     *
     * @param array<string> $locales
     */
    public function supportedLocales(array $locales): self
    {
        $this->supportedLocales = $locales;
        return $this;
    }

    /**
     * Sets raw query config (Go-native format, bypasses SearchConfig VOs).
     *
     * @param array<string, mixed> $config
     */
    public function rawQueryConfig(array $config): self
    {
        $this->rawQueryConfig = $config;
        return $this;
    }

    /**
     * Sets filter configuration for facets/aggregations.
     *
     * @param array<string, mixed> $filterConfig
     */
    public function filterConfig(array $filterConfig): self
    {
        $this->filterConfig = $filterConfig;
        return $this;
    }
    
    /**
     * Sets the features key-value map for facet name translation.
     *
     * @param array<string, array<string, string>> $map Feature ID → locale → display name
     */
    public function featuresKeyValueMap(array $map): self
    {
        $this->featuresKeyValueMap = $map;
        return $this;
    }

    /**
     * Sets the attribute key-value map for facet name translation.
     *
     * @param array<string, array<string, string>> $map Attribute ID → locale → display name
     */
    public function attributeKeyValueMap(array $map): self
    {
        $this->attributeKeyValueMap = $map;
        return $this;
    }

    /**
     * Sets the complete search config.
     */
    public function searchConfig(SearchConfig $searchConfig): self
    {
        $this->fields = [];
        $this->nestedFields = [];
        $this->multiMatchConfigs = [];

        foreach ($searchConfig->fields as $field) {
            $this->fields[] = $field;
        }

        foreach ($searchConfig->nestedFields as $nestedField) {
            $this->nestedFields[] = $nestedField;
        }

        foreach ($searchConfig->multiMatchConfigs as $config) {
            $this->multiMatchConfigs[] = $config;
        }

        return $this;
    }

    /**
     * Sets the complete scoring config.
     */
    public function scoringConfig(ScoringConfig $scoringConfig): self
    {
        $this->functionScore = $scoringConfig->functionScore;
        $this->minScore = $scoringConfig->minScore;
        return $this;
    }

    /**
     * Sets the complete response config.
     */
    public function responseConfig(ResponseConfig $responseConfig): self
    {
        $this->sourceFields = $responseConfig->sourceFields;
        $this->sortableFields = $responseConfig->sortableFields;
        return $this;
    }

    /**
     * Builds and returns the immutable SearchSettingsRequest.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): SearchSettingsRequest
    {
        if ($this->appId === null || $this->appId === '') {
            throw new InvalidArgumentException(
                'Application ID is required.',
                'app_id',
                $this->appId
            );
        }

        $searchConfig = null;
        if (count($this->fields) > 0 || count($this->nestedFields) > 0 || count($this->multiMatchConfigs) > 0) {
            $searchConfig = new SearchConfig(
                $this->fields,
                $this->nestedFields,
                $this->multiMatchConfigs
            );
        }

        $scoringConfig = null;
        if ($this->functionScore !== null || $this->minScore !== null) {
            $scoringConfig = new ScoringConfig(
                $this->functionScore,
                $this->minScore
            );
        }

        $responseConfig = null;
        if (count($this->sourceFields) > 0 || count($this->sortableFields) > 0) {
            $responseConfig = new ResponseConfig(
                $this->sourceFields,
                $this->sortableFields
            );
        }

        return new SearchSettingsRequest(
            $this->appId,
            $searchConfig,
            $scoringConfig,
            $responseConfig,
            $this->supportedLocales,
            $this->rawQueryConfig,
            $this->filterConfig,
            $this->featuresKeyValueMap,
            $this->attributeKeyValueMap,
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->appId = null;
        $this->fields = [];
        $this->nestedFields = [];
        $this->multiMatchConfigs = [];
        $this->functionScore = null;
        $this->minScore = null;
        $this->sourceFields = [];
        $this->sortableFields = [];
        $this->supportedLocales = null;
        $this->rawQueryConfig = null;
        $this->filterConfig = null;
        $this->featuresKeyValueMap = null;
        $this->attributeKeyValueMap = null;
        return $this;
    }
}
