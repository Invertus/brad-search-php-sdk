<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class PrestaShopAdapter
{
    public function __construct()
    {
    }

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
            foreach ($product['productUrl'] as $url) {
                $result['productUrl'] = $url;
                break;
            }
        }

        // Handle variants
        if (isset($product['variants']) && is_array($product['variants'])) {
            $this->transformVariants($result, $product['variants']);
        }

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
     * Transform PrestaShop variants to BradSearch format
     */
    private function transformVariants(array &$result, array $variants): void
    {
        $transformedVariants = [];

        foreach ($variants as $variant) {
            if (!isset($variant['remoteId'])) {
                continue; // Skip variants without ID
            }

            $transformedVariant = [
                'id' => (string) $variant['remoteId'],
                'sku' => $variant['sku'] ?? '',
                'url' => $this->extractFirstLocaleValue($variant['productUrl']['localizedValues'] ?? []),
                'attributes' => $this->transformVariantAttributes($variant['attributes'] ?? [])
            ];

            $transformedVariants[] = $transformedVariant;
        }

        $result['variants'] = $transformedVariants;
    }

    private function extractFirstLocaleValue(array $localizedValues): string
    {
        return array_values($localizedValues)[0] ?? '';
    }

    /**
     * Transform variant attributes to BradSearch format
     */
    private function transformVariantAttributes(array $attributes): array
    {
        $transformedAttributes = [];

        foreach ($attributes as $attributeName => $attributeData) {
            foreach ($attributeData['localizedValues'] as $locale => $value) {
                if ($locale === 'en-US') {
                    $transformedAttributes[strtolower($attributeName)] = [
                        'name' => strtolower($attributeName),
                        'value' => $value
                    ];
                } else {
                    $transformedAttributes[strtolower($attributeName) . '_' . $locale] = [
                        'name' => strtolower($attributeName),
                        'value' => $value
                    ];
                }
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
