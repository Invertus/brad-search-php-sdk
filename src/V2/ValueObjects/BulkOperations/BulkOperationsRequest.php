<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a bulk operations request containing multiple operations.
 *
 * This immutable ValueObject is the top-level request structure for
 * the bulk-operations API endpoint.
 */
final readonly class BulkOperationsRequest extends ValueObject
{
    /**
     * @param array<int, BulkOperation> $operations Operations to execute
     * @param string|null $targetIndex Optional physical index name to target directly (bypasses alias resolution)
     */
    public function __construct(
        public array $operations,
        public ?string $targetIndex = null,
    ) {
        $this->validateOperations($operations);
    }

    /**
     * Returns a new instance with different operations.
     *
     * @param array<int, BulkOperation> $operations
     */
    public function withOperations(array $operations): self
    {
        return new self($operations, $this->targetIndex);
    }

    /**
     * Returns a new instance with an added operation.
     */
    public function withAddedOperation(BulkOperation $operation): self
    {
        $operations = $this->operations;
        $operations[] = $operation;

        return new self($operations, $this->targetIndex);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'operations' => array_map(
                fn(BulkOperation $operation) => $operation->jsonSerialize(),
                $this->operations
            ),
        ];

        if ($this->targetIndex !== null) {
            $data['target_index'] = $this->targetIndex;
        }

        return $data;
    }

    /**
     * @param array<int, BulkOperation> $operations
     * @throws InvalidArgumentException
     */
    private function validateOperations(array $operations): void
    {
        if (count($operations) === 0) {
            throw new InvalidArgumentException(
                'At least one operation is required.',
                'operations',
                $operations
            );
        }

        foreach ($operations as $index => $operation) {
            if (!$operation instanceof BulkOperation) {
                throw new InvalidArgumentException(
                    sprintf('Operation at index %d must be an instance of BulkOperation.', $index),
                    'operations',
                    $operation
                );
            }
        }
    }
}
