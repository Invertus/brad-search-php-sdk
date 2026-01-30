<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonException;

/**
 * Represents a complete search configuration request.
 *
 * This immutable ValueObject contains the full search configuration including
 * supported locales, query configuration, and response configuration.
 * It supports loading from JSON/array for external configuration sources.
 */
final readonly class SearchConfigurationRequest extends ValueObject
{
    /**
     * @param array<string> $supportedLocales Array of supported locale strings
     * @param QueryConfig|null $queryConfig Query configuration with fields
     * @param ResponseConfig|null $responseConfig Response configuration
     */
    public function __construct(
        public array $supportedLocales = [],
        public ?QueryConfig $queryConfig = null,
        public ?ResponseConfig $responseConfig = null
    ) {
        $this->validateSupportedLocales($supportedLocales);
    }

    /**
     * Creates a SearchConfigurationRequest from a JSON string.
     *
     * @param string $json JSON string containing the configuration
     *
     * @return self
     *
     * @throws InvalidArgumentException If JSON is invalid or data is malformed
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid JSON: %s', $e->getMessage()),
                'json',
                $json
            );
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException(
                'JSON must decode to an array.',
                'json',
                $json
            );
        }

        return self::fromArray($data);
    }

    /**
     * Creates a SearchConfigurationRequest from an array (typically from JSON).
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public static function fromArray(array $data): self
    {
        $supportedLocales = [];
        if (isset($data['supported_locales']) && is_array($data['supported_locales'])) {
            $supportedLocales = $data['supported_locales'];
        }

        $queryConfig = null;
        if (isset($data['query_config']) && is_array($data['query_config'])) {
            $queryConfig = QueryConfig::fromArray($data['query_config']);
        }

        $responseConfig = null;
        if (isset($data['response_config']) && is_array($data['response_config'])) {
            $responseConfig = ResponseConfig::fromArray($data['response_config']);
        }

        return new self(
            supportedLocales: $supportedLocales,
            queryConfig: $queryConfig,
            responseConfig: $responseConfig
        );
    }

    /**
     * Returns a new instance with different supported locales.
     *
     * @param array<string> $supportedLocales
     */
    public function withSupportedLocales(array $supportedLocales): self
    {
        return new self($supportedLocales, $this->queryConfig, $this->responseConfig);
    }

    /**
     * Returns a new instance with an additional supported locale.
     */
    public function withAddedSupportedLocale(string $locale): self
    {
        return new self([...$this->supportedLocales, $locale], $this->queryConfig, $this->responseConfig);
    }

    /**
     * Returns a new instance with different query configuration.
     */
    public function withQueryConfig(?QueryConfig $queryConfig): self
    {
        return new self($this->supportedLocales, $queryConfig, $this->responseConfig);
    }

    /**
     * Returns a new instance with different response configuration.
     */
    public function withResponseConfig(?ResponseConfig $responseConfig): self
    {
        return new self($this->supportedLocales, $this->queryConfig, $responseConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if (count($this->supportedLocales) > 0) {
            $result['supported_locales'] = $this->supportedLocales;
        }

        if ($this->queryConfig !== null) {
            $queryConfigArray = $this->queryConfig->jsonSerialize();
            if (count($queryConfigArray) > 0) {
                $result['query_config'] = $queryConfigArray;
            }
        }

        if ($this->responseConfig !== null) {
            $responseConfigArray = $this->responseConfig->jsonSerialize();
            if (count($responseConfigArray) > 0) {
                $result['response_config'] = $responseConfigArray;
            }
        }

        return $result;
    }

    /**
     * Validates that all supported locales are valid strings.
     *
     * @param array<mixed> $supportedLocales
     * @throws InvalidArgumentException If any locale is invalid
     */
    private function validateSupportedLocales(array $supportedLocales): void
    {
        foreach ($supportedLocales as $index => $locale) {
            if (!is_string($locale)) {
                throw new InvalidArgumentException(
                    sprintf('Supported locale at index %d must be a string.', $index),
                    'supported_locales',
                    $locale
                );
            }

            if ($locale === '') {
                throw new InvalidArgumentException(
                    sprintf('Supported locale at index %d cannot be empty.', $index),
                    'supported_locales',
                    $locale
                );
            }
        }
    }
}
