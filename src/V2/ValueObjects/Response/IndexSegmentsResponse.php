<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Response of GET /index/segments: merge state of the index behind the alias.
 */
final readonly class IndexSegmentsResponse extends ValueObject
{
    public function __construct(
        public string $index,
        public int $segments,
        public int $deletedDocs,
        public int $storeBytes,
        public int $freeDiskBytes,
        public bool $mergeRunning,
        public bool $rewriteRunning
    ) {
        if (trim($index) === '') {
            throw new InvalidArgumentException('index cannot be empty.', 'index', $index);
        }
    }

    /**
     * @param array<string, mixed> $data Raw API response data
     *
     * @throws InvalidArgumentException If index or segments is missing
     */
    public static function fromArray(array $data): self
    {
        foreach (['index', 'segments'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('Missing required field: %s', $field), $field, null);
            }
        }

        return new self(
            index: (string) $data['index'],
            segments: (int) $data['segments'],
            deletedDocs: (int) ($data['deleted_docs'] ?? 0),
            storeBytes: (int) ($data['store_bytes'] ?? 0),
            freeDiskBytes: (int) ($data['free_disk_bytes'] ?? 0),
            mergeRunning: (bool) ($data['merge_running'] ?? false),
            rewriteRunning: (bool) ($data['rewrite_running'] ?? false)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'segments' => $this->segments,
            'deleted_docs' => $this->deletedDocs,
            'store_bytes' => $this->storeBytes,
            'free_disk_bytes' => $this->freeDiskBytes,
            'merge_running' => $this->mergeRunning,
            'rewrite_running' => $this->rewriteRunning,
        ];
    }
}
