<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a field configuration for search result highlighting.
 *
 * This immutable ValueObject defines how a specific field should be highlighted
 * in search results, including the tags to wrap matched text.
 */
final readonly class HighlightField extends ValueObject
{
    /**
     * @param string $fieldName The field name to highlight
     * @param bool|string|null $localeSuffix Locale suffix: true to auto-apply locale, or a specific suffix string
     * @param array<string> $preTags Tags to insert before highlighted text
     * @param array<string> $postTags Tags to insert after highlighted text
     */
    public function __construct(
        public string $fieldName,
        public bool|string|null $localeSuffix = null,
        public array $preTags = [],
        public array $postTags = []
    ) {
        $this->validateFieldName($fieldName);
        $this->validateTags($preTags, 'pre_tags');
        $this->validateTags($postTags, 'post_tags');
    }

    /**
     * Creates a HighlightField from an array (typically from JSON).
     *
     * @param array<string, mixed> $data Raw data array
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['field_name']);

        return new self(
            fieldName: (string) $data['field_name'],
            localeSuffix: $data['locale_suffix'] ?? null,
            preTags: isset($data['pre_tags']) && is_array($data['pre_tags']) ? $data['pre_tags'] : [],
            postTags: isset($data['post_tags']) && is_array($data['post_tags']) ? $data['post_tags'] : []
        );
    }

    /**
     * Returns a new instance with a different field name.
     */
    public function withFieldName(string $fieldName): self
    {
        return new self($fieldName, $this->localeSuffix, $this->preTags, $this->postTags);
    }

    /**
     * Returns a new instance with a different locale suffix.
     */
    public function withLocaleSuffix(bool|string|null $localeSuffix): self
    {
        return new self($this->fieldName, $localeSuffix, $this->preTags, $this->postTags);
    }

    /**
     * Returns a new instance with different pre tags.
     *
     * @param array<string> $preTags
     */
    public function withPreTags(array $preTags): self
    {
        return new self($this->fieldName, $this->localeSuffix, $preTags, $this->postTags);
    }

    /**
     * Returns a new instance with different post tags.
     *
     * @param array<string> $postTags
     */
    public function withPostTags(array $postTags): self
    {
        return new self($this->fieldName, $this->localeSuffix, $this->preTags, $postTags);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'field_name' => $this->fieldName,
        ];

        if ($this->localeSuffix !== null) {
            $result['locale_suffix'] = $this->localeSuffix;
        }

        if (count($this->preTags) > 0) {
            $result['pre_tags'] = $this->preTags;
        }

        if (count($this->postTags) > 0) {
            $result['post_tags'] = $this->postTags;
        }

        return $result;
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

    /**
     * Validates that the field name is not empty.
     *
     * @throws InvalidArgumentException If field name is empty
     */
    private function validateFieldName(string $fieldName): void
    {
        if ($fieldName === '') {
            throw new InvalidArgumentException(
                'Field name cannot be empty.',
                'field_name',
                $fieldName
            );
        }
    }

    /**
     * Validates that all tags are valid strings.
     *
     * @param array<mixed> $tags
     * @param string $fieldName
     * @throws InvalidArgumentException If any tag is invalid
     */
    private function validateTags(array $tags, string $fieldName): void
    {
        foreach ($tags as $index => $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException(
                    sprintf('Tag at index %d in %s must be a string.', $index, $fieldName),
                    $fieldName,
                    $tag
                );
            }
        }
    }
}
