<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Common;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidLocaleException;
use Stringable;

/**
 * Represents a localized field with a base name and locale suffix.
 *
 * This helper generates locale-suffixed field names for multilingual content.
 * Accepts both short codes and BCP 47: `new LocalizedField('name', 'lt')` → `name_lt`,
 * `new LocalizedField('name', 'lt-LT')` → `name_lt-LT`.
 */
final readonly class LocalizedField implements Stringable
{
    private const LOCALE_PATTERN = '/^[a-z]{2}(-[A-Z]{2})?$/';

    private string $locale;

    public function __construct(
        private string $baseName,
        string $locale
    ) {
        if ($baseName === '') {
            throw new InvalidArgumentException(
                'Base field name cannot be empty.',
                'baseName',
                $baseName
            );
        }

        if (!preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new InvalidLocaleException($locale);
        }

        $this->locale = $locale;
    }

    /**
     * Returns the suffixed field name (e.g., "name_lt-LT").
     */
    public function toString(): string
    {
        return $this->baseName . '_' . $this->locale;
    }

    /**
     * Returns the suffixed field name (e.g., "name_lt-LT").
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the base field name without locale suffix.
     */
    public function getBaseName(): string
    {
        return $this->baseName;
    }

    /**
     * Returns the locale identifier.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns a new instance with a different locale.
     */
    public function withLocale(string $locale): self
    {
        return new self($this->baseName, $locale);
    }
}
