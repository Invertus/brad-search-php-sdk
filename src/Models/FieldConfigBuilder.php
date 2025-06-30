<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

use BradSearch\SyncSdk\Enums\FieldType;

class FieldConfigBuilder
{
    /**
     * Create a text keyword field
     */
    public static function textKeyword(): FieldConfig
    {
        return new FieldConfig(FieldType::TEXT_KEYWORD);
    }

    /**
     * Create a text field
     */
    public static function text(): FieldConfig
    {
        return new FieldConfig(FieldType::TEXT);
    }

    /**
     * Create a keyword field
     */
    public static function keyword(): FieldConfig
    {
        return new FieldConfig(FieldType::KEYWORD);
    }

    /**
     * Create a hierarchy field
     */
    public static function hierarchy(): FieldConfig
    {
        return new FieldConfig(FieldType::HIERARCHY);
    }

    /**
     * Create a URL field
     */
    public static function url(): FieldConfig
    {
        return new FieldConfig(FieldType::URL);
    }

    /**
     * Create an image URL field
     */
    public static function imageUrl(): FieldConfig
    {
        return new FieldConfig(FieldType::IMAGE_URL);
    }

    /**
     * Create a variants field with attributes
     *
     * @param array<string, FieldConfig> $attributes
     */
    public static function variants(array $attributes): FieldConfig
    {
        return new FieldConfig(FieldType::VARIANTS, null, $attributes);
    }

    /**
     * Build field configuration for ecommerce products (similar to Go implementation)
     *
     * @return array<string, FieldConfig>
     */
    public static function ecommerceFields(): array
    {
        return [
            'id' => self::keyword(),
            'name' => self::textKeyword(),
            'brand' => self::textKeyword(),
            'categoryDefault' => self::textKeyword(),
            'categories' => self::hierarchy(),
            'sku' => self::keyword(),
            'variants' => self::variants([
                'color' => self::keyword(),
                'size' => self::keyword(),
            ]),
            'imageUrl' => self::imageUrl(),
            'productUrl' => self::url(),
            'descriptionShort' => self::textKeyword(),
        ];
    }

    /**
     * Add custom fields or override existing default eCommerce fields with custom values.
     * Default fields are defined in ecommerceFields() method.
     *
     * @param array<string, FieldConfig> $customFields
     * @return array<string, FieldConfig>
     */
    public static function addToEcommerceFields(array $customFields = []): array
    {
        $defaultFields = self::ecommerceFields();

        return array_replace($defaultFields, $customFields);
    }
}
