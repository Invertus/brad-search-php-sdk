<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from index version activation API endpoint.
 *
 * This immutable ValueObject contains the response data after activating an index version:
 * - status: Response status (e.g., "success")
 * - oldIndex: Full name of the previously active index (e.g., "app-id-v1")
 * - newIndex: Full name of the newly activated index (e.g., "app-id-v2")
 * - aliasName: The alias name that now points to the new version
 * - message: Success message
 * - previousVersion: Parsed version number from oldIndex
 * - newVersion: Parsed version number from newIndex
 */
final readonly class VersionActivateResponse extends ValueObject
{
    public function __construct(
        public string $status,
        public string $oldIndex,
        public string $newIndex,
        public string $aliasName,
        public string $message,
        public int $previousVersion,
        public int $newVersion
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNotEmpty($oldIndex, 'old_index');
        $this->validateNotEmpty($newIndex, 'new_index');
        $this->validateNotEmpty($aliasName, 'alias_name');
        $this->validateNotEmpty($message, 'message');
        $this->validateNonNegative($previousVersion, 'previous_version');
        $this->validateNonNegative($newVersion, 'new_version');
    }

    /**
     * Creates a VersionActivateResponse from an API response array.
     *
     * API returns:
     * - status: "success"
     * - old_index: "193d520f-6732-49ac-98ba-e26fdcf676a5-v1"
     * - new_index: "193d520f-6732-49ac-98ba-e26fdcf676a5-v2"
     * - alias_name: "193d520f-6732-49ac-98ba-e26fdcf676a5"
     * - message: "Alias swapped successfully"
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
            'old_index',
            'new_index',
            'alias_name',
            'message',
        ]);

        // Parse version numbers from index names
        // "193d520f-6732-49ac-98ba-e26fdcf676a5-v1" -> 1
        $previousVersion = self::parseVersionFromIndexName((string) $data['old_index']);
        $newVersion = self::parseVersionFromIndexName((string) $data['new_index']);

        return new self(
            status: (string) $data['status'],
            oldIndex: (string) $data['old_index'],
            newIndex: (string) $data['new_index'],
            aliasName: (string) $data['alias_name'],
            message: (string) $data['message'],
            previousVersion: $previousVersion,
            newVersion: $newVersion
        );
    }

    /**
     * Parse version number from index name.
     *
     * Examples:
     * - "193d520f-6732-49ac-98ba-e26fdcf676a5-v1" -> 1
     * - "app-id-v42" -> 42
     *
     * @param string $indexName Full index name
     * @return int Version number
     * @throws InvalidArgumentException If version cannot be parsed
     */
    private static function parseVersionFromIndexName(string $indexName): int
    {
        // Extract version suffix: "app-id-v1" -> "v1"
        if (preg_match('/-v(\d+)$/', $indexName, $matches)) {
            return (int) $matches[1];
        }

        throw new InvalidArgumentException(
            sprintf('Cannot parse version from index name: %s', $indexName),
            'index_name',
            $indexName
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'old_index' => $this->oldIndex,
            'new_index' => $this->newIndex,
            'alias_name' => $this->aliasName,
            'message' => $this->message,
            'previous_version' => $this->previousVersion,
            'new_version' => $this->newVersion,
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
