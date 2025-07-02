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
     * Create a float field
     */
    public static function float(): FieldConfig
    {
        return new FieldConfig(FieldType::FLOAT);
    }

    /**
     * Create an integer field
     */
    public static function integer(): FieldConfig
    {
        return new FieldConfig(FieldType::INTEGER);
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
     * Create a features field configuration
     *
     * @param array<string, FieldConfig> $featureFields
     * @return FieldConfig
     */
    public static function features(array $featureFields = []): FieldConfig
    {
        return new FieldConfig(FieldType::NAME_VALUE_LIST, null, $featureFields);
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
            'price' => self::integer(),
            'formattedPrice' => self::keyword(),
            'categoryDefault' => self::textKeyword(),
            'categories' => self::hierarchy(),
            'sku' => self::keyword(),
            'variants' => self::variants([
                'color' => self::keyword(),
                'size' => self::keyword(),
            ]),
            'features' => self::features(
                []
            ),
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
