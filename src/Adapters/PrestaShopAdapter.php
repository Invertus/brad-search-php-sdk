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
            if (!is_array($product)) {
                continue;
            }
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
            'inStock' => $this->validateBooleanField($product['inStock'] ?? null),
            'isNew' => $this->validateBooleanField($product['isNew'] ?? null),
            'variants' => [],
        ];

        // Handle localized product name
        $this->addLocalizedField($result, 'name', $product['localizedNames'] ?? []);

        if (isset($product['description']) && is_array($product['description'])) {
            $this->addLocalizedField($result, 'description', $product['description']);
        }

        if (isset($product['descriptionShort']) && is_array($product['descriptionShort'])) {
            $this->addLocalizedField($result, 'descriptionShort', $product['descriptionShort']);
        }

        // Handle brand
        if (
            isset($product['brand']) && is_array($product['brand']) &&
            isset($product['brand']['localizedNames']) && is_array($product['brand']['localizedNames'])
        ) {
            $this->addLocalizedField($result, 'brand', $product['brand']['localizedNames']);
        }

        // Handle categories (flatten all levels)
        $this->extractCategories($result, $product);

        $this->extractCategoryDefault($result, $product);

        // Handle image URLs
        if (isset($product['imageUrl']) && is_array($product['imageUrl'])) {
            $result['imageUrl'] = $this->transformImageUrl($product['imageUrl']);
        }

        // Handle product URL
        if (isset($product['productUrl']) && is_array($product['productUrl'])) {
            $this->transformProductUrls($result, $product['productUrl']);
        }

        $this->transformVariants($result, (array)($product['variants'] ?? []));

        $this->transformFeatures($result, (array)($product['features'] ?? []));

        return $result;
    }

    private function transformFeatures(array &$result, array $features): void
    {
        $featuresByLocale = [];

        foreach ($features as $feature) {
            if (!is_array($feature) || !isset($feature['localizedNames']) || !isset($feature['localizedValues'])) {
                continue;
            }

            if (!is_array($feature['localizedNames']) || !is_array($feature['localizedValues'])) {
                continue;
            }

            foreach ($feature['localizedNames'] as $locale => $name) {
                if (
                    $locale === null || $name === null || $name === '' ||
                    !isset($feature['localizedValues'][$locale]) ||
                    $feature['localizedValues'][$locale] === null ||
                    $feature['localizedValues'][$locale] === ''
                ) {
                    continue;
                }

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
            if (!is_string($locale) || $locale === '' || $url === null || $url === '') {
                continue;
            }

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
        $variantsByLocale = [];

        foreach ($variants as $variant) {
            if (!is_array($variant) || !isset($variant['remoteId']) || $variant['remoteId'] === null) {
                continue;
            }

            $locales = $this->getAllLocalesFromVariant($variant);

            if (empty($locales)) {
                continue;
            }

            foreach ($locales as $locale) {
                if ($locale === null || !is_string($locale) || $locale === '') {
                    continue;
                }

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
        }


        foreach ($variantsByLocale as $key => $variants) {
            $result[$key] = $variants;
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
        if (isset($variant['productUrl']['localizedValues']) && is_array($variant['productUrl']['localizedValues'])) {
            $locales = array_merge($locales, array_keys($variant['productUrl']['localizedValues']));
        }

        // Get locales from attributes
        if (isset($variant['attributes']) && is_array($variant['attributes'])) {
            foreach ($variant['attributes'] as $attributeData) {
                if (is_array($attributeData) && isset($attributeData['localizedValues']) && is_array($attributeData['localizedValues'])) {
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
            if (
                !is_string($attributeName) || $attributeName === '' ||
                !is_array($attributeData) ||
                !isset($attributeData['localizedValues'][$locale]) ||
                $attributeData['localizedValues'][$locale] === null ||
                $attributeData['localizedValues'][$locale] === ''
            ) {
                continue;
            }

            $transformedAttributes[] = [
                'name' => strtolower($attributeName),
                'value' => $attributeData['localizedValues'][$locale]
            ];
        }

        return $transformedAttributes;
    }



    /**
     * Extract categories from all levels and flatten them
     */
    private function extractCategories(array &$result, array $product): void
    {
        $fieldName = 'categories';
        // Always initialize categories array
        $result[$fieldName] = [];

        if (!isset($product[$fieldName]) || !is_array($product[$fieldName])) {
            return;
        }

        // Process all category levels (lvl2, lvl3, lvl4, etc.)
        foreach ($product[$fieldName] as $level => $levelCategories) {
            if (!is_array($levelCategories)) {
                continue;
            }

            foreach ($levelCategories as $category) {
                $this->extractCategory($category, $fieldName, $result);
            }
        }
    }

    private function extractCategory(array $category, string $fieldName, array &$result): void
    {
        if (
            !is_array($category) ||
            !isset($category['localizedValues']) ||
            !is_array($category['localizedValues']) ||
            !isset($category['localizedValues']['path']) ||
            !is_array($category['localizedValues']['path'])
        ) {
            return;
        }

        foreach ($category['localizedValues']['path'] as $locale => $path) {
            if (
                !is_string($locale) || $locale === '' ||
                $path === null || $path === ''
            ) {
                continue;
            }

            $key = $fieldName . ($locale === 'en-US' ? '' : '_' . $locale);

            switch ($fieldName) {
                case 'categories':
                    $result[$key][] = $path;
                    break;
                case 'categoryDefault':
                    $result[$key] = $path;
            }
        }
    }

    private function extractCategoryDefault(array &$result, array $product): void
    {
        $categoryFieldName = 'categoryDefault';
        $result[$categoryFieldName] = '';

        if (!isset($product[$categoryFieldName]) || !is_array($product[$categoryFieldName])) {
            return;
        }

        $this->extractCategory($product[$categoryFieldName], $categoryFieldName, $result);
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
            if (
                isset($imageUrl[$prestaShopSize]) &&
                $imageUrl[$prestaShopSize] !== null &&
                $imageUrl[$prestaShopSize] !== ''
            ) {
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
            if (
                !is_string($locale) || $locale === '' ||
                $value === null || $value === ''
            ) {
                continue;
            }

            // remove HTML tags from values.
            // TODO: check if does not remove any useful data?
            $cleanValue = strip_tags((string) $value);

            if ($locale === 'en-US') {
                $result[$fieldName] = $cleanValue;
            } else {
                $result["{$fieldName}_{$locale}"] = $cleanValue;
            }
        }
    }

    /**
     * Get required field with validation
     */
    private function getRequiredField(array $data, string $field): string
    {
        if (!isset($data[$field]) || $data[$field] === null) {
            throw new ValidationException("Required field '{$field}' is missing from PrestaShop data");
        }

        return (string) $data[$field];
    }

    /**
     * Validate and convert a field value to a proper boolean or null
     * 
     * @param mixed $value The value to validate
     * @return bool|null Returns true/false for valid boolean values, null for invalid/missing values
     */
    private function validateBooleanField($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        
        return $result;
    }
}
