<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexCreationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexInfoResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\QueryConfigurationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\SynonymResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\VersionActivateResponse;
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
     * @return IndexCreationResponse Typed response object
     */
    public function createIndex(IndexCreateRequest $request): IndexCreationResponse
    {
        $response = $this->httpClient->post(
            $this->baseApiPath . 'index',
            $request->jsonSerialize()
        );

        return IndexCreationResponse::fromArray($response);
    }

    /**
     * Get index information including active version and all versions.
     *
     * @return IndexInfoResponse Typed response containing alias_name,
     *                           active_version, active_index, all_versions
     */
    public function getIndexInfo(): IndexInfoResponse
    {
        $response = $this->httpClient->get(
            $this->baseApiPath . 'index/info'
        );

        return IndexInfoResponse::fromArray($response);
    }

    /**
     * List all index versions.
     *
     * @return IndexInfoResponse Typed response with list of versions
     */
    public function listIndexVersions(): IndexInfoResponse
    {
        $response = $this->httpClient->get(
            $this->baseApiPath . 'index/versions'
        );

        return IndexInfoResponse::fromArray($response);
    }

    /**
     * Activate a specific index version for zero-downtime migrations and rollbacks.
     *
     * @param int $version The version number to activate
     *
     * @return VersionActivateResponse Typed response containing previous_version,
     *                                 new_version, alias_name
     */
    public function activateIndexVersion(int $version): VersionActivateResponse
    {
        $response = $this->httpClient->post(
            $this->baseApiPath . 'index/activate',
            ['version' => $version]
        );

        return VersionActivateResponse::fromArray($response);
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
     * @return QueryConfigurationResponse Typed response containing status, index_name, cache_ttl_hours
     */
    public function setConfiguration(QueryConfigurationRequest $config): QueryConfigurationResponse
    {
        $response = $this->httpClient->post(
            $this->baseApiPath . 'configuration',
            $config->jsonSerialize()
        );

        return QueryConfigurationResponse::fromArray($response);
    }

    /**
     * Get query configuration.
     *
     * @return QueryConfigurationResponse Typed response with configuration data
     */
    public function getConfiguration(): QueryConfigurationResponse
    {
        $response = $this->httpClient->get(
            $this->baseApiPath . 'configuration'
        );

        return QueryConfigurationResponse::fromArray($response);
    }

    /**
     * Update query configuration.
     *
     * @param array<string, mixed> $config Configuration options to update
     *
     * @return QueryConfigurationResponse Typed response
     */
    public function updateConfiguration(array $config): QueryConfigurationResponse
    {
        $response = $this->httpClient->put(
            $this->baseApiPath . 'configuration',
            $config
        );

        return QueryConfigurationResponse::fromArray($response);
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
     * @return SynonymResponse Typed response containing language, synonym_count, requires_reindex
     */
    public function setSynonyms(SynonymConfiguration $config): SynonymResponse
    {
        $response = $this->httpClient->post(
            $this->baseApiPath . 'synonyms',
            $config->jsonSerialize()
        );

        return SynonymResponse::fromArray($response);
    }

    /**
     * Get search synonyms for a specific language.
     *
     * @param string $language Language code (e.g., "en", "lt")
     *
     * @return SynonymResponse Typed response with synonyms data
     */
    public function getSynonyms(string $language): SynonymResponse
    {
        $response = $this->httpClient->get(
            $this->baseApiPath . 'synonyms?language=' . $language
        );

        return SynonymResponse::fromArray($response);
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
     * @return BulkOperationsResponse Typed response with operation results
     */
    public function bulkOperations(BulkOperationsRequest $request): BulkOperationsResponse
    {
        $response = $this->httpClient->post(
            $this->baseApiPath . 'sync/bulk-operations',
            $request->jsonSerialize()
        );

        return BulkOperationsResponse::fromArray($response);
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
