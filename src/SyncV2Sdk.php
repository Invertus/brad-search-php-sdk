<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Synonym\SynonymConfiguration;

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
     * Create a versioned index with the given request.
     *
     * @param IndexCreateRequest $request The index creation request
     *
     * @return array<string, mixed> Raw API response
     */
    public function createIndex(IndexCreateRequest $request): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'index',
            $request->jsonSerialize()
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
     * @param QueryConfigurationRequest $config The query configuration request
     *
     * @return array<string, mixed> Raw API response containing status, index_name, cache_ttl_hours
     */
    public function setConfiguration(QueryConfigurationRequest $config): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'configuration',
            $config->jsonSerialize()
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
     * @param SynonymConfiguration $config The synonym configuration
     *
     * @return array<string, mixed> Raw API response containing language, synonym_count, requires_reindex
     */
    public function setSynonyms(SynonymConfiguration $config): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'synonyms',
            $config->jsonSerialize()
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

    /**
     * Perform bulk product operations (index, update, delete).
     *
     * @param BulkOperationsRequest $request The bulk operations request
     *
     * @return array<string, mixed> Raw API response with operation results
     */
    public function bulkOperations(BulkOperationsRequest $request): array
    {
        return $this->httpClient->post(
            $this->baseApiPath . 'sync/bulk-operations',
            $request->jsonSerialize()
        );
    }

    /**
     * Create search settings.
     *
     * @param SearchSettingsRequest $settings Search settings configuration
     *
     * @return array<string, mixed> Raw API response
     */
    public function createSearchSettings(SearchSettingsRequest $settings): array
    {
        return $this->httpClient->post(
            'api/v2/configuration',
            $settings->jsonSerialize()
        );
    }

    /**
     * Get search settings for a specific application.
     *
     * @param string $appId Application ID
     *
     * @return array<string, mixed> Raw API response with settings data
     */
    public function getSearchSettings(string $appId): array
    {
        return $this->httpClient->get(
            'api/v2/configuration/' . $appId
        );
    }

    /**
     * Update search settings for a specific application.
     *
     * @param string $appId Application ID
     * @param array<string, mixed> $settings Search settings to update
     *
     * @return array<string, mixed> Raw API response
     */
    public function updateSearchSettings(string $appId, array $settings): array
    {
        return $this->httpClient->put(
            'api/v2/configuration/' . $appId,
            $settings
        );
    }

    /**
     * Delete search settings for a specific application.
     *
     * @param string $appId Application ID
     *
     * @return array<string, mixed> Raw API response
     */
    public function deleteSearchSettings(string $appId): array
    {
        return $this->httpClient->delete(
            'api/v2/configuration/' . $appId
        );
    }
}
