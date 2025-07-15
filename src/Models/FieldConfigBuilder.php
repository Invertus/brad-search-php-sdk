<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

use BradSearch\SyncSdk\Enums\FieldType;

class FieldConfigBuilder
{
    /**
     * Create a text keyword field
     */
    public static function textKeyword(?array $subfields = null): FieldConfig
    {
        return new FieldConfig(FieldType::TEXT_KEYWORD, null, null, $subfields);
    }

    /**
     * Create a text field
     */
    public static function text(?array $subfields = null): FieldConfig
    {
        return new FieldConfig(FieldType::TEXT, null, null, $subfields);
    }

    /**
     * Create a keyword field
     */
    public static function keyword(?array $subfields = null): FieldConfig
    {
        return new FieldConfig(FieldType::KEYWORD, null, null, $subfields);
    }

    /**
     * Create a hierarchy field
     */
    public static function hierarchy(bool $embeddable = false): FieldConfig
    {
        return new FieldConfig(FieldType::HIERARCHY, null, null, $embeddable);
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
     * Create a double field
     */
    public static function double(): FieldConfig
    {
        return new FieldConfig(FieldType::DOUBLE);
    }

    /**
     * Create a variants field with attributes
     *
     * @param array<string, FieldConfig> $attributes
     */
    public static function variants(?array $attributes = null): FieldConfig
    {
        return new FieldConfig(FieldType::VARIANTS, null, $attributes);
    }


    /**
     * Create a features field configuration
     *
     * @param array<string, FieldConfig> $featureFields
     * @return FieldConfig
     */
    public static function features(?array $attributes = null): FieldConfig
    {
        return new FieldConfig(FieldType::NAME_VALUE_LIST, null, $attributes);
    }

    /**
     * Build field configuration for ecommerce products (similar to Go implementation)
     *
     * @return array<string, FieldConfig>
     */
    public static function ecommerceFields(array $locales): array
    {
        $localizedFields = [];
        foreach ($locales as $locale) {
            if (in_array($locale, ['en-US', 'en'])) {
                $localizedFields['name'] = self::textKeyword(true);
                $localizedFields['brand'] = self::textKeyword(true);
                $localizedFields['categoryDefault'] = self::textKeyword();
                $localizedFields['description'] = self::textKeyword(true);
                $localizedFields['categories'] = self::hierarchy(true);
                $localizedFields['descriptionShort'] = self::textKeyword();
                $localizedFields['productUrl'] = self::url();
            } else {
                $localizedFields["name_{$locale}"] = self::textKeyword(true);
                $localizedFields["brand_{$locale}"] = self::textKeyword(true);
                $localizedFields["categoryDefault_{$locale}"] = self::textKeyword();
                $localizedFields["categories_{$locale}"] = self::hierarchy(true);
                $localizedFields["descriptionShort_{$locale}"] = self::textKeyword();
                $localizedFields["description_{$locale}"] = self::textKeyword(true);
                $localizedFields["productUrl_{$locale}"] = self::url();
            }
        }

        $defaultFields = [
            'id' => self::keyword(),
            'name' => self::textKeyword(true),
            'brand' => self::textKeyword(true),
            'price' => self::double(),
            'formattedPrice' => self::keyword(),
            'categoryDefault' => self::textKeyword(),
            'categories' => self::hierarchy(true),
            'sku' => self::keyword(),
            'imageUrl' => self::imageUrl(),
            'productUrl' => self::url(),
            'descriptionShort' => self::textKeyword(),
            'description' => self::textKeyword(true),
        ];

        return array_merge($defaultFields, $localizedFields);
    }

    /**
     * Add custom fields or override existing default eCommerce fields with custom values.
     * Default fields are defined in ecommerceFields() method.
     *
     * @param array<string, FieldConfig> $customFields
     * @return array<string, FieldConfig>
     */
    public static function addToEcommerceFields(array $customFields = [], array $locales): array
    {
        $defaultFields = self::ecommerceFields($locales);

        return array_merge($defaultFields, $customFields);
    }
}
