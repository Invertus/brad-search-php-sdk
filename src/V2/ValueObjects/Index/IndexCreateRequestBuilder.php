<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Builder for creating IndexCreateRequest ValueObjects with fluent API.
 */
final class IndexCreateRequestBuilder
{
    /** @var array<string> */
    private array $locales = [];

    /** @var array<FieldDefinition> */
    private array $fields = [];

    /**
     * Adds a locale to the request.
     */
    public function addLocale(string $locale): self
    {
        $this->locales[] = $locale;
        return $this;
    }

    /**
     * Adds a field definition to the request.
     */
    public function addField(FieldDefinition $field): self
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Builds and returns the immutable IndexCreateRequest.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): IndexCreateRequest
    {
        if (count($this->locales) === 0) {
            throw new InvalidArgumentException(
                'At least one locale is required.',
                'locales',
                $this->locales
            );
        }

        if (count($this->fields) === 0) {
            throw new InvalidArgumentException(
                'At least one field is required.',
                'fields',
                $this->fields
            );
        }

        return new IndexCreateRequest(
            $this->locales,
            $this->fields
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->locales = [];
        $this->fields = [];
        return $this;
    }
}
