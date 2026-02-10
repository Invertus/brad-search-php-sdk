<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from index creation API endpoint.
 *
 * This immutable ValueObject contains the response data after creating a new index:
 * - status: Operation status (e.g., "success")
 * - physical_index_name: The actual index name in the backend
 * - alias_name: The alias pointing to the index
 * - version: The version number of the created index
 * - fields_created: Number of fields created
 * - message: Human-readable message about the operation
 */
final readonly class IndexCreationResponse extends ValueObject
{
    public function __construct(
        public string $status,
        public string $physicalIndexName,
        public string $aliasName,
        public int $version,
        public int $fieldsCreated,
        public string $message
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNotEmpty($physicalIndexName, 'physical_index_name');
        $this->validateNotEmpty($aliasName, 'alias_name');
        $this->validateNonNegative($version, 'version');
        $this->validateNonNegative($fieldsCreated, 'fields_created');
    }

    /**
     * Creates an IndexCreationResponse from an API response array.
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
            'physical_index_name',
            'alias_name',
            'version',
            'fields_created',
            'message',
        ]);

        return new self(
            status: (string) $data['status'],
            physicalIndexName: (string) $data['physical_index_name'],
            aliasName: (string) $data['alias_name'],
            version: self::parseVersion($data['version']),
            fieldsCreated: (int) $data['fields_created'],
            message: (string) $data['message']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'physical_index_name' => $this->physicalIndexName,
            'alias_name' => $this->aliasName,
            'version' => $this->version,
            'fields_created' => $this->fieldsCreated,
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
     * Parse version from API response.
     *
     * The brad-search API returns version as a string with "v" prefix (e.g., "v4").
     * This method strips the prefix and converts to integer.
     *
     * @param mixed $version Version from API (string "v4" or int 4)
     *
     * @return int Numeric version (4)
     *
     * @throws InvalidArgumentException If version format is invalid
     */
    private static function parseVersion(mixed $version): int
    {
        // If already an integer, return it
        if (is_int($version)) {
            return $version;
        }

        $versionString = (string) $version;

        // Remove "v" or "V" prefix: "v4" → "4"
        $versionString = ltrim($versionString, 'vV');

        // Convert to integer
        $parsed = (int) $versionString;

        // Validate: ensure we got a valid number (not 0 from failed parsing)
        if ($parsed === 0 && $versionString !== '0') {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid version format: "%s". Expected integer or "vX" format.',
                    $version
                ),
                'version',
                $version
            );
        }

        return $parsed;
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
