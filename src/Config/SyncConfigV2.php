<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Config;

use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

readonly class SyncConfigV2
{
    /**
     * @param int $timeout Total request timeout in seconds
     * @param int $connectTimeout Connection-establishment timeout in seconds
     * @param RetryPolicy $retryPolicy Retry budget applied to idempotent requests only
     */
    public function __construct(
        public string $appId,
        public string $apiUrl,
        public string $token,
        public ?string $targetIndex = null,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->appId))) {
            throw new InvalidFieldConfigException('App ID cannot be empty');
        }

        if (empty(trim($this->apiUrl))) {
            throw new InvalidFieldConfigException('API URL cannot be empty');
        }

        if (empty(trim($this->token))) {
            throw new InvalidFieldConfigException('Token cannot be empty');
        }

        if (!$this->isValidUuid($this->appId)) {
            throw new InvalidFieldConfigException('App ID must be a valid UUID');
        }

        if (!filter_var($this->apiUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidFieldConfigException('API URL must be a valid URL');
        }

        if ($this->timeout <= 0) {
            throw new InvalidFieldConfigException('Timeout must be greater than 0');
        }

        if ($this->connectTimeout <= 0) {
            throw new InvalidFieldConfigException('Connect timeout must be greater than 0');
        }
    }

    private function isValidUuid(string $uuid): bool
    {
        $hex = '[0-9a-f]';
        $pattern = "/^{$hex}{8}-{$hex}{4}-{$hex}{4}-{$hex}{4}-{$hex}{12}$/i";

        return preg_match($pattern, $uuid) === 1;
    }
}
