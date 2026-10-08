<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Response of POST /index/forcemerge: the merge runs in the background on the
 * index behind the alias; follow it with the OpenSearch task id.
 */
final readonly class ForceMergeResponse extends ValueObject
{
    public function __construct(
        public string $status,
        public string $index,
        public string $task,
        public int $maxNumSegments
    ) {
        foreach (['status' => $status, 'index' => $index, 'task' => $task] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field), $field, $value);
            }
        }
        if ($maxNumSegments < 1) {
            throw new InvalidArgumentException(
                sprintf('max_num_segments must be at least 1, got %d.', $maxNumSegments),
                'max_num_segments',
                $maxNumSegments
            );
        }
    }

    /**
     * @param array<string, mixed> $data Raw API response data
     *
     * @throws InvalidArgumentException If a required field is missing or empty
     */
    public static function fromArray(array $data): self
    {
        foreach (['status', 'index', 'task'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('Missing required field: %s', $field), $field, null);
            }
        }

        return new self(
            status: (string) $data['status'],
            index: (string) $data['index'],
            task: (string) $data['task'],
            maxNumSegments: (int) ($data['max_num_segments'] ?? 1)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'index' => $this->index,
            'task' => $this->task,
            'max_num_segments' => $this->maxNumSegments,
        ];
    }
}
