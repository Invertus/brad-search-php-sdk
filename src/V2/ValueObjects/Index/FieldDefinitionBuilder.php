<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Builder for creating FieldDefinition ValueObjects with fluent API.
 */
final class FieldDefinitionBuilder
{
    private ?string $name = null;
    private ?FieldType $type = null;

    /** @var array<VariantAttribute> */
    private array $attributes = [];

    /**
     * Sets the field name.
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Sets the field type.
     */
    public function type(FieldType $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Adds a variant attribute to the field.
     */
    public function addAttribute(VariantAttribute $attribute): self
    {
        $this->attributes[] = $attribute;
        return $this;
    }

    /**
     * Builds and returns the immutable FieldDefinition.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): FieldDefinition
    {
        if ($this->name === null) {
            throw new InvalidArgumentException(
                'Field name is required.',
                'name',
                null
            );
        }

        if ($this->type === null) {
            throw new InvalidArgumentException(
                'Field type is required.',
                'type',
                null
            );
        }

        return new FieldDefinition(
            $this->name,
            $this->type,
            $this->attributes
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->name = null;
        $this->type = null;
        $this->attributes = [];
        return $this;
    }
}
