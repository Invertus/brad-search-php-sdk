<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from the admin list all indices endpoint.
 */
final readonly class AllIndicesResponse extends ValueObject
{
    /**
     * @param array<int, IndexEntry> $indices
     * @param int $totalCount
     */
    public function __construct(
        public array $indices,
        public int $totalCount,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $indices = [];
        foreach ($data['indices'] ?? [] as $indexData) {
            $indices[] = IndexEntry::fromArray($indexData);
        }

        return new self(
            indices: $indices,
            totalCount: (int) ($data['total_count'] ?? 0),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'indices' => array_map(
                fn(IndexEntry $entry) => $entry->jsonSerialize(),
                $this->indices,
            ),
            'total_count' => $this->totalCount,
        ];
    }
}
