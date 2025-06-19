<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class PrestaShopAdapter
{
    private array $supportedLocales;
    private string $defaultLocale;

    /**
     * @param array<string> $supportedLocales List of supported locales (first one becomes default)
     */
    public function __construct(array $supportedLocales = ['en-US'])
    {
        if (empty($supportedLocales)) {
            throw new ValidationException('At least one locale must be specified');
        }
        
        $this->supportedLocales = $supportedLocales;
        $this->defaultLocale = $supportedLocales[0];
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
        $result['categories'] = $this->extractCategories($product);

        // Handle image URLs
        if (isset($product['imageUrl'])) {
            $result['imageUrl'] = $this->transformImageUrl($product['imageUrl']);
        }

        // Handle product URL
        if (isset($product['productUrl'])) {
            $this->addLocalizedField($result, 'productUrl', $product['productUrl']);
        }

        // Handle variants
        if (isset($product['variants']) && is_array($product['variants'])) {
            $result['variants'] = $this->transformVariants($product['variants']);
        }

        return $result;
    }

    /**
     * Transform PrestaShop variants to BradSearch format
     */
    private function transformVariants(array $variants): array
    {
        $transformedVariants = [];

        foreach ($variants as $variant) {
            if (!isset($variant['remoteId'])) {
                continue; // Skip variants without ID
            }

            $transformedVariant = [
                'id' => (string) $variant['remoteId'],
                'sku' => $variant['sku'] ?? '',
                'url' => $this->extractDefaultLocaleValue($variant['productUrl']['localizedValues'] ?? []),
                'attributes' => $this->transformVariantAttributes($variant['attributes'] ?? [])
            ];

            $transformedVariants[] = $transformedVariant;
        }

        return $transformedVariants;
    }

    /**
     * Transform variant attributes to BradSearch format
     */
    private function transformVariantAttributes(array $attributes): array
    {
        $transformedAttributes = [];

        foreach ($attributes as $attributeKey => $attributeData) {
            $attributeName = strtolower($attributeKey); // Normalize to lowercase
            $attributeValue = $this->extractDefaultLocaleValue($attributeData['localizedValues'] ?? []);
            
            if ($attributeValue !== null) {
                $transformedAttributes[$attributeName] = [
                    'name' => $attributeName,
                    'value' => $attributeValue
                ];
            }
        }

        return $transformedAttributes;
    }

    /**
     * Extract categories from all levels and flatten them
     */
    private function extractCategories(array $product): array
    {
        $categories = [];
        
        if (!isset($product['categories'])) {
            return $categories;
        }

        // Process all category levels (lvl2, lvl3, lvl4, etc.)
        foreach ($product['categories'] as $level => $levelCategories) {
            if (!is_array($levelCategories)) {
                continue;
            }

            foreach ($levelCategories as $category) {
                if (isset($category['localizedValues']['path'])) {
                    $path = $this->extractDefaultLocaleValue($category['localizedValues']['path']);
                    if ($path && !in_array($path, $categories, true)) {
                        $categories[] = $path;
                    }
                }
            }
        }

        return $categories;
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

        // Extract default locale value (first supported locale found)
        $defaultValue = $this->extractDefaultLocaleValue($localizedValues);
        if ($defaultValue !== null) {
            $result[$fieldName] = $defaultValue;
        }

        // Add additional locales with suffixes
        foreach ($this->supportedLocales as $index => $locale) {
            if ($index === 0) {
                continue; // Skip default locale (already added above)
            }

            if (isset($localizedValues[$locale])) {
                $result["{$fieldName}_{$locale}"] = $localizedValues[$locale];
            }
        }
    }

    /**
     * Extract value for the default locale
     */
    private function extractDefaultLocaleValue(array $localizedValues): ?string
    {
        if (empty($localizedValues)) {
            return null;
        }

        // Try to find value for default locale first
        if (isset($localizedValues[$this->defaultLocale])) {
            return $localizedValues[$this->defaultLocale];
        }

        // Try other supported locales
        foreach ($this->supportedLocales as $locale) {
            if (isset($localizedValues[$locale])) {
                return $localizedValues[$locale];
            }
        }

        // Fallback to first available value
        return array_values($localizedValues)[0] ?? null;
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

    /**
     * Get supported locales
     * 
     * @return array<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Get default locale
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
} 