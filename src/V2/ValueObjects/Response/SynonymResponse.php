<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from synonym API endpoints.
 *
 * This immutable ValueObject contains the response data for synonym operations:
 * - language: The language code for the synonyms
 * - synonymCount: Number of synonym groups
 * - requiresReindex: Whether the index needs to be reindexed
 * - synonyms: Optional array of synonym groups (returned by GET)
 */
final readonly class SynonymResponse extends ValueObject
{
    private const LANGUAGE_PATTERN = '/^[a-z]{2}$/';

    /**
     * @param string $language ISO 639-1 language code
     * @param int $synonymCount Number of synonym groups
     * @param bool $requiresReindex Whether reindex is required
     * @param array<int, array<int, string>>|null $synonyms Optional array of synonym groups
     */
    public function __construct(
        public string $language,
        public int $synonymCount,
        public bool $requiresReindex,
        public ?array $synonyms = null
    ) {
        $this->validateLanguage($language);
        $this->validateNonNegative($synonymCount, 'synonym_count');
    }

    /**
     * Creates a SynonymResponse from an API response array.
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
            'language',
            'synonym_count',
            'requires_reindex',
        ]);

        return new self(
            language: (string) $data['language'],
            synonymCount: (int) $data['synonym_count'],
            requiresReindex: (bool) $data['requires_reindex'],
            synonyms: $data['synonyms'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'language' => $this->language,
            'synonym_count' => $this->synonymCount,
            'requires_reindex' => $this->requiresReindex,
        ];

        if ($this->synonyms !== null) {
            $result['synonyms'] = $this->synonyms;
        }

        return $result;
    }

    /**
     * Validates that the language matches ISO 639-1 format.
     *
     * @throws InvalidArgumentException If language format is invalid
     */
    private function validateLanguage(string $language): void
    {
        if (preg_match(self::LANGUAGE_PATTERN, $language) !== 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Language must be a valid ISO 639-1 code (2 lowercase letters), got "%s".',
                    $language
                ),
                'language',
                $language
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
