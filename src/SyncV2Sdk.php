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
}
