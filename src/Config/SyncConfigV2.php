<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Config;

use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

readonly class SyncConfigV2
{
    public function __construct(
        public string $appId,
        public string $apiUrl,
        public string $token
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
    }

    private function isValidUuid(string $uuid): bool
    {
        $hex = '[0-9a-f]';
        $pattern = "/^{$hex}{8}-{$hex}{4}-{$hex}{4}-{$hex}{4}-{$hex}{12}$/i";

        return preg_match($pattern, $uuid) === 1;
    }
}
