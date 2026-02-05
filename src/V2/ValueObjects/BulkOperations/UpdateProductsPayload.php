<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the payload for an update_products bulk operation.
 *
 * This immutable ValueObject contains flexible update arrays allowing partial updates.
 * Only the 'id' field is required in each update - all other fields are optional.
 */
final readonly class UpdateProductsPayload extends ValueObject
{
    /**
     * @param array<int, array<string, mixed>> $updates Array of update objects with flexible fields
     */
    public function __construct(
        public array $updates
    ) {
        $this->validateUpdates($updates);
    }

    /**
     * Returns a new instance with different updates.
     *
     * @param array<int, array<string, mixed>> $updates
     */
    public function withUpdates(array $updates): self
    {
        return new self($updates);
    }

    /**
     * Returns a new instance with an added update.
     *
     * @param array<string, mixed> $update
     */
    public function withAddedUpdate(array $update): self
    {
        $updates = $this->updates;
        $updates[] = $update;

        return new self($updates);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'updates' => $this->updates,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $updates
     * @throws InvalidArgumentException
     */
    private function validateUpdates(array $updates): void
    {
        if (count($updates) === 0) {
            throw new InvalidArgumentException(
                'At least one update is required in the payload.',
                'updates',
                $updates
            );
        }

        foreach ($updates as $index => $update) {
            if (!is_array($update)) {
                throw new InvalidArgumentException(
                    sprintf('Update at index %d must be an array.', $index),
                    'updates',
                    $update
                );
            }

            if (!isset($update['id']) || $update['id'] === '' || $update['id'] === null) {
                throw new InvalidArgumentException(
                    sprintf('Update at index %d must contain a non-empty "id" field.', $index),
                    'updates',
                    $update
                );
            }
        }
    }
}
