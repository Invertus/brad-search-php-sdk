<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

/**
 * Configuration for Magento GraphQL connection
 */
readonly class MagentoConfig
{
    /**
     * @param string $graphqlUrl Full URL to Magento GraphQL endpoint (e.g., "https://example.com/graphql")
     * @param string|null $bearerToken Optional bearer token for authenticated requests
     * @param int $timeout Request timeout in seconds
     * @param bool $verifySSL Whether to verify SSL certificates
     * @param int $defaultPageSize Default page size for paginated requests
     */
    public function __construct(
        public string $graphqlUrl,
        public ?string $bearerToken = null,
        public int $timeout = 30,
        public bool $verifySSL = true,
        public int $defaultPageSize = 100,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->graphqlUrl))) {
            throw new InvalidFieldConfigException('GraphQL URL cannot be empty');
        }

        if (!filter_var($this->graphqlUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidFieldConfigException('GraphQL URL must be a valid URL');
        }

        if ($this->timeout <= 0) {
            throw new InvalidFieldConfigException('Timeout must be greater than 0');
        }

        if ($this->defaultPageSize <= 0) {
            throw new InvalidFieldConfigException('Default page size must be greater than 0');
        }

        if ($this->defaultPageSize > 300) {
            throw new InvalidFieldConfigException('Default page size cannot exceed 300 (Magento limit)');
        }
    }
}
