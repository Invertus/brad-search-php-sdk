<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a field definition for an index.
 *
 * Field definitions specify the name, type, and optional attributes for index fields.
 * For VARIANTS type fields, attributes define the variant properties.
 */
final readonly class FieldDefinition extends ValueObject
{
    /**
     * @param string $name The field name
     * @param FieldType $type The field type
     * @param array<VariantAttribute> $attributes Optional variant attributes (for VARIANTS type)
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public array $attributes = []
    ) {
        if ($name === '') {
            throw new InvalidArgumentException(
                'Field name cannot be empty.',
                'name',
                $name
            );
        }

        foreach ($attributes as $index => $attribute) {
            if (!$attribute instanceof VariantAttribute) {
                throw new InvalidArgumentException(
                    sprintf('Attribute at index %d must be an instance of VariantAttribute.', $index),
                    'attributes',
                    $attribute
                );
            }
        }
    }

    /**
     * Returns a new instance with a different name.
     */
    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->attributes);
    }

    /**
     * Returns a new instance with a different type.
     */
    public function withType(FieldType $type): self
    {
        return new self($this->name, $type, $this->attributes);
    }

    /**
     * Returns a new instance with additional attributes.
     *
     * @param array<VariantAttribute> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self($this->name, $this->type, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type->value,
        ];

        if (count($this->attributes) > 0) {
            $result['attributes'] = array_map(
                fn(VariantAttribute $attr) => $attr->jsonSerialize(),
                $this->attributes
            );
        }

        return $result;
    }
}
