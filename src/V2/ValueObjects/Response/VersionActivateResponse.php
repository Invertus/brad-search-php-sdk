<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from index version activation API endpoint.
 *
 * This immutable ValueObject contains the response data after activating an index version:
 * - previousVersion: The version that was active before
 * - newVersion: The newly activated version
 * - aliasName: The alias name that now points to the new version
 */
final readonly class VersionActivateResponse extends ValueObject
{
    public function __construct(
        public int $previousVersion,
        public int $newVersion,
        public string $aliasName
    ) {
        $this->validateNonNegative($previousVersion, 'previous_version');
        $this->validateNonNegative($newVersion, 'new_version');
        $this->validateNotEmpty($aliasName, 'alias_name');
    }

    /**
     * Creates a VersionActivateResponse from an API response array.
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
            'previous_version',
            'new_version',
            'alias_name',
        ]);

        return new self(
            previousVersion: (int) $data['previous_version'],
            newVersion: (int) $data['new_version'],
            aliasName: (string) $data['alias_name']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'previous_version' => $this->previousVersion,
            'new_version' => $this->newVersion,
            'alias_name' => $this->aliasName,
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
