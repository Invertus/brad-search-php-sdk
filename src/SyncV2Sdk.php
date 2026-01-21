<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Config\SyncConfigV2;

class SyncV2Sdk
{
    private readonly HttpClient $httpClient;
    private readonly string $baseApiPath;

    public function __construct(
        private readonly SyncConfigV2 $config
    ) {
        $syncConfig = new SyncConfig(
            baseUrl: $this->config->apiUrl,
            authToken: $this->config->token
        );

        $this->httpClient = new HttpClient($syncConfig);
        $this->baseApiPath = "api/v2/applications/{$this->config->appId}/";
    }

    public function getAppId(): string
    {
        return $this->config->appId;
    }

    public function getBaseApiPath(): string
    {
        return $this->baseApiPath;
    }

    protected function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Create a versioned index with the given field definitions.
     *
     * @param array<int, array<string, mixed>> $fields Array of field definitions
     *
     * @return array<string, mixed> Raw API response
     */
    public function createIndex(array $fields): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'index',
            ['fields' => $fields]
        );
    }

    /**
     * Get index information including active version and all versions.
     *
     * @return array<string, mixed> Raw API response containing alias_name,
     *                              active_version, active_index, all_versions
     */
    public function getIndexInfo(): array
    {
        return $this->httpClient->get(
            $this->baseApiPath . 'index/info'
        );
    }

    /**
     * List all index versions.
     *
     * @return array<string, mixed> Raw API response with list of versions
     */
    public function listIndexVersions(): array
    {
        return $this->httpClient->get(
            $this->baseApiPath . 'index/versions'
        );
    }

    /**
     * Activate a specific index version for zero-downtime migrations and rollbacks.
     *
     * @param int $version The version number to activate
     *
     * @return array<string, mixed> Raw API response containing previous_version,
     *                              new_version, alias_name
     */
    public function activateIndexVersion(int $version): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'index/activate',
            ['version' => $version]
        );
    }

    /**
     * Delete a specific index version.
     *
     * @param int $version The version number to delete
     *
     * @return array<string, mixed> Raw API response with status and message
     */
    public function deleteIndexVersion(int $version): array
    {
        return $this->httpClient->delete(
            $this->baseApiPath . 'index/version/' . $version
        );
    }

    /**
     * Set query configuration for search behavior.
     *
     * @param array<string, mixed> $config Configuration options (search_fields, fuzzy_matching, etc.)
     *
     * @return array<string, mixed> Raw API response containing status, index_name, cache_ttl_hours
     */
    public function setConfiguration(array $config): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'configuration',
            $config
        );
    }

    /**
     * Get query configuration.
     *
     * @return array<string, mixed> Raw API response with configuration data
     */
    public function getConfiguration(): array
    {
        return $this->httpClient->get(
            $this->baseApiPath . 'configuration'
        );
    }

    /**
     * Update query configuration.
     *
     * @param array<string, mixed> $config Configuration options to update
     *
     * @return array<string, mixed> Raw API response
     */
    public function updateConfiguration(array $config): array
    {
        return $this->httpClient->put(
            $this->baseApiPath . 'configuration',
            $config
        );
    }

    /**
     * Delete query configuration.
     *
     * @return array<string, mixed> Raw API response
     */
    public function deleteConfiguration(): array
    {
        return $this->httpClient->delete(
            $this->baseApiPath . 'configuration'
        );
    }

    /**
     * Set search synonyms for a specific language.
     *
     * @param string $language Language code (e.g., "en", "lt")
     * @param array<int, array<int, string>> $synonyms Array of synonym groups
     *
     * @return array<string, mixed> Raw API response containing language, synonym_count, requires_reindex
     */
    public function setSynonyms(string $language, array $synonyms): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'synonyms',
            [
                'language' => $language,
                'synonyms' => $synonyms,
            ]
        );
    }

    /**
     * Get search synonyms for a specific language.
     *
     * @param string $language Language code (e.g., "en", "lt")
     *
     * @return array<string, mixed> Raw API response with synonyms data
     */
    public function getSynonyms(string $language): array
    {
        return $this->httpClient->get(
            $this->baseApiPath . 'synonyms?language=' . $language
        );
    }

    /**
     * Delete search synonyms for a specific language.
     *
     * @param string $language Language code (e.g., "en", "lt")
     *
     * @return array<string, mixed> Raw API response
     */
    public function deleteSynonyms(string $language): array
    {
        return $this->httpClient->delete(
            $this->baseApiPath . 'synonyms?language=' . $language
        );
    }
}
