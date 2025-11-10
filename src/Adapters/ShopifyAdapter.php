<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class ShopifyAdapter
{
    public function __construct() {}

    /**
     * Transform Shopify GraphQL product data to BradSearch format
     *
     * @param array $shopifyData The Shopify GraphQL API response
     * @return array<array> Array with products and errors
     * @throws ValidationException
     */
    public function transform(array $shopifyData): array
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

        $transformedProducts = [];
        $errors = [];

        foreach ($shopifyData['data']['products']['edges'] as $index => $edge) {
            if (! is_array($edge)) {
                continue;
            }

            if (! isset($edge['node']) || ! is_array($edge['node'])) {
                continue;
            }

            try {
                $transformedProducts[] = $this->transformProduct($edge['node']);
            } catch (\Exception $e) {
                $errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => $this->extractNumericId($edge['node']['id'] ?? ''),
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
     * Transform a single Shopify product to BradSearch format
     */
    private function transformProduct(array $product): array
    {
        // Extract prices once to avoid duplicate calls
        $price = $this->extractPrice($product);
        $basePrice = $this->extractBasePrice($product);

        $result = [
            'id' => $this->getRequiredField($product, 'id', true),
            'name' => $this->getRequiredField($product, 'title'),
            'sku' => $this->extractMainSku($product),
            'price' => $price,
            'basePrice' => $basePrice,
            'priceTaxExcluded' => $price, // Shopify prices are typically tax-excluded
            'basePriceTaxExcluded' => $basePrice,
            'inStock' => $this->isInStock($product),
            'isNew' => false, // Shopify doesn't have explicit "new" flag
            'variants' => [],
        ];

        // Add description (strip HTML tags)
        if (isset($product['descriptionHtml']) && is_string($product['descriptionHtml'])) {
            $result['description'] = strip_tags($product['descriptionHtml']);
        }

        // Add brand (vendor in Shopify)
        if (isset($product['vendor']) && is_string($product['vendor']) && $product['vendor'] !== '') {
            $result['brand'] = $product['vendor'];
        }

        // Add category default (productType in Shopify)
        // Always set to match PrestaShopAdapter behavior (defaults to empty string)
        $result['categoryDefault'] = '';
        if (isset($product['productType']) && is_string($product['productType']) && $product['productType'] !== '') {
            $result['categoryDefault'] = $product['productType'];
        }

        // Extract categories from productType and tags
        $result['categories'] = $this->extractCategories($product);

        // Handle images - only add if images exist
        if (isset($product['images']) && is_array($product['images'])) {
            $imageUrl = $this->extractImages($product['images']);
            if (!empty($imageUrl)) {
                $result['imageUrl'] = $imageUrl;
            }
        }

        // Transform variants
        if (isset($product['variants']) && is_array($product['variants'])) {
            $result['variants'] = $this->transformVariants($product['variants']);
        }

        return $result;
    }

    /**
     * Extract numeric ID from Shopify GID format
     * Converts "gid://shopify/Product/6843600694995" to "6843600694995"
     * Returns empty string for malformed GIDs that don't match the expected format
     */
    private function extractNumericId(string $gid): string
    {
        if ($gid === '') {
            return '';
        }

        // Parse URL to safely extract path component (handles query parameters)
        $path = parse_url($gid, PHP_URL_PATH);
        if ($path === false || $path === null) {
            return '';
        }

        // Extract ID from format: gid://shopify/Resource/123456
        // Only accept purely numeric IDs - return empty string for malformed GIDs
        if (preg_match('/\/(\d+)$/', $path, $matches)) {
            return $matches[1];
        }

        // Malformed GID - return empty string
        return '';
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
     * Extract maximum price as base price (compareAt price would be better, but not in main product)
     * Returns string to match PrestaShopAdapter format
     */
    private function extractBasePrice(array $product): string
    {
        $amount = $this->getNestedValue($product, ['priceRangeV2', 'maxVariantPrice', 'amount']);

        if (is_string($amount)) {
            return $amount;
        }

        // Fallback to min price if max not available
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
        if (! isset($product['variants']['edges']) || ! is_array($product['variants']['edges'])) {
            return false;
        }

        // Check if any variant is available for sale
        foreach ($product['variants']['edges'] as $edge) {
            if (
                isset($edge['node']['availableForSale']) &&
                $edge['node']['availableForSale'] === true
            ) {
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
        $categories = [];

        // Add productType as primary category
        if (
            isset($product['productType']) &&
            is_string($product['productType']) &&
            $product['productType'] !== ''
        ) {
            $categories[] = $product['productType'];
        }

        // Add tags as additional categories
        if (isset($product['tags']) && is_array($product['tags'])) {
            foreach ($product['tags'] as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $categories[] = $tag;
                }
            }
        }

        return array_unique($categories);
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
        // For medium, pick one from the middle range, or the last if few images
        $mediumIndex = count($images) > 1 ? (int) floor(count($images) / 2) : 0;
        $mediumImage = $images[$mediumIndex]['url'];

        return [
            'small' => $smallImage,
            'medium' => $mediumImage,
        ];
    }

    /**
     * Transform Shopify variants to BradSearch format
     */
    private function transformVariants(array $variantsData): array
    {
        if (! isset($variantsData['edges']) || ! is_array($variantsData['edges'])) {
            return [];
        }

        $variants = [];

        foreach ($variantsData['edges'] as $edge) {
            if (! isset($edge['node']) || ! is_array($edge['node'])) {
                continue;
            }

            $variant = $edge['node'];

            if (! isset($variant['id'])) {
                continue;
            }

            $transformedVariant = [
                'id' => $this->extractNumericId($variant['id']),
                'sku' => $variant['sku'] ?? '',
                'attributes' => $this->transformVariantOptions($variant['selectedOptions'] ?? []),
            ];

            $variants[] = $transformedVariant;
        }

        return $variants;
    }

    /**
     * Transform variant selectedOptions to BradSearch attributes format
     */
    private function transformVariantOptions(array $selectedOptions): array
    {
        if (! is_array($selectedOptions)) {
            return [];
        }

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
