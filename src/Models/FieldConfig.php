<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

use BradSearch\SyncSdk\Enums\FieldType;
use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

readonly class FieldConfig
{
    /**
     * @param array<string, FieldConfig>|null $properties
     * @param array<string, FieldConfig>|null $attributes
     * @param array<string, FieldConfig|mixed>|null $subfields Can contain FieldConfig objects OR raw config values for dynamic field creation
     */
    public function __construct(
        public FieldType $type,
        public ?array $properties = null,
        public ?array $attributes = null,
        public ?array $subfields = null,
    ) {
        $this->validate();
    }

    /**
     * Create FieldConfig from array representation
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type'])) {
            throw new InvalidFieldConfigException('Field type is required');
        }

        $type = FieldType::tryFrom($data['type']);
        if ($type === null) {
            throw new InvalidFieldConfigException("Invalid field type: {$data['type']}");
        }

        $properties = null;
        if (isset($data['properties']) && is_array($data['properties'])) {
            $properties = [];
            foreach ($data['properties'] as $key => $property) {
                $properties[$key] = self::fromArray($property);
            }
        }

        $attributes = null;
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $attributes = [];
            foreach ($data['attributes'] as $key => $attribute) {
                $attributes[$key] = self::fromArray($attribute);
            }
        }

        $subfields = null;
        if (isset($data['subfields']) && is_array($data['subfields'])) {
            $subfields = [];
            foreach ($data['subfields'] as $key => $subfield) {
                if (is_array($subfield) && isset($subfield['type'])) {
                    $subfields[$key] = self::fromArray($subfield);
                } else {
                    $subfields[$key] = $subfield;
                }
            }
        }

        return new self($type, $properties, $attributes, $subfields);
    }

    /**
     * Convert to array representation for API requests
     */
    public function toArray(): array
    {
        $data = ['type' => $this->type->value];

        if ($this->properties !== null) {
            $data['properties'] = array_map(
                fn(FieldConfig $config) => $config->toArray(),
                $this->properties
            );
        }

        if ($this->attributes !== null) {
            $data['attributes'] = array_map(
                fn(FieldConfig $config) => $config->toArray(),
                $this->attributes
            );
        }

        if ($this->subfields !== null) {
            $data['subfields'] = [];
            foreach ($this->subfields as $key => $value) {
                if ($value instanceof FieldConfig) {
                    // Standard FieldConfig object
                    $data['subfields'][$key] = $value->toArray();
                } else {
                    // Raw config value for dynamic field creation
                    $data['subfields'][$key] = $value;
                }
            }
        }

        return $data;
    }



    /**
     * Validate the field configuration
     */
    private function validate(): void
    {
        // Validate property and attribute names
        $this->validateFieldNames($this->properties, 'properties');
        $this->validateFieldNames($this->attributes, 'attributes');
        $this->validateFieldNames($this->subfields, 'subfields');
    }

    /**
     * @param array<string, FieldConfig|mixed>|null $fields
     */
    private function validateFieldNames(?array $fields, string $type): void
    {
        if ($fields === null) {
            return;
        }

        foreach (array_keys($fields) as $name) {
            if (!is_string($name) || empty(trim($name))) {
                throw new InvalidFieldConfigException("Invalid field name in {$type}: '{$name}'");
            }
        }
    }
}
