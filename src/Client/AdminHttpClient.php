<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client;

use BradSearch\SyncSdk\Client\Transport\Sleeper;
use BradSearch\SyncSdk\Client\Transport\Transport;
use BradSearch\SyncSdk\Config\SyncConfig;

/**
 * HTTP client for admin operations that includes the X-Admin-Action header.
 *
 * Thin wrapper over HttpClient so admin calls share its timeouts and retry policy.
 */
class AdminHttpClient
{
    private readonly HttpClient $httpClient;

    public function __construct(
        SyncConfig $config,
        ?Transport $transport = null,
        ?Sleeper $sleeper = null,
    ) {
        $this->httpClient = new HttpClient($config, $transport, $sleeper, ['X-Admin-Action: true']);
    }

    public function get(string $endpoint): array
    {
        return $this->httpClient->get($endpoint);
    }

    public function delete(string $endpoint): array
    {
        return $this->httpClient->delete($endpoint);
    }
}
