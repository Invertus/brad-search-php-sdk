<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from the normalize API endpoint.
 *
 * This immutable ValueObject contains the response data after normalizing field values:
 * - status: Overall status (success, partial, error)
 * - results: Array of per-field normalization results
 * - message: Descriptive message about the normalization
 */
final readonly class NormalizeResponse extends ValueObject
{
    /**
     * @param string $status Overall status
     * @param array<int, NormalizeFieldResult> $results Per-field results
     * @param string $message Descriptive message
     */
    public function __construct(
        public string $status,
        public array $results,
        public string $message
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateResults($results);
    }

    /**
     * Creates a NormalizeResponse from an API response array.
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
            'results',
            'message',
        ]);

        $results = [];
        foreach ($data['results'] as $resultData) {
            $results[] = NormalizeFieldResult::fromArray($resultData);
        }

        return new self(
            status: (string) $data['status'],
            results: $results,
            message: (string) $data['message']
        );
    }

    /**
     * Checks if all fields were normalized successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Gets all successful field results.
     *
     * @return array<NormalizeFieldResult>
     */
    public function getSuccessfulResults(): array
    {
        return array_filter(
            $this->results,
            fn(NormalizeFieldResult $result) => $result->isSuccessful()
        );
    }

    /**
     * Gets all failed field results.
     *
     * @return array<NormalizeFieldResult>
     */
    public function getFailedResults(): array
    {
        return array_filter(
            $this->results,
            fn(NormalizeFieldResult $result) => !$result->isSuccessful()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'results' => array_map(
                fn(NormalizeFieldResult $result) => $result->jsonSerialize(),
                $this->results
            ),
            'message' => $this->message,
        ];
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
     * Validates that all results are NormalizeFieldResult instances.
     *
     * @param array<mixed> $results
     *
     * @throws InvalidArgumentException If any result is not a NormalizeFieldResult
     */
    private function validateResults(array $results): void
    {
        foreach ($results as $index => $result) {
            if (!$result instanceof NormalizeFieldResult) {
                throw new InvalidArgumentException(
                    sprintf('Result at index %d must be an instance of NormalizeFieldResult.', $index),
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
