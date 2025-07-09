<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class PrestaShopAdapter
{
    public function __construct() {}

    /**
     * Transform PrestaShop product data to BradSearch format
     * 
     * @param array $prestaShopData The PrestaShop API response
     * @return array<array> Array of products in BradSearch format
     */
    public function transform(array $prestaShopData): array
    {
        if (!isset($prestaShopData['products']) || !is_array($prestaShopData['products'])) {
            throw new ValidationException('Invalid PrestaShop data: missing products array');
        }

        $transformedProducts = [];

        foreach ($prestaShopData['products'] as $product) {
            $transformedProducts[] = $this->transformProduct($product);
        }

        return $transformedProducts;
    }

    /**
     * Transform a single PrestaShop product to BradSearch format
     */
    private function transformProduct(array $product): array
    {
        $result = [
            'id' => $this->getRequiredField($product, 'remoteId'),
            'sku' => $this->getRequiredField($product, 'sku'),
            'price' => $this->getRequiredField($product, 'price'),
            'formattedPrice' => $this->getRequiredField($product, 'formattedPrice'),
            'variants' => [],
        ];

        // Handle localized product name
        $this->addLocalizedField($result, 'name', $product['localizedNames'] ?? []);

        // Handle brand
        if (isset($product['brand']['localizedNames'])) {
            $this->addLocalizedField($result, 'brand', $product['brand']['localizedNames']);
        }

        // Handle categories (flatten all levels)
        $this->extractCategories($result, $product);

        // Handle image URLs
        if (isset($product['imageUrl'])) {
            $result['imageUrl'] = $this->transformImageUrl($product['imageUrl']);
        }

        // Handle product URL
        if (isset($product['productUrl'])) {
            $this->transformProductUrls($result, $product['productUrl']);
        }

        $this->transformVariants($result, $product['variants'] ?? []);

        // Handle features
        if (isset($product['features']) && is_array($product['features'])) {
            $this->transformFeatures($result, $product['features']);
        }

        return $result;
    }

    private function transformFeatures(array &$result, array $features): void
    {
        $transformedFeatures = [];
        $featuresByLocale = [];

        foreach ($features as $feature) {
            if (!isset($feature['localizedNames']) || !isset($feature['localizedValues'])) {
                continue;
            }

            foreach ($feature['localizedNames'] as $locale => $name) {
                $featuresByLocale[$locale][] = [
                    'name' => $name,
                    'value' => $feature['localizedValues'][$locale]
                ];
            }
        }

        foreach ($featuresByLocale as $locale => $features) {
            if ($locale === 'en-US') {
                $result['features'] = $features;
            } else {
                $result["features_{$locale}"] = $features;
            }
        }
    }

    /**
     * Transform product URLs to create localized fields
     */
    private function transformProductUrls(array &$result, array $productUrls): void
    {
        // Root level product URLs have flat structure: {"en-US": "url", "lt-LT": "url"}
        foreach ($productUrls as $locale => $url) {
            if ($locale === 'en-US') {
                $result['productUrl'] = $url;
            } else {
                $result["productUrl_{$locale}"] = $url;
            }
        }
    }

    /**
     * Transform PrestaShop variants to BradSearch format
     */
    private function transformVariants(array &$result, array $variants): void
    {
        if (empty($variants)) {
            return;
        }
        $variantsByLocale = [];

        foreach ($variants as $variant) {
            if (!isset($variant['remoteId'])) {
                continue;
            }

            $locales = $this->getAllLocalesFromVariant($variant);

            foreach ($locales as $locale) {
                $transformedVariant = [
                    'id' => (string) $variant['remoteId'],
                    'sku' => $variant['sku'] ?? '',
                    'url' => $this->getLocaleSpecificUrl($variant['productUrl']['localizedValues'] ?? [], $locale),
                    'attributes' => $this->transformVariantAttributesForLocale($variant['attributes'] ?? [], $locale)
                ];

                if ($locale === 'en-US') {
                    $variantsByLocale['variants'][] = $transformedVariant;
                } else {
                    $variantsByLocale["variants_{$locale}"][] = $transformedVariant;
                }
            }

            foreach ($variantsByLocale as $key => $variants) {
                $result[$key] = $variants;
            }
        }
    }

    private function extractFirstLocaleValue(array $localizedValues): string
    {
        return array_values($localizedValues)[0] ?? '';
    }

    /**
     * Get all locales available in a variant (from attributes and URLs)
     */
    private function getAllLocalesFromVariant(array $variant): array
    {
        $locales = [];

        // Get locales from product URLs
        if (isset($variant['productUrl']['localizedValues'])) {
            $locales = array_merge($locales, array_keys($variant['productUrl']['localizedValues']));
        }

        // Get locales from attributes
        if (isset($variant['attributes'])) {
            foreach ($variant['attributes'] as $attributeData) {
                if (isset($attributeData['localizedValues'])) {
                    $locales = array_merge($locales, array_keys($attributeData['localizedValues']));
                }
            }
        }

        return array_unique($locales);
    }

    /**
     * Get URL for a specific locale
     */
    private function getLocaleSpecificUrl(array $localizedValues, string $locale): string
    {
        return $localizedValues[$locale] ?? $this->extractFirstLocaleValue($localizedValues);
    }

    /**
     * Transform variant attributes for a specific locale only
     */
    private function transformVariantAttributesForLocale(array $attributes, string $locale): array
    {
        $transformedAttributes = [];

        foreach ($attributes as $attributeName => $attributeData) {
            if (isset($attributeData['localizedValues'][$locale])) {
                $transformedAttributes[] = [
                    'name' => strtolower($attributeName),
                    'value' => $attributeData['localizedValues'][$locale]
                ];
            }
        }

        return $transformedAttributes;
    }



    /**
     * Extract categories from all levels and flatten them
     */
    private function extractCategories(array &$result, array $product): void
    {
        if (!isset($product['categories'])) {
            $result['categories'] = [];
            return;
        }

        // Process all category levels (lvl2, lvl3, lvl4, etc.)
        foreach ($product['categories'] as $level => $levelCategories) {
            if (!is_array($levelCategories)) {
                continue;
            }

            foreach ($levelCategories as $category) {
                if (isset($category['localizedValues']['path'])) {
                    foreach ($category['localizedValues']['path'] as $locale => $path) {
                        if ($locale === 'en-US') {
                            $result['categories'][] = $path;
                        } else {
                            $result["categories_{$locale}"][] = $path;
                        }
                    }
                }
            }
        }
    }

    /**
     * Transform image URL structure
     */
    private function transformImageUrl(array $imageUrl): array
    {
        $result = [];

        // Map standard image sizes
        $sizeMapping = [
            'small' => 'small',
            'medium' => 'medium'
        ];

        foreach ($sizeMapping as $bradSearchSize => $prestaShopSize) {
            if (isset($imageUrl[$prestaShopSize])) {
                $result[$bradSearchSize] = $imageUrl[$prestaShopSize];
            }
        }

        return $result;
    }

    /**
     * Add localized field with support for multiple locales
     */
    private function addLocalizedField(array &$result, string $fieldName, array $localizedValues): void
    {
        if (empty($localizedValues)) {
            return;
        }

        foreach ($localizedValues as $locale => $value) {
            if ($locale === 'en-US') {
                $result[$fieldName] = $value;
            } else {
                $result["{$fieldName}_{$locale}"] = $value;
            }
        }
    }

    /**
     * Get required field with validation
     */
    private function getRequiredField(array $data, string $field): string
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Required field '{$field}' is missing from PrestaShop data");
        }

        return (string) $data[$field];
    }
}
