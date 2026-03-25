<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\Common\LocaleNormalizer;

class ShopifyAdapter
{
    public function __construct()
    {
        if (!function_exists('bccomp')) {
            throw new \RuntimeException(
                'ShopifyAdapter requires the bcmath PHP extension for precise price comparisons. ' .
                'Please install or enable ext-bcmath.'
            );
        }
    }

    /**
     * Transform Shopify GraphQL product data to BradSearch format.
     *
     * When locales are provided, locale-aware fields (name, description, brand, etc.)
     * are output with BCP 47 locale suffixes (e.g., "name_en-US", "name_lt-LT").
     * All locales receive the same content (primary language) until translation support is added.
     *
     * @param array $shopifyData The Shopify GraphQL API response
     * @param array<string> $locales Locale codes (e.g., ['en', 'lt'] or ['en-US', 'lt-LT'])
     * @return array<array> Array with products and errors
     * @throws ValidationException
     */
    public function transform(array $shopifyData, array $locales = []): array
    {
        // Validate basic structure
        if (! isset($shopifyData['data'])) {
            throw new ValidationException('Invalid Shopify data: missing data field');
        }

        if (! isset($shopifyData['data']['products'])) {
            throw new ValidationException('Invalid Shopify data: missing products field');
        }

        // Handle both empty results and missing edges gracefully
        if (! isset($shopifyData['data']['products']['edges'])) {
            // Empty response or different structure
            return [
                'products' => [],
                'errors' => [],
            ];
        }

        if (! is_array($shopifyData['data']['products']['edges'])) {
            throw new ValidationException('Invalid Shopify data: products edges must be an array');
        }

        // Normalize locales to BCP 47 (e.g., "lt" → "lt-LT")
        $normalizedLocales = ! empty($locales) ? LocaleNormalizer::normalizeAll($locales) : [];

        // Build locale mapping: normalized BCP 47 → raw short code (for looking up translations)
        $localeMap = [];
        foreach ($locales as $raw) {
            $localeMap[LocaleNormalizer::normalize($raw)] = $raw;
        }

        // Determine primary locale from response metadata (if available)
        $rawPrimary = $shopifyData['locales']['primary'] ?? ($locales[0] ?? 'en');
        $primaryLocale = LocaleNormalizer::normalize($rawPrimary);

        $transformedProducts = [];
        $errors = [];

        foreach ($shopifyData['data']['products']['edges'] as $index => $edge) {
            if (! is_array($edge) || ! isset($edge['node']) || ! is_array($edge['node'])) {
                $errors[] = [
                    'type' => 'invalid_structure',
                    'product_index' => $index,
                    'product_id' => '',
                    'message' => 'Skipping product due to malformed edge or node structure.',
                    'exception' => null,
                ];
                continue;
            }

            try {
                $transformedProducts[] = $this->transformProduct($edge['node'], $normalizedLocales, $primaryLocale, $localeMap);
            } catch (\Throwable $e) {
                $productId = $this->getNumericIdOrGid($edge['node']['id'] ?? '');

                $errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => $productId,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
            }
        }

        return [
            'products' => $transformedProducts,
            'errors' => $errors,
        ];
    }

    /**
     * Transform a single Shopify product to BradSearch format.
     *
     * @param array<string, mixed> $product Shopify product node (may include translations)
     * @param array<string> $locales Normalized BCP 47 locale codes
     * @param string $primaryLocale Normalized BCP 47 primary locale
     * @param array<string, string> $localeMap Normalized BCP 47 → raw short code mapping
     */
    private function transformProduct(array $product, array $locales, string $primaryLocale, array $localeMap): array
    {
        // Extract prices once to avoid duplicate calls
        $price = $this->extractPrice($product);
        $basePrice = $this->extractBasePrice($product);

        $result = [
            'id' => $this->getRequiredField($product, 'id', true),
            'sku' => $this->extractMainSku($product),
            'price' => $price,
            'basePrice' => $basePrice,
            'priceTaxExcluded' => $price, // Shopify prices are typically tax-excluded
            'basePriceTaxExcluded' => $basePrice,
            'inStock' => $this->isInStock($product),
            'isNew' => false, // Shopify doesn't have explicit "new" flag
            'variants' => [],
        ];

        // Extract primary locale field values
        $title = $this->getRequiredField($product, 'title');
        $description = '';
        if (isset($product['descriptionHtml']) && is_string($product['descriptionHtml'])) {
            $description = strip_tags($product['descriptionHtml']);
        }
        $brand = (isset($product['vendor']) && is_string($product['vendor']) && $product['vendor'] !== '')
            ? $product['vendor']
            : '';
        $categoryDefault = (isset($product['productType']) && is_string($product['productType']) && $product['productType'] !== '')
            ? $product['productType']
            : '';
        $categories = $this->extractCategories($product);
        $productUrl = $this->extractProductUrl($product);

        // Translation data keyed by raw locale code: ['lt' => [{key, value}, ...]]
        $translations = $product['translations'] ?? [];

        // Output locale-aware fields with BCP 47 suffixes
        if (! empty($locales)) {
            foreach ($locales as $locale) {
                if ($locale === $primaryLocale) {
                    // Primary locale: use the product's native fields
                    $result["name_{$locale}"] = $title;
                    $result["description_{$locale}"] = $description;
                } else {
                    // Non-primary locale: use translation if available, fall back to primary
                    $rawLocale = $localeMap[$locale] ?? null;
                    $localeTranslations = ($rawLocale !== null && isset($translations[$rawLocale]) && is_array($translations[$rawLocale]))
                        ? $translations[$rawLocale]
                        : null;

                    $translatedTitle = $localeTranslations !== null ? $this->getTranslationValue($localeTranslations, 'title') : null;
                    $result["name_{$locale}"] = $translatedTitle ?? $title;

                    $translatedDescription = $localeTranslations !== null ? $this->getTranslationValue($localeTranslations, 'body_html') : null;
                    $result["description_{$locale}"] = $translatedDescription !== null ? strip_tags($translatedDescription) : $description;
                }

                // Non-translatable fields: same across all locales
                if ($brand !== '') {
                    $result["brand_{$locale}"] = $brand;
                }
                $result["categoryDefault_{$locale}"] = $categoryDefault;
                $result["categories_{$locale}"] = $categories;
                if ($productUrl !== '') {
                    $result["productUrl_{$locale}"] = $productUrl;
                }
            }
        } else {
            // No locales: plain field names (backward compatible)
            $result['name'] = $title;
            if ($description !== '') {
                $result['description'] = $description;
            }
            if ($brand !== '') {
                $result['brand'] = $brand;
            }
            $result['categoryDefault'] = $categoryDefault;
            $result['categories'] = $categories;
            if ($productUrl !== '') {
                $result['productUrl'] = $productUrl;
            }
        }

        // Handle images - only add if images exist
        if (isset($product['images']) && is_array($product['images'])) {
            $imageUrl = $this->extractImages($product['images']);
            if (! empty($imageUrl)) {
                $result['imageUrl'] = $imageUrl;
            }
        }

        // Transform variants
        if (isset($product['variants']) && is_array($product['variants'])) {
            $result['variants'] = $this->transformVariants($product['variants'], $locales);
        }

        return $result;
    }

    /**
     * Get a translated value from a Shopify translations array.
     *
     * @param array<int, array{key: string, value: string|null}> $translationData
     * @param string $key The Shopify translation key (e.g., "title", "body_html")
     */
    private function getTranslationValue(array $translationData, string $key): ?string
    {
        foreach ($translationData as $entry) {
            if (
                is_array($entry) &&
                isset($entry['key'], $entry['value']) &&
                $entry['key'] === $key &&
                is_string($entry['value']) &&
                $entry['value'] !== ''
            ) {
                return $entry['value'];
            }
        }

        return null;
    }

    /**
     * Extract product URL from onlineStoreUrl field.
     */
    private function extractProductUrl(array $product): string
    {
        if (isset($product['onlineStoreUrl']) && is_string($product['onlineStoreUrl'])) {
            return $product['onlineStoreUrl'];
        }

        return '';
    }

    /**
     * Extract numeric ID from Shopify GID format
     * Converts "gid://shopify/Product/6843600694995" to "6843600694995"
     * Returns empty string only for empty input
     * Throws ValidationException for malformed GIDs
     */
    private function extractNumericId(string $gid): string
    {
        if ($gid === '') {
            return '';
        }

        // Use preg_match for robust and clear extraction of the numeric ID
        if (preg_match('#/(\d+)$#', $gid, $matches)) {
            return $matches[1];
        }

        // Malformed GID - throw exception to surface data quality issues
        throw new ValidationException("Malformed Shopify GID: {$gid}");
    }

    /**
     * Safely extract numeric ID or return raw GID for error logging
     * Returns numeric ID if valid, otherwise returns the raw GID unchanged
     */
    private function getNumericIdOrGid(string $gid): string
    {
        if ($gid === '') {
            return '';
        }
        try {
            return $this->extractNumericId($gid);
        } catch (ValidationException) {
            return $gid;
        }
    }

    /**
     * Extract main SKU from the first variant
     */
    private function extractMainSku(array $product): string
    {
        $sku = $this->getNestedValue($product, ['variants', 'edges', 0, 'node', 'sku']);
        return is_string($sku) ? $sku : '';
    }

    /**
     * Extract minimum price from priceRangeV2
     * Returns string to match PrestaShopAdapter format
     */
    private function extractPrice(array $product): string
    {
        $amount = $this->getNestedValue($product, ['priceRangeV2', 'minVariantPrice', 'amount']);
        return is_string($amount) ? $amount : '0.00';
    }

    /**
     * Extract base price from variants' compareAtPrice
     * Uses the maximum compareAtPrice across all variants (represents original price before discount)
     * Returns string to match PrestaShopAdapter format
     */
    private function extractBasePrice(array $product): string
    {
        $maxCompareAtPrice = '0.00';
        $hasCompareAtPrice = false;

        foreach ($this->getNestedValue($product, ['variants', 'edges'], []) as $edge) {
            $price = $this->getNestedValue($edge, ['node', 'compareAtPrice']);

            if ($price !== null && bccomp((string) $price, $maxCompareAtPrice, 2) > 0) {
                $maxCompareAtPrice = (string) $price;
                $hasCompareAtPrice = true;
            }
        }

        if ($hasCompareAtPrice) {
            return $maxCompareAtPrice;
        }

        // Fallback to max variant price from priceRangeV2
        $amount = $this->getNestedValue($product, ['priceRangeV2', 'maxVariantPrice', 'amount']);

        if (is_string($amount)) {
            return $amount;
        }

        // Final fallback to min price if max not available
        return $this->extractPrice($product);
    }

    /**
     * Safely access nested array values
     *
     * @param array $data The array to traverse
     * @param array $keys The keys to traverse (e.g., ['variants', 'edges', 0, 'node', 'sku'])
     * @param mixed $default Default value if path doesn't exist or is invalid
     * @return mixed The value at the nested path or default
     */
    private function getNestedValue(array $data, array $keys, mixed $default = null): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Check if product is in stock based on variants
     */
    private function isInStock(array $product): bool
    {
        // Check if any variant is available for sale
        foreach ($this->getNestedValue($product, ['variants', 'edges'], []) as $edge) {
            if ($this->getNestedValue($edge, ['node', 'availableForSale']) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract categories from productType and tags
     */
    private function extractCategories(array $product): array
    {
        $rawCategories = [];
        if (isset($product['productType'])) {
            $rawCategories[] = $product['productType'];
        }
        if (isset($product['tags']) && is_array($product['tags'])) {
            $rawCategories = array_merge($rawCategories, $product['tags']);
        }

        $validCategories = array_filter($rawCategories, fn($c) => is_string($c) && $c !== '');

        return array_values(array_unique($validCategories));
    }

    /**
     * Extract and format images
     * Intelligently selects images based on dimensions when available
     */
    private function extractImages(array $imagesData): array
    {
        if (! isset($imagesData['edges']) || ! is_array($imagesData['edges'])) {
            return [];
        }

        $images = [];
        foreach ($imagesData['edges'] as $edge) {
            if (isset($edge['node']['url']) && is_string($edge['node']['url'])) {
                $images[] = [
                    'url' => $edge['node']['url'],
                    'width' => $edge['node']['width'] ?? 0,
                    'height' => $edge['node']['height'] ?? 0,
                ];
            }
        }

        if (empty($images)) {
            return [];
        }

        // Sort images by width to intelligently select small and medium sizes
        usort($images, fn($a, $b) => $a['width'] <=> $b['width']);

        $smallImage = $images[0]['url'];
        // For medium, pick one from the middle range
        $mediumIndex = (int) floor(count($images) / 2);
        $mediumImage = $images[$mediumIndex]['url'];

        return [
            'small' => $smallImage,
            'medium' => $mediumImage,
        ];
    }

    /**
     * Transform Shopify variants to BradSearch format.
     *
     * @param array<string, mixed> $variantsData Shopify variants data
     * @param array<string> $locales Normalized BCP 47 locale codes
     */
    private function transformVariants(array $variantsData, array $locales): array
    {
        if (! isset($variantsData['edges']) || ! is_array($variantsData['edges'])) {
            return [];
        }

        $variants = [];

        foreach ($variantsData['edges'] as $edge) {
            if (! isset($edge['node']) || ! is_array($edge['node'])) {
                throw new ValidationException('Variant has malformed node structure');
            }

            $variant = $edge['node'];

            if (! isset($variant['id'])) {
                throw new ValidationException('Variant is missing required ID field');
            }

            $transformedVariant = [
                'id' => $this->extractNumericId($variant['id']),
                'sku' => $variant['sku'] ?? '',
            ];

            // Use locale-keyed attrs format when locales are provided
            if (! empty($locales)) {
                $transformedVariant['attrs'] = $this->transformVariantOptionsWithLocales(
                    $variant['selectedOptions'] ?? [],
                    $locales
                );
            } else {
                $transformedVariant['attributes'] = $this->transformVariantOptions($variant['selectedOptions'] ?? []);
            }

            $variants[] = $transformedVariant;
        }

        return $variants;
    }

    /**
     * Transform variant selectedOptions to locale-keyed attrs format.
     *
     * Output format: {"color": {"en-US": "White", "lt-LT": "White"}}
     *
     * @param array<int, array{name: string, value: string}> $selectedOptions
     * @param array<string> $locales
     * @return array<string, array<string, string>>
     */
    private function transformVariantOptionsWithLocales(array $selectedOptions, array $locales): array
    {
        $attrs = [];

        foreach ($selectedOptions as $option) {
            if (
                ! is_array($option) ||
                ! isset($option['name']) ||
                ! isset($option['value']) ||
                ! is_string($option['name']) ||
                ! is_string($option['value']) ||
                $option['name'] === '' ||
                $option['value'] === ''
            ) {
                continue;
            }

            $name = strtolower($option['name']);
            $localeValues = [];
            foreach ($locales as $locale) {
                $localeValues[$locale] = $option['value'];
            }
            $attrs[$name] = $localeValues;
        }

        return $attrs;
    }

    /**
     * Transform variant selectedOptions to flat attributes format (no locales).
     */
    private function transformVariantOptions(array $selectedOptions): array
    {
        $attributes = [];

        foreach ($selectedOptions as $option) {
            if (
                ! is_array($option) ||
                ! isset($option['name']) ||
                ! isset($option['value']) ||
                ! is_string($option['name']) ||
                ! is_string($option['value']) ||
                $option['name'] === '' ||
                $option['value'] === ''
            ) {
                continue;
            }

            $attributes[] = [
                'name' => strtolower($option['name']),
                'value' => $option['value'],
            ];
        }

        return $attributes;
    }

    /**
     * Get required field with validation
     * Note: Empty strings are allowed (consistent with PrestaShopAdapter)
     */
    private function getRequiredField(array $data, string $field, bool $extractId = false): string
    {
        if (! isset($data[$field]) || ! is_scalar($data[$field])) {
            throw new ValidationException("Required field '{$field}' is missing or not a scalar in Shopify data");
        }

        $value = (string) $data[$field];

        // Extract numeric ID from GID if needed
        if ($extractId) {
            return $this->extractNumericId($value);
        }

        return $value;
    }
}
