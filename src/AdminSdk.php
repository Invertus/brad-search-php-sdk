<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\AdminHttpClient;
use BradSearch\SyncSdk\Client\Transport\Transport;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Response\AllIndicesResponse;

/**
 * Admin SDK for cluster-wide operations not tied to a specific application.
 *
 * Used for index pruning, listing all indices, and deleting orphaned indices.
 * All requests include the X-Admin-Action: true header required by the Go admin endpoints.
 */
class AdminSdk
{
    private readonly AdminHttpClient $httpClient;

    /**
     * @param Transport|null $transport Override the HTTP transport (tests, custom clients); null uses cURL
     */
    public function __construct(SyncConfig $config, ?Transport $transport = null)
    {
        $this->httpClient = new AdminHttpClient($config, $transport);
    }

    /**
     * List all OpenSearch indices with alias info (system indices excluded by Go).
     */
    public function listAllIndices(): AllIndicesResponse
    {
        $response = $this->httpClient->get('api/v2/admin/indices');

        return AllIndicesResponse::fromArray($response);
    }

    /**
     * Delete a specific index by exact name.
     *
     * @return array<string, mixed> Response with status and message
     */
    public function deleteIndex(string $indexName): array
    {
        return $this->httpClient->delete('api/v2/admin/indices/' . urlencode($indexName));
    }
}
