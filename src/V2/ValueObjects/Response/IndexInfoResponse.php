<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from get index info API endpoint.
 *
 * This immutable ValueObject contains index information:
 * - aliasName: The alias pointing to the active index
 * - activeVersion: The currently active version number
 * - activeIndex: The physical name of the active index
 * - allVersions: Array of IndexVersion objects
 */
final readonly class IndexInfoResponse extends ValueObject
{
    /**
     * @param string $aliasName The alias name
     * @param int $activeVersion The active version number
     * @param string $activeIndex The physical name of the active index
     * @param array<IndexVersion> $allVersions All available versions
     */
    public function __construct(
        public string $aliasName,
        public int $activeVersion,
        public string $activeIndex,
        public array $allVersions
    ) {
        $this->validateNotEmpty($aliasName, 'alias_name');
        $this->validateNonNegative($activeVersion, 'active_version');
        $this->validateNotEmpty($activeIndex, 'active_index');
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
            'active_version',
            'active_index',
            'all_versions',
        ]);

        $versions = [];
        foreach ($data['all_versions'] as $versionData) {
            $versions[] = IndexVersion::fromArray($versionData);
        }

        return new self(
            aliasName: (string) $data['alias_name'],
            activeVersion: (int) $data['active_version'],
            activeIndex: (string) $data['active_index'],
            allVersions: $versions
        );
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
        return $this->getVersion($this->activeVersion);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'alias_name' => $this->aliasName,
            'active_version' => $this->activeVersion,
            'active_index' => $this->activeIndex,
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
