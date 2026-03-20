<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single OpenSearch index with alias info from the admin list endpoint.
 */
final readonly class IndexEntry extends ValueObject
{
    /**
     * @param string $index Index name
     * @param string $health Health status (green, yellow, red)
     * @param string $status Index status (open, close)
     * @param string $docsCount Document count
     * @param string $storeSize Storage size (e.g., "5.2mb")
     * @param array<int, string> $aliases Alias names pointing to this index
     */
    public function __construct(
        public string $index,
        public string $health,
        public string $status,
        public string $docsCount,
        public string $storeSize,
        public array $aliases = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: (string) ($data['index'] ?? ''),
            health: (string) ($data['health'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            docsCount: (string) ($data['docs_count'] ?? '0'),
            storeSize: (string) ($data['store_size'] ?? '0b'),
            aliases: $data['aliases'] ?? [],
        );
    }

    public function hasAlias(string $aliasName): bool
    {
        return in_array($aliasName, $this->aliases, true);
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'health' => $this->health,
            'status' => $this->status,
            'docs_count' => $this->docsCount,
            'store_size' => $this->storeSize,
            'aliases' => $this->aliases,
        ];
    }
}
