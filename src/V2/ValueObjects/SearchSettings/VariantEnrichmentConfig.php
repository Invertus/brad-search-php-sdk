<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the variant enrichment configuration for search results.
 *
 * This immutable ValueObject defines which fields from matched variants
 * should replace the parent product fields in search results.
 */
final readonly class VariantEnrichmentConfig extends ValueObject
{
    /**
     * @param array<string> $replaceFields Array of field names to replace from matched variants
     */
    public function __construct(
        public array $replaceFields = []
    ) {
        $this->validateReplaceFields($replaceFields);
    }

    /**
     * Creates a VariantEnrichmentConfig from an array (typically from JSON).
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public static function fromArray(array $data): self
    {
        $replaceFields = [];
        if (isset($data['replace_fields']) && is_array($data['replace_fields'])) {
            $replaceFields = $data['replace_fields'];
        }

        return new self(
            replaceFields: $replaceFields
        );
    }

    /**
     * Returns a new instance with different replace fields.
     *
     * @param array<string> $replaceFields
     */
    public function withReplaceFields(array $replaceFields): self
    {
        return new self($replaceFields);
    }

    /**
     * Returns a new instance with an additional replace field.
     */
    public function withAddedReplaceField(string $fieldName): self
    {
        return new self([...$this->replaceFields, $fieldName]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if (count($this->replaceFields) > 0) {
            $result['replace_fields'] = $this->replaceFields;
        }

        return $result;
    }

    /**
     * Validates that all replace fields are valid strings.
     *
     * @param array<mixed> $replaceFields
     * @throws InvalidArgumentException If any field is invalid
     */
    private function validateReplaceFields(array $replaceFields): void
    {
        foreach ($replaceFields as $index => $fieldName) {
            if (!is_string($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Replace field at index %d must be a string.', $index),
                    'replace_fields',
                    $fieldName
                );
            }

            if ($fieldName === '') {
                throw new InvalidArgumentException(
                    sprintf('Replace field at index %d cannot be empty.', $index),
                    'replace_fields',
                    $fieldName
                );
            }
        }
    }
}
