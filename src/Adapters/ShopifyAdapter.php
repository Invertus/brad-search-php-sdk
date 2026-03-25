<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class ShopifyAdapter
{
    private string $defaultPrimaryLocale;

    /** @var array<string> */
    private array $defaultAvailableLocales;

    /**
     * @param string $primaryLocale Default primary locale (e.g., "en", "lt", or "en-US")
     * @param array<string> $availableLocales Default available locales
     */
    public function __construct(string $primaryLocale = 'en', array $availableLocales = [])
    {
        if (!function_exists('bccomp')) {
            throw new \RuntimeException(
                'ShopifyAdapter requires the bcmath PHP extension for precise price comparisons. ' .
                'Please install or enable ext-bcmath.'
            );
        }

        $this->defaultPrimaryLocale = $primaryLocale;
        $this->defaultAvailableLocales = !empty($availableLocales) ? $availableLocales : [$primaryLocale];
    }

    /**
     * Transform Shopify GraphQL product data to BradSearch format
     *
     * @param array $shopifyData The Shopify GraphQL API response (with optional locales/translations enrichment)
     * @return array<array> Array with products and errors
     * @throws ValidationException
     */
    public function transform(array $shopifyData): array
    {
        if (! isset($shopifyData['data'])) {
            throw new ValidationException('Invalid Shopify data: missing data field');
        }

        if (! isset($shopifyData['data']['products'])) {
            throw new ValidationException('Invalid Shopify data: missing products field');
        }

        if (! isset($shopifyData['data']['products']['edges'])) {
            return [
                'products' => [],
                'errors' => [],
            ];
        }

        if (! is_array($shopifyData['data']['products']['edges'])) {
            throw new ValidationException('Invalid Shopify data: products edges must be an array');
        }

        // Resolve locale info: response-level overrides constructor defaults.
        // Locale codes are used as-is (e.g. "lt", "en" from Shopify, or "lt-LT" from PrestaShop).
        $primaryLocale = $shopifyData['locales']['primary'] ?? $this->defaultPrimaryLocale;
        $publishedLocales = $shopifyData['locales']['published'] ?? $this->defaultAvailableLocales;

        // Ensure primary is in published list
        if (!in_array($primaryLocale, $publishedLocales, true)) {
            array_unshift($publishedLocales, $primaryLocale);
        }

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
                $transformedProducts[] = $this->transformProduct($edge['node'], $primaryLocale, $publishedLocales);
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
     * Transform a single Shopify product to BradSearch format with locale-suffixed fields
     *
     * @param array<string> $publishedLocales Locale codes as-is (e.g. ["en", "lt"] or ["en-US", "lt-LT"])
     */
    private function transformProduct(array $product, string $primaryLocale, array $publishedLocales): array
    {
        $price = $this->extractPrice($product);
        $basePrice = $this->extractBasePrice($product);

        $result = [
            'id' => $this->getRequiredField($product, 'id', true),
            'sku' => $this->extractMainSku($product),
            'price' => $price,
            'basePrice' => $basePrice,
            'priceTaxExcluded' => $price,
            'basePriceTaxExcluded' => $basePrice,
            'inStock' => $this->isInStock($product),
            'isNew' => false,
            'variants' => [],
        ];

        // Primary locale translatable fields
        $title = $this->getRequiredField($product, 'title');
        $result["name_{$primaryLocale}"] = $title;

        $description = null;
        if (isset($product['descriptionHtml']) && is_string($product['descriptionHtml'])) {
            $description = strip_tags($product['descriptionHtml']);
            $result["description_{$primaryLocale}"] = $description;
        }

        // Non-translatable fields — replicate for all published locales
        $vendor = (isset($product['vendor']) && is_string($product['vendor']) && $product['vendor'] !== '') ? $product['vendor'] : null;
        $hasProductType = isset($product['productType']) && is_string($product['productType']) && $product['productType'] !== '';
        $categories = $this->extractCategories($product);
        $productUrl = $product['onlineStoreUrl'] ?? $product['onlineStorePreviewUrl'] ?? null;

        foreach ($publishedLocales as $locale) {
            if ($vendor !== null) {
                $result["brand_{$locale}"] = $vendor;
            }
            if ($hasProductType) {
                $result["categoryDefault_{$locale}"] = $product['productType'];
            }
            $result["categories_{$locale}"] = $categories;
            if (is_string($productUrl) && $productUrl !== '') {
                $result["productUrl_{$locale}"] = $productUrl;
            }
        }

        // Translations for non-primary locales
        // Translations are keyed by raw locale code (e.g. "lt") with array of {key, value, locale} entries
        $translations = $product['translations'] ?? [];
        foreach ($publishedLocales as $locale) {
            if ($locale === $primaryLocale) {
                continue;
            }

            $translationData = (isset($translations[$locale]) && is_array($translations[$locale]))
                ? $translations[$locale]
                : null;

            if ($translationData !== null) {
                $translatedTitle = $this->getTranslationValue($translationData, 'title');
                $result["name_{$locale}"] = ($translatedTitle !== null) ? $translatedTitle : $title;

                $translatedDescription = $this->getTranslationValue($translationData, 'body_html');
                if ($translatedDescription !== null) {
                    $result["description_{$locale}"] = strip_tags($translatedDescription);
                } elseif ($description !== null) {
                    $result["description_{$locale}"] = $description;
                }
            } else {
                // No translation — fall back to primary locale values
                $result["name_{$locale}"] = $title;
                if ($description !== null) {
                    $result["description_{$locale}"] = $description;
                }
            }
        }

        // Handle images
        if (isset($product['images']) && is_array($product['images'])) {
            $imageUrl = $this->extractImages($product['images']);
            if (!empty($imageUrl)) {
                $result['imageUrl'] = $imageUrl;
            }
        }

        // Transform variants with locale-aware attributes
        if (isset($product['variants']) && is_array($product['variants'])) {
            $result['variants'] = $this->transformVariants($product['variants'], $publishedLocales);
        }

        return $result;
    }

    /**
     * Get a translated value from a Shopify translations array
     *
     * @param array $translationData Array of [{key, value, locale}, ...]
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
     * Extract numeric ID from Shopify GID format
     */
    private function extractNumericId(string $gid): string
    {
        if ($gid === '') {
            return '';
        }

        if (preg_match('#/(\d+)$#', $gid, $matches)) {
            return $matches[1];
        }

        throw new ValidationException("Malformed Shopify GID: {$gid}");
    }

    /**
     * Safely extract numeric ID or return raw GID for error logging
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
     */
    private function extractPrice(array $product): string
    {
        $amount = $this->getNestedValue($product, ['priceRangeV2', 'minVariantPrice', 'amount']);
        return is_string($amount) ? $amount : '0.00';
    }

    /**
     * Extract base price from variants' compareAtPrice
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

        $amount = $this->getNestedValue($product, ['priceRangeV2', 'maxVariantPrice', 'amount']);

        if (is_string($amount)) {
            return $amount;
        }

        return $this->extractPrice($product);
    }

    /**
     * Safely access nested array values
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
        if (isset($product['productType']) && is_string($product['productType']) && $product['productType'] !== '') {
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

        usort($images, fn($a, $b) => $a['width'] <=> $b['width']);

        $smallImage = $images[0]['url'];
        $mediumIndex = (int) floor(count($images) / 2);
        $mediumImage = $images[$mediumIndex]['url'];

        return [
            'small' => $smallImage,
            'medium' => $mediumImage,
        ];
    }

    /**
     * Transform Shopify variants to BradSearch format with locale-aware attributes
     *
     * @param array<string> $publishedLocales Locale codes
     */
    private function transformVariants(array $variantsData, array $publishedLocales): array
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
                'attrs' => $this->transformVariantOptionsLocaleAware($variant['selectedOptions'] ?? [], $publishedLocales),
            ];

            $variants[] = $transformedVariant;
        }

        return $variants;
    }

    /**
     * Transform variant selectedOptions to locale-aware attributes format
     * Output: ['color' => ['en' => 'Red', 'lt' => 'Red'], ...]
     *
     * @param array<string> $publishedLocales Locale codes
     */
    private function transformVariantOptionsLocaleAware(array $selectedOptions, array $publishedLocales): array
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
            foreach ($publishedLocales as $locale) {
                $localeValues[$locale] = $option['value'];
            }
            $attrs[$name] = $localeValues;
        }

        return $attrs;
    }

    /**
     * Get required field with validation
     */
    private function getRequiredField(array $data, string $field, bool $extractId = false): string
    {
        if (! isset($data[$field]) || ! is_scalar($data[$field])) {
            throw new ValidationException("Required field '{$field}' is missing or not a scalar in Shopify data");
        }

        $value = (string) $data[$field];

        if ($extractId) {
            return $this->extractNumericId($value);
        }

        return $value;
    }
}
