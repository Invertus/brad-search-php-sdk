<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single index version in the API response.
 *
 * This immutable ValueObject contains information about an index version:
 * - version: The version number
 * - indexName: The physical index name
 * - documentCount: Number of documents in this version
 * - createdAt: ISO 8601 timestamp when the version was created
 * - isActive: Whether this version is currently active
 */
final readonly class IndexVersion extends ValueObject
{
    public function __construct(
        public int $version,
        public string $indexName,
        public int $documentCount,
        public string $createdAt,
        public bool $isActive
    ) {
        $this->validateNonNegative($version, 'version');
        $this->validateNotEmpty($indexName, 'index_name');
        $this->validateNonNegative($documentCount, 'document_count');
        $this->validateNotEmpty($createdAt, 'created_at');
    }

    /**
     * Creates an IndexVersion from an API response array.
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
            'version',
            'index_name',
            'document_count',
            'created_at',
            'is_active',
        ]);

        return new self(
            version: (int) $data['version'],
            indexName: (string) $data['index_name'],
            documentCount: (int) $data['document_count'],
            createdAt: (string) $data['created_at'],
            isActive: (bool) $data['is_active']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'index_name' => $this->indexName,
            'document_count' => $this->documentCount,
            'created_at' => $this->createdAt,
            'is_active' => $this->isActive,
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
