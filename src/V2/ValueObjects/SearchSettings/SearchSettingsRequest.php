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
    /** @var array<string>|null */
    public ?array $supportedLocales;

    /**
     * @param string $appId The application ID for these settings
     * @param SearchConfig|null $searchConfig Optional search configuration (fields, nested fields, multi-match)
     * @param ScoringConfig|null $scoringConfig Optional scoring configuration (function score, min score)
     * @param ResponseConfig|null $responseConfig Optional response configuration (source fields, sortable fields)
     * @param array<string>|null $supportedLocales Optional supported locales
     * @param array<string, mixed>|null $rawQueryConfig Optional raw query config (Go-native format, bypasses SearchConfig VOs)
     */
    public function __construct(
        public string $appId,
        public ?SearchConfig $searchConfig = null,
        public ?ScoringConfig $scoringConfig = null,
        public ?ResponseConfig $responseConfig = null,
        ?array $supportedLocales = null,
        public ?array $rawQueryConfig = null
    ) {
        $this->validateAppId($appId);
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
        );
    }

    /**
     * Returns a new instance with a different app ID.
     */
    public function withAppId(string $appId): self
    {
        return new self($appId, $this->searchConfig, $this->scoringConfig, $this->responseConfig, $this->supportedLocales, $this->rawQueryConfig);
    }

    /**
     * Returns a new instance with a different search config.
     */
    public function withSearchConfig(?SearchConfig $searchConfig): self
    {
        return new self($this->appId, $searchConfig, $this->scoringConfig, $this->responseConfig, $this->supportedLocales, $this->rawQueryConfig);
    }

    /**
     * Returns a new instance with a different scoring config.
     */
    public function withScoringConfig(?ScoringConfig $scoringConfig): self
    {
        return new self($this->appId, $this->searchConfig, $scoringConfig, $this->responseConfig, $this->supportedLocales, $this->rawQueryConfig);
    }

    /**
     * Returns a new instance with a different response config.
     */
    public function withResponseConfig(?ResponseConfig $responseConfig): self
    {
        return new self($this->appId, $this->searchConfig, $this->scoringConfig, $responseConfig, $this->supportedLocales, $this->rawQueryConfig);
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

        return $result;
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
}
