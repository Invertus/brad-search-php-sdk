<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single item result in a bulk operations response.
 *
 * This immutable ValueObject contains the result of processing a single item:
 * - id: The ID of the item that was processed
 * - operation: The type of operation performed (index_products, delete_products, etc.)
 * - status: The result status (created, updated, deleted, error)
 * - error: Error message if status is "error" (optional)
 */
final readonly class ItemResult extends ValueObject
{
    /**
     * @param string $id The ID of the item
     * @param BulkOperationType $operation The type of operation performed
     * @param string $status The result status (created, updated, deleted, error)
     * @param string|null $error Error message if status is "error"
     */
    public function __construct(
        public string $id,
        public BulkOperationType $operation,
        public string $status,
        public ?string $error = null
    ) {
        $this->validateNotEmpty($id, 'id');
        $this->validateNotEmpty($status, 'status');
    }

    /**
     * Creates an ItemResult from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, [
            'id',
            'operation',
            'status',
        ]);

        return new self(
            id: (string) $data['id'],
            operation: BulkOperationType::from($data['operation']),
            status: (string) $data['status'],
            error: isset($data['error']) ? (string) $data['error'] : null
        );
    }

    /**
     * Checks if this item was processed successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->status !== 'error';
    }

    /**
     * Checks if this item failed.
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'operation' => $this->operation->value,
            'status' => $this->status,
        ];

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }

    /**
     * Validates that a string field is not empty.
     *
     * @throws InvalidArgumentException If the value is empty
     */
    private function validateNotEmpty(string $value, string $fieldName): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(
                sprintf('%s cannot be empty.', $fieldName),
                $fieldName,
                $value
            );
        }
    }

    /**
     * Validates that all required fields are present in the data array.
     *
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     *
     * @throws InvalidArgumentException If a required field is missing
     */
    private static function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    sprintf('Missing required field: %s', $field),
                    $field,
                    null
                );
            }
        }
    }
}
