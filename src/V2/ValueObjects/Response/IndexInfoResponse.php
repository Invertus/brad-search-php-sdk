<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from get index info API endpoint.
 *
 * This immutable ValueObject contains index information:
 * - aliasName: The alias pointing to the active index (e.g., application ID)
 * - physicalIndexName: The physical name of the active index (e.g., "app-id-v15")
 * - currentVersion: The currently active version string (e.g., "v15")
 * - documentCount: Number of documents in the index
 * - sizeInBytes: Size of the index in bytes
 * - fieldCount: Number of fields in the index
 * - allVersions: Array of IndexVersion objects (optional, may be empty)
 */
final readonly class IndexInfoResponse extends ValueObject
{
    /**
     * @param string $aliasName The alias name
     * @param string $physicalIndexName The physical name of the active index
     * @param string $currentVersion The current version string (e.g., "v15")
     * @param int $documentCount Number of documents in the index
     * @param int $sizeInBytes Size of the index in bytes
     * @param int $fieldCount Number of fields in the index
     * @param array<IndexVersion> $allVersions All available versions (optional)
     */
    public function __construct(
        public string $aliasName,
        public string $physicalIndexName,
        public string $currentVersion,
        public int $documentCount,
        public int $sizeInBytes,
        public int $fieldCount,
        public array $allVersions = []
    ) {
        $this->validateNotEmpty($aliasName, 'alias_name');
        $this->validateNotEmpty($physicalIndexName, 'physical_index_name');
        $this->validateNotEmpty($currentVersion, 'current_version');
        $this->validateNonNegative($documentCount, 'document_count');
        $this->validateNonNegative($sizeInBytes, 'size_in_bytes');
        $this->validateNonNegative($fieldCount, 'field_count');
        $this->validateVersions($allVersions);
    }

    /**
     * Creates an IndexInfoResponse from an API response array.
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
            'alias_name',
            'physical_index_name',
            'current_version',
            'document_count',
            'size_in_bytes',
            'field_count',
        ]);

        // Parse all_versions if present (API may not return this field)
        $versions = [];
        if (isset($data['all_versions']) && is_array($data['all_versions'])) {
            foreach ($data['all_versions'] as $versionData) {
                $versions[] = IndexVersion::fromArray($versionData);
            }
        }

        return new self(
            aliasName: (string) $data['alias_name'],
            physicalIndexName: (string) $data['physical_index_name'],
            currentVersion: (string) $data['current_version'],
            documentCount: (int) $data['document_count'],
            sizeInBytes: (int) $data['size_in_bytes'],
            fieldCount: (int) $data['field_count'],
            allVersions: $versions
        );
    }

    /**
     * Get the active version number as an integer.
     * Parses version string like "v15" to 15.
     *
     * @return int The version number
     */
    public function getActiveVersionNumber(): int
    {
        // Remove "v" prefix and convert to int
        return (int) ltrim($this->currentVersion, 'v');
    }

    /**
     * Gets a specific version by version number.
     *
     * @param int $version The version number to find
     *
     * @return IndexVersion|null The version if found, null otherwise
     */
    public function getVersion(int $version): ?IndexVersion
    {
        foreach ($this->allVersions as $indexVersion) {
            if ($indexVersion->version === $version) {
                return $indexVersion;
            }
        }

        return null;
    }

    /**
     * Gets the currently active version object.
     *
     * @return IndexVersion|null The active version if found
     */
    public function getActiveVersionObject(): ?IndexVersion
    {
        return $this->getVersion($this->getActiveVersionNumber());
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'alias_name' => $this->aliasName,
            'physical_index_name' => $this->physicalIndexName,
            'current_version' => $this->currentVersion,
            'document_count' => $this->documentCount,
            'size_in_bytes' => $this->sizeInBytes,
            'field_count' => $this->fieldCount,
            'all_versions' => array_map(
                fn(IndexVersion $version) => $version->jsonSerialize(),
                $this->allVersions
            ),
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
     * Validates that all versions are IndexVersion instances.
     *
     * @param array<mixed> $versions
     *
     * @throws InvalidArgumentException If any version is not an IndexVersion
     */
    private function validateVersions(array $versions): void
    {
        foreach ($versions as $index => $version) {
            if (!$version instanceof IndexVersion) {
                throw new InvalidArgumentException(
                    sprintf('Version at index %d must be an instance of IndexVersion.', $index),
                    'all_versions',
                    $version
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
