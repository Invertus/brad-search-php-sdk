<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a variant attribute configuration for a variants field.
 *
 * Variant attributes define the searchable/filterable properties of product variants,
 * such as size, color, or material.
 */
final readonly class VariantAttribute extends ValueObject
{
    public function __construct(
        public string $id,
        public FieldType $type,
        public bool $localeAware = false
    ) {
        if ($id === '') {
            throw new InvalidArgumentException(
                'Variant attribute id cannot be empty.',
                'id',
                $id
            );
        }
    }

    /**
     * Returns a new instance with a different locale awareness setting.
     */
    public function withLocaleAware(bool $localeAware): self
    {
        return new self($this->id, $this->type, $localeAware);
    }

    /**
     * Returns a new instance with a different type.
     */
    public function withType(FieldType $type): self
    {
        return new self($this->id, $type, $this->localeAware);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'locale_aware' => $this->localeAware,
        ];
    }
}
