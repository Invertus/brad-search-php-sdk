<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from bulk operations API endpoint.
 *
 * This immutable ValueObject contains the response data after executing bulk operations:
 * - status: Overall status (success, partial, error)
 * - totalOperations: Total number of items processed
 * - successfulOperations: Number of items that succeeded
 * - failedOperations: Number of items that failed
 * - results: Array of per-item results
 * - warnings: Optional array of non-fatal warnings
 * - processingTimeMs: Optional processing time in milliseconds
 */
final readonly class BulkOperationsResponse extends ValueObject
{
    /**
     * @param string $status Overall status
     * @param int $totalOperations Total items count
     * @param int $successfulOperations Successful items count
     * @param int $failedOperations Failed items count
     * @param array<int, ItemResult> $results Per-item results
     * @param array<int, string>|null $warnings Optional warnings
     * @param int|null $processingTimeMs Optional processing time
     */
    public function __construct(
        public string $status,
        public int $totalOperations,
        public int $successfulOperations,
        public int $failedOperations,
        public array $results,
        public ?array $warnings = null,
        public ?int $processingTimeMs = null
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNonNegative($totalOperations, 'total_operations');
        $this->validateNonNegative($successfulOperations, 'successful_operations');
        $this->validateNonNegative($failedOperations, 'failed_operations');
        $this->validateResults($results);
    }

    /**
     * Creates a BulkOperationsResponse from an API response array.
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
            'status',
            'total_operations',
            'successful_operations',
            'failed_operations',
            'results',
        ]);

        $results = [];
        foreach ($data['results'] as $resultData) {
            $results[] = ItemResult::fromArray($resultData);
        }

        return new self(
            status: (string) $data['status'],
            totalOperations: (int) $data['total_operations'],
            successfulOperations: (int) $data['successful_operations'],
            failedOperations: (int) $data['failed_operations'],
            results: $results,
            warnings: $data['warnings'] ?? null,
            processingTimeMs: isset($data['processing_time_ms']) ? (int) $data['processing_time_ms'] : null
        );
    }

    /**
     * Checks if all items were processed successfully.
     */
    public function isFullySuccessful(): bool
    {
        return $this->status === 'success' && $this->failedOperations === 0;
    }

    /**
     * Checks if any items failed.
     */
    public function hasFailures(): bool
    {
        return $this->failedOperations > 0;
    }

    /**
     * Gets all failed item results.
     *
     * @return array<ItemResult>
     */
    public function getFailedResults(): array
    {
        return array_filter(
            $this->results,
            fn(ItemResult $result) => $result->hasError()
        );
    }

    /**
     * Gets all successful item results.
     *
     * @return array<ItemResult>
     */
    public function getSuccessfulResults(): array
    {
        return array_filter(
            $this->results,
            fn(ItemResult $result) => $result->isSuccessful()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'status' => $this->status,
            'total_operations' => $this->totalOperations,
            'successful_operations' => $this->successfulOperations,
            'failed_operations' => $this->failedOperations,
            'results' => array_map(
                fn(ItemResult $item) => $item->jsonSerialize(),
                $this->results
            ),
        ];

        if ($this->warnings !== null) {
            $result['warnings'] = $this->warnings;
        }

        if ($this->processingTimeMs !== null) {
            $result['processing_time_ms'] = $this->processingTimeMs;
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
     * Validates that all results are ItemResult instances.
     *
     * @param array<mixed> $results
     *
     * @throws InvalidArgumentException If any result is not an ItemResult
     */
    private function validateResults(array $results): void
    {
        foreach ($results as $index => $result) {
            if (!$result instanceof ItemResult) {
                throw new InvalidArgumentException(
                    sprintf('Result at index %d must be an instance of ItemResult.', $index),
                    'results',
                    $result
                );
            }
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
