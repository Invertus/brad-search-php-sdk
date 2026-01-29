<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents an error response from the API.
 *
 * This immutable ValueObject contains error information:
 * - status: HTTP status code or error status
 * - error: Error type or name
 * - details: Additional error details
 */
final readonly class ErrorResponse extends ValueObject
{
    /**
     * @param int $status HTTP status code or error status
     * @param string $error Error type or name
     * @param string|array<string, mixed>|null $details Additional error details
     */
    public function __construct(
        public int $status,
        public string $error,
        public string|array|null $details = null
    ) {
        $this->validateNotEmpty($error, 'error');
    }

    /**
     * Creates an ErrorResponse from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['status', 'error']);

        return new self(
            status: (int) $data['status'],
            error: (string) $data['error'],
            details: $data['details'] ?? null
        );
    }

    /**
     * Checks if this is a client error (4xx status).
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Checks if this is a server error (5xx status).
     */
    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * Checks if this is a not found error (404).
     */
    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * Checks if this is an unauthorized error (401).
     */
    public function isUnauthorized(): bool
    {
        return $this->status === 401;
    }

    /**
     * Checks if this is a validation error (400 or 422).
     */
    public function isValidationError(): bool
    {
        return $this->status === 400 || $this->status === 422;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'status' => $this->status,
            'error' => $this->error,
        ];

        if ($this->details !== null) {
            $result['details'] = $this->details;
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
