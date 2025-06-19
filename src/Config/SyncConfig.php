<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Config;

use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

readonly class SyncConfig
{
    public function __construct(
        public string $baseUrl,
        public string $authToken,
        public int $timeout = 30,
        public bool $verifySSL = true
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->baseUrl))) {
            throw new InvalidFieldConfigException('Base URL cannot be empty');
        }

        if (empty(trim($this->authToken))) {
            throw new InvalidFieldConfigException('Auth token cannot be empty');
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidFieldConfigException('Base URL must be a valid URL');
        }

        if ($this->timeout <= 0) {
            throw new InvalidFieldConfigException('Timeout must be greater than 0');
        }
    }
} 