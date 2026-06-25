<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidLocaleException;
use BradSearch\SyncSdk\V2\ValueObjects\Synonym\SynonymConfiguration;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents an index creation request matching IndexCreateRequestV2App schema.
 *
 * This immutable ValueObject contains the required data for creating a new index:
 * - locales: Array of locale codes in 'xx-XX' format
 * - fields: Array of FieldDefinition objects
 * - synonyms: Optional per-language synonym configurations, applied at index
 *   creation so they take effect on activation without a separate post-activation
 *   update (which would briefly close the active index).
 */
final readonly class IndexCreateRequest extends ValueObject
{
    private const LOCALE_PATTERN = '/^[a-z]{2}(-[A-Z]{2})?$/';

    /** @var array<string> */
    public array $locales;

    /**
     * @param array<string> $locales Array of locale codes (e.g., ['lt-LT', 'en-US'])
     * @param array<FieldDefinition> $fields Array of field definitions
     * @param array<SynonymConfiguration> $synonyms Optional per-language synonym configurations
     */
    public function __construct(
        array $locales,
        public array $fields,
        public array $synonyms = []
    ) {
        $this->validateLocales($locales);
        $this->validateFields($fields);
        $this->validateSynonyms($synonyms);
        $this->locales = $locales;
    }

    /**
     * Returns a new instance with different locales.
     *
     * @param array<string> $locales
     */
    public function withLocales(array $locales): self
    {
        return new self($locales, $this->fields, $this->synonyms);
    }

    /**
     * Returns a new instance with different fields.
     *
     * @param array<FieldDefinition> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($this->locales, $fields, $this->synonyms);
    }

    /**
     * Returns a new instance with different synonym configurations.
     *
     * @param array<SynonymConfiguration> $synonyms
     */
    public function withSynonyms(array $synonyms): self
    {
        return new self($this->locales, $this->fields, $synonyms);
    }

    /**
     * Returns a new instance with an additional locale.
     */
    public function withAddedLocale(string $locale): self
    {
        return new self([...$this->locales, $locale], $this->fields, $this->synonyms);
    }

    /**
     * Returns a new instance with an additional field.
     */
    public function withAddedField(FieldDefinition $field): self
    {
        return new self($this->locales, [...$this->fields, $field], $this->synonyms);
    }

    /**
     * Returns a new instance with an additional synonym configuration.
     */
    public function withAddedSynonym(SynonymConfiguration $synonym): self
    {
        return new self($this->locales, $this->fields, [...$this->synonyms, $synonym]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'locales' => $this->locales,
            'fields' => array_map(
                fn(FieldDefinition $field) => $field->jsonSerialize(),
                $this->fields
            ),
        ];

        if ($this->synonyms !== []) {
            $data['synonyms'] = array_map(
                fn(SynonymConfiguration $synonym) => $synonym->jsonSerialize(),
                $this->synonyms
            );
        }

        return $data;
    }

    /**
     * Validates that all locales match the required pattern.
     *
     * @param array<mixed> $locales
     * @throws InvalidArgumentException If no locales are provided
     * @throws InvalidLocaleException If a locale doesn't match the pattern
     */
    private function validateLocales(array $locales): void
    {
        if (count($locales) === 0) {
            throw new InvalidArgumentException(
                'At least one locale is required.',
                'locales',
                $locales
            );
        }

        foreach ($locales as $index => $locale) {
            if (!is_string($locale)) {
                throw new InvalidArgumentException(
                    sprintf('Locale at index %d must be a string.', $index),
                    'locales',
                    $locale
                );
            }

            if (preg_match(self::LOCALE_PATTERN, $locale) !== 1) {
                throw new InvalidLocaleException($locale);
            }
        }
    }

    /**
     * Validates that all fields are FieldDefinition instances.
     *
     * @param array<mixed> $fields
     * @throws InvalidArgumentException If no fields are provided or invalid field types
     */
    private function validateFields(array $fields): void
    {
        if (count($fields) === 0) {
            throw new InvalidArgumentException(
                'At least one field is required.',
                'fields',
                $fields
            );
        }

        foreach ($fields as $index => $field) {
            if (!$field instanceof FieldDefinition) {
                throw new InvalidArgumentException(
                    sprintf('Field at index %d must be an instance of FieldDefinition.', $index),
                    'fields',
                    $field
                );
            }
        }
    }

    /**
     * Validates that all synonym entries are SynonymConfiguration instances.
     *
     * @param array<mixed> $synonyms
     * @throws InvalidArgumentException If an entry is not a SynonymConfiguration
     */
    private function validateSynonyms(array $synonyms): void
    {
        foreach ($synonyms as $index => $synonym) {
            if (!$synonym instanceof SynonymConfiguration) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Synonym at index %d must be an instance of SynonymConfiguration.',
                        $index
                    ),
                    'synonyms',
                    $synonym
                );
            }
        }
    }
}
