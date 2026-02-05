<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single operation result in a bulk operations response.
 *
 * This immutable ValueObject contains the result of an individual bulk operation:
 * - operationType: The type of operation performed
 * - status: The result status (e.g., "success", "failed")
 * - itemsProcessed: Number of items processed
 * - itemsFailed: Number of items that failed
 * - errors: Optional array of error details
 */
final readonly class OperationResult extends ValueObject
{
    /**
     * @param BulkOperationType $operationType The type of operation
     * @param string $status The result status
     * @param int $itemsProcessed Number of items processed
     * @param int $itemsFailed Number of items that failed
     * @param array<int, array<string, mixed>>|null $errors Optional array of error details
     */
    public function __construct(
        public BulkOperationType $operationType,
        public string $status,
        public int $itemsProcessed,
        public int $itemsFailed,
        public ?array $errors = null
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNonNegative($itemsProcessed, 'items_processed');
        $this->validateNonNegative($itemsFailed, 'items_failed');
    }

    /**
     * Creates an OperationResult from an API response array.
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
            'type',
            'status',
            'items_processed',
            'items_failed',
        ]);

        return new self(
            operationType: BulkOperationType::from($data['type']),
            status: (string) $data['status'],
            itemsProcessed: (int) $data['items_processed'],
            itemsFailed: (int) $data['items_failed'],
            errors: $data['errors'] ?? null
        );
    }

    /**
     * Checks if this operation was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success' && $this->itemsFailed === 0;
    }

    /**
     * Checks if this operation had any failures.
     */
    public function hasFailures(): bool
    {
        return $this->itemsFailed > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->operationType->value,
            'status' => $this->status,
            'items_processed' => $this->itemsProcessed,
            'items_failed' => $this->itemsFailed,
        ];

        if ($this->errors !== null) {
            $result['errors'] = $this->errors;
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
     * Validates that an integer field is non-negative.
     *
     * @throws InvalidArgumentException If the value is negative
     */
    private function validateNonNegative(int $value, string $fieldName): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                sprintf('%s must be non-negative, got %d.', $fieldName, $value),
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
