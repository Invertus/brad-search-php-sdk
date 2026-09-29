<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the complete search settings request.
 *
 * This immutable ValueObject aggregates all search settings configurations including
 * search config, scoring config, and response config for a full search settings request.
 */
final readonly class SearchSettingsRequest extends ValueObject
{
    private const LANGUAGE_PATTERN = '/^[a-z]{2}$/';

    /** @var array<string>|null */
    public ?array $supportedLocales;

    /**
     * @param string $appId The application ID for these settings
     * @param SearchConfig|null $searchConfig Optional search configuration (fields, nested fields, multi-match)
     * @param ScoringConfig|null $scoringConfig Optional scoring configuration (function score, min score)
     * @param ResponseConfig|null $responseConfig Optional response configuration (source fields, sortable fields)
     * @param array<string>|null $supportedLocales Optional supported locales
     * @param array<string, mixed>|null $rawQueryConfig Optional raw query config (Go-native format, bypasses SearchConfig VOs)
     * @param array<string, mixed>|null $filterConfig Optional filter configuration for facets/aggregations
     * @param string|null $similarity Optional similarity algorithm applied to text fields in the index mapping (e.g. "boolean")
     * @param array<string, array<SynonymRule>>|null $synonymRules Optional query-time synonym rules keyed by ISO 639-1 language code
     */
    public function __construct(
        public string $appId,
        public ?SearchConfig $searchConfig = null,
        public ?ScoringConfig $scoringConfig = null,
        public ?ResponseConfig $responseConfig = null,
        ?array $supportedLocales = null,
        public ?array $rawQueryConfig = null,
        public ?array $filterConfig = null,
        public ?array $featuresKeyValueMap = null,
        public ?array $attributeKeyValueMap = null,
        public ?string $similarity = null,
        public ?array $synonymRules = null,
    ) {
        $this->validateAppId($appId);
        $this->validateSynonymRules($synonymRules);
        $this->supportedLocales = $supportedLocales;
    }

    /**
     * Create a SearchSettingsRequest from a search configuration array (Go-native format).
     *
     * @param string $appId The application ID
     * @param array<string, mixed> $config The search configuration array with query_config, response_config, supported_locales
     */
    public static function fromSearchConfiguration(string $appId, array $config): self
    {
        return new self(
            appId: $appId,
            supportedLocales: $config['supported_locales'] ?? null,
            rawQueryConfig: $config['query_config'] ?? null,
            responseConfig: isset($config['response_config'])
                ? ResponseConfig::fromArray($config['response_config'])
                : null,
            filterConfig: $config['filter_config'] ?? null,
            featuresKeyValueMap: $config['features_key_value_map'] ?? null,
            attributeKeyValueMap: $config['attribute_key_value_map'] ?? null,
            similarity: $config['similarity'] ?? null,
            synonymRules: isset($config['synonym_rules']) && is_array($config['synonym_rules'])
                ? self::synonymRulesFromArray($config['synonym_rules'])
                : null,
        );
    }

    /**
     * Parses the `synonym_rules` payload shape (language => list of rule arrays).
     *
     * @param array<string, mixed> $data
     * @return array<string, array<SynonymRule>>
     *
     * @throws InvalidArgumentException If a language entry is not a list or a rule is malformed
     */
    public static function synonymRulesFromArray(array $data): array
    {
        $rules = [];
        foreach ($data as $language => $ruleList) {
            if (!is_array($ruleList)) {
                throw new InvalidArgumentException(
                    sprintf('Synonym rules for language "%s" must be a list.', (string) $language),
                    'synonym_rules',
                    $ruleList
                );
            }
            $rules[(string) $language] = array_map(
                static fn(mixed $rule): SynonymRule => $rule instanceof SynonymRule
                    ? $rule
                    : SynonymRule::fromArray(is_array($rule) ? $rule : []),
                array_values($ruleList)
            );
        }

        return $rules;
    }

    /**
     * Returns a new instance with a different app ID.
     */
    public function withAppId(string $appId): self
    {
        return $this->copyWith(appId: $appId);
    }

    /**
     * Returns a new instance with a different search config.
     */
    public function withSearchConfig(?SearchConfig $searchConfig): self
    {
        return $this->copyWith(searchConfig: $searchConfig, clearSearchConfig: $searchConfig === null);
    }

    /**
     * Returns a new instance with a different scoring config.
     */
    public function withScoringConfig(?ScoringConfig $scoringConfig): self
    {
        return $this->copyWith(scoringConfig: $scoringConfig, clearScoringConfig: $scoringConfig === null);
    }

    /**
     * Returns a new instance with a different response config.
     */
    public function withResponseConfig(?ResponseConfig $responseConfig): self
    {
        return $this->copyWith(responseConfig: $responseConfig, clearResponseConfig: $responseConfig === null);
    }

    /**
     * Returns a new instance with a different similarity setting.
     */
    public function withSimilarity(?string $similarity): self
    {
        return $this->copyWith(similarity: $similarity, clearSimilarity: $similarity === null);
    }

    /**
     * Returns a new instance with different synonym rules.
     *
     * @param array<string, array<SynonymRule>>|null $synonymRules Rules keyed by ISO 639-1 language code
     */
    public function withSynonymRules(?array $synonymRules): self
    {
        return $this->copyWith(synonymRules: $synonymRules, clearSynonymRules: $synonymRules === null);
    }

    /**
     * Returns a new instance with one more synonym rule for a language.
     *
     * @throws InvalidArgumentException If the new rule targets a field the query configuration lacks or nests
     */
    public function withAddedSynonymRule(string $language, SynonymRule $rule): self
    {
        $this->assertRuleTargetsSearchableField($rule);

        $rules = $this->synonymRules ?? [];
        $rules[$language] = [...($rules[$language] ?? []), $rule];

        return $this->copyWith(synonymRules: $rules);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'app_id' => $this->appId,
        ];

        if ($this->supportedLocales !== null && count($this->supportedLocales) > 0) {
            $result['supported_locales'] = $this->supportedLocales;
        }

        // rawQueryConfig takes precedence over searchConfig for query_config key
        if ($this->rawQueryConfig !== null) {
            $result['query_config'] = $this->rawQueryConfig;
        } elseif ($this->searchConfig !== null) {
            $searchConfigData = $this->searchConfig->jsonSerialize();
            if (count($searchConfigData) > 0) {
                $result['query_config'] = $searchConfigData;
            }
        }

        if ($this->scoringConfig !== null) {
            $scoringConfigData = $this->scoringConfig->jsonSerialize();
            if (count($scoringConfigData) > 0) {
                $result['scoring_config'] = $scoringConfigData;
            }
        }

        if ($this->responseConfig !== null) {
            $responseConfigData = $this->responseConfig->jsonSerialize();
            if (count($responseConfigData) > 0) {
                $result['response_config'] = $responseConfigData;
            }
        }

        if ($this->filterConfig !== null && count($this->filterConfig) > 0) {
            $result['filter_config'] = $this->filterConfig;
        }
        if ($this->featuresKeyValueMap !== null && count($this->featuresKeyValueMap) > 0) {
            $result['features_key_value_map'] = $this->featuresKeyValueMap;
        }

        if ($this->attributeKeyValueMap !== null && count($this->attributeKeyValueMap) > 0) {
            $result['attribute_key_value_map'] = $this->attributeKeyValueMap;
        }

        if ($this->similarity !== null && $this->similarity !== '') {
            $result['similarity'] = $this->similarity;
        }

        if ($this->synonymRules !== null && count($this->synonymRules) > 0) {
            $result['synonym_rules'] = array_map(
                static fn(array $rules): array => array_values(array_map(
                    static fn(SynonymRule $rule): array => $rule->jsonSerialize(),
                    $rules
                )),
                $this->synonymRules
            );
        }

        return $result;
    }

    /**
     * Copies this request with selected parts replaced. Nullable parts need an
     * explicit clear flag because null also means "keep".
     *
     * @param array<string, array<SynonymRule>>|null $synonymRules
     */
    private function copyWith(
        ?string $appId = null,
        ?SearchConfig $searchConfig = null,
        bool $clearSearchConfig = false,
        ?ScoringConfig $scoringConfig = null,
        bool $clearScoringConfig = false,
        ?ResponseConfig $responseConfig = null,
        bool $clearResponseConfig = false,
        ?string $similarity = null,
        bool $clearSimilarity = false,
        ?array $synonymRules = null,
        bool $clearSynonymRules = false,
    ): self {
        return new self(
            $appId ?? $this->appId,
            $clearSearchConfig ? null : ($searchConfig ?? $this->searchConfig),
            $clearScoringConfig ? null : ($scoringConfig ?? $this->scoringConfig),
            $clearResponseConfig ? null : ($responseConfig ?? $this->responseConfig),
            $this->supportedLocales,
            $this->rawQueryConfig,
            $this->filterConfig,
            $this->featuresKeyValueMap,
            $this->attributeKeyValueMap,
            $clearSimilarity ? null : ($similarity ?? $this->similarity),
            $clearSynonymRules ? null : ($synonymRules ?? $this->synonymRules),
        );
    }

    /**
     * Validates that the app ID is not empty.
     *
     * @throws InvalidArgumentException If app ID is empty
     */
    private function validateAppId(string $appId): void
    {
        if ($appId === '') {
            throw new InvalidArgumentException(
                'Application ID cannot be empty.',
                'app_id',
                $appId
            );
        }
    }

    /**
     * Checks each rule targets a top-level, non-nested query configuration field.
     * Not run on construction, so a stored config with a stale rule still loads.
     *
     * @throws InvalidArgumentException
     */
    public function validateSynonymRuleFields(): void
    {
        foreach ($this->synonymRules ?? [] as $rules) {
            foreach ($rules as $rule) {
                $this->assertRuleTargetsSearchableField($rule);
            }
        }
    }

    /**
     * Validates the synonym rules shape: language keys are ISO 639-1 codes and
     * every entry is a SynonymRule. Target fields are checked by
     * validateSynonymRuleFields().
     *
     * @param array<string, mixed>|null $synonymRules
     *
     * @throws InvalidArgumentException
     */
    private function validateSynonymRules(?array $synonymRules): void
    {
        if ($synonymRules === null) {
            return;
        }

        foreach ($synonymRules as $language => $rules) {
            if (!is_string($language) || preg_match(self::LANGUAGE_PATTERN, $language) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Synonym rules language "%s" must be an ISO 639-1 code (e.g. "lt").', (string) $language),
                    'synonym_rules',
                    $language
                );
            }
            if (!is_array($rules)) {
                throw new InvalidArgumentException(
                    sprintf('Synonym rules for language "%s" must be a list.', $language),
                    'synonym_rules',
                    $rules
                );
            }
            foreach ($rules as $rule) {
                if (!$rule instanceof SynonymRule) {
                    throw new InvalidArgumentException(
                        sprintf('Synonym rules for language "%s" must contain SynonymRule instances.', $language),
                        'synonym_rules',
                        $rule
                    );
                }
            }
        }
    }

    /**
     * @throws InvalidArgumentException If the rule's field is missing from, or nested in, the query configuration
     */
    private function assertRuleTargetsSearchableField(SynonymRule $rule): void
    {
        $fieldTypes = $this->configuredFieldTypes();
        if ($fieldTypes === null) {
            return;
        }

        if (!array_key_exists($rule->field, $fieldTypes)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Synonym rule "%s" targets field "%s", which is not in the query configuration.',
                    $rule->when,
                    $rule->field
                ),
                'synonym_rules',
                $rule->field
            );
        }

        if ($fieldTypes[$rule->field] === QueryFieldType::NESTED->value) {
            throw new InvalidArgumentException(
                sprintf(
                    'Synonym rule "%s" targets field "%s", which is a nested field.',
                    $rule->when,
                    $rule->field
                ),
                'synonym_rules',
                $rule->field
            );
        }
    }

    /**
     * Top-level field names of the query configuration mapped to their type,
     * or null when the request carries no field list to check against.
     *
     * @return array<string, string|null>|null
     */
    private function configuredFieldTypes(): ?array
    {
        if ($this->rawQueryConfig !== null) {
            $fields = $this->rawQueryConfig['fields'] ?? null;
            if (!is_array($fields)) {
                return null;
            }

            $types = [];
            foreach ($fields as $field) {
                if (is_array($field) && isset($field['name']) && is_string($field['name'])) {
                    $types[$field['name']] = isset($field['type']) && is_string($field['type']) ? $field['type'] : null;
                }
            }

            return $types;
        }

        if ($this->searchConfig !== null) {
            $types = [];
            foreach ($this->searchConfig->fields as $field) {
                $types[$field->fieldName] = null;
            }

            return $types;
        }

        return null;
    }
}
