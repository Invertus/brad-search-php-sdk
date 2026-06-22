<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Synonym;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a synonym configuration matching SynonymConfiguration schema.
 *
 * This immutable ValueObject defines synonyms for a specific language,
 * where each synonym group contains terms that are considered equivalent.
 */
final readonly class SynonymConfiguration extends ValueObject
{
    private const LANGUAGE_PATTERN = '/^[a-z]{2}$/';

    /**
     * @param string $language ISO 639-1 language code (e.g., "en", "lt")
     * @param array<int, array<int, string>> $synonyms Array of synonym groups
     */
    public function __construct(
        public string $language,
        public array $synonyms
    ) {
        $this->validateLanguage($language);
        $this->validateSynonyms($synonyms);
    }

    /**
     * Returns a new instance with a different language.
     */
    public function withLanguage(string $language): self
    {
        return new self($language, $this->synonyms);
    }

    /**
     * Returns a new instance with different synonyms.
     *
     * @param array<int, array<int, string>> $synonyms Array of synonym groups
     */
    public function withSynonyms(array $synonyms): self
    {
        return new self($this->language, $synonyms);
    }

    /**
     * Returns a new instance with an additional synonym group.
     *
     * @param array<int, string> $synonymGroup Array of synonymous terms
     */
    public function addSynonym(array $synonymGroup): self
    {
        return new self($this->language, [...$this->synonyms, $synonymGroup]);
    }

    /**
     * Creates a SynonymConfiguration from an API response payload, where each
     * synonym group is a Solr-format string (e.g. "laptop, notebook").
     *
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $synonyms = array_map(
            static fn(string $group): array => array_map('trim', explode(',', $group)),
            $data['synonyms'] ?? []
        );

        return new self((string) ($data['language'] ?? ''), $synonyms);
    }

    /**
     * The API expects each synonym group as a Solr-format equivalence string
     * ("laptop, notebook, computer"), not as a nested array.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'language' => $this->language,
            'synonyms' => array_map(
                static fn(array $group): string => implode(', ', $group),
                $this->synonyms
            ),
        ];
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
     * Validates that the synonyms array is not empty and each entry is valid.
     *
     * @param array<int, array<int, string>> $synonyms
     *
     * @throws InvalidArgumentException If synonyms array is empty or contains invalid entries
     */
    private function validateSynonyms(array $synonyms): void
    {
        if (empty($synonyms)) {
            throw new InvalidArgumentException(
                'Synonyms array cannot be empty.',
                'synonyms',
                $synonyms
            );
        }

        foreach ($synonyms as $index => $synonymGroup) {
            if (!is_array($synonymGroup)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Synonym group at index %d must be an array, got %s.',
                        $index,
                        gettype($synonymGroup)
                    ),
                    'synonyms',
                    $synonyms
                );
            }

            if (empty($synonymGroup)) {
                throw new InvalidArgumentException(
                    sprintf('Synonym group at index %d cannot be empty.', $index),
                    'synonyms',
                    $synonyms
                );
            }

            foreach ($synonymGroup as $termIndex => $term) {
                if (!is_string($term)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Synonym term at index [%d][%d] must be a string, got %s.',
                            $index,
                            $termIndex,
                            gettype($term)
                        ),
                        'synonyms',
                        $synonyms
                    );
                }

                if (trim($term) === '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Synonym term at index [%d][%d] cannot be empty.',
                            $index,
                            $termIndex
                        ),
                        'synonyms',
                        $synonyms
                    );
                }

                if (str_contains($term, ',') || str_contains($term, '=>')) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Synonym term at index [%d][%d] must not contain "," or "=>" (Solr syntax characters), got "%s".',
                            $index,
                            $termIndex,
                            $term
                        ),
                        'synonyms',
                        $synonyms
                    );
                }
            }
        }
    }
}
