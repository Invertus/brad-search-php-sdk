<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class MagentoAdapter
{
    public function __construct()
    {
    }

    /**
     * Transform Magento GraphQL product data to BradSearch format
     *
     * This adapter performs minimal transformation - it validates required fields
     * (id, sku, name) and passes all other fields through unchanged.
     *
     * @param array $magentoData The Magento GraphQL API response
     * @return array{products: array, errors: array} Array with products and errors
     * @throws ValidationException
     */
    public function transform(array $magentoData): array
    {
        // Validate basic structure
        if (!isset($magentoData['data'])) {
            throw new ValidationException('Invalid Magento data: missing data field');
        }

        if (!isset($magentoData['data']['products'])) {
            throw new ValidationException('Invalid Magento data: missing products field');
        }

        // Handle empty results gracefully
        if (!isset($magentoData['data']['products']['items'])) {
            return [
                'products' => [],
                'errors' => [],
            ];
        }

        if (!is_array($magentoData['data']['products']['items'])) {
            throw new ValidationException('Invalid Magento data: products items must be an array');
        }

        $transformedProducts = [];
        $errors = [];

        foreach ($magentoData['data']['products']['items'] as $index => $item) {
            if (!is_array($item)) {
                $errors[] = [
                    'type' => 'invalid_structure',
                    'product_index' => $index,
                    'product_id' => '',
                    'message' => 'Skipping product due to invalid item structure.',
                    'exception' => null,
                ];
                continue;
            }

            try {
                $transformedProducts[] = $this->transformProduct($item);
            } catch (\Throwable $e) {
                $errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => (string) ($item['id'] ?? ''),
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
     * Extract pagination info from Magento GraphQL response
     *
     * @param array $magentoData The Magento GraphQL API response
     * @return array<string, int>|null Pagination info or null if not available
     */
    public function extractPaginationInfo(array $magentoData): ?array
    {
        if (!isset($magentoData['data']['products'])) {
            return null;
        }

        $products = $magentoData['data']['products'];
        $result = [];

        if (isset($products['total_count'])) {
            $result['total_count'] = (int) $products['total_count'];
        }

        if (isset($products['page_info']) && is_array($products['page_info'])) {
            $pageInfo = $products['page_info'];

            if (isset($pageInfo['current_page'])) {
                $result['current_page'] = (int) $pageInfo['current_page'];
            }

            if (isset($pageInfo['page_size'])) {
                $result['page_size'] = (int) $pageInfo['page_size'];
            }

            if (isset($pageInfo['total_pages'])) {
                $result['total_pages'] = (int) $pageInfo['total_pages'];
            }
        }

        return empty($result) ? null : $result;
    }

    /**
     * Transform a single Magento product to BradSearch format
     *
     * Performs transformation to unified format while preserving original Magento fields:
     * - Validates required fields (id, sku, name)
     * - Adds unified fields matching PrestaShop/Shopify adapters
     * - Passes all original Magento fields through unchanged
     */
    private function transformProduct(array $product): array
    {
        // Validate required fields
        $this->getRequiredField($product, 'id');
        $this->getRequiredField($product, 'sku');
        $this->getRequiredField($product, 'name');

        // Start with a copy of all original data (pass-through)
        $result = $product;

        // Cast id to string to match SDK expectations
        $result['id'] = (string) $product['id'];

        // Product URL
        $productUrl = $this->extractProductUrl($product);
        if ($productUrl !== null) {
            $result['productUrl'] = $productUrl;
        }

        // Description fields (strip HTML)
        $description = $this->extractDescription($product);
        if ($description !== null) {
            $result['description'] = $description;
        }

        $descriptionShort = $this->extractShortDescription($product);
        if ($descriptionShort !== null) {
            $result['descriptionShort'] = $descriptionShort;
        }

        // Brand from attributes
        $brand = $this->extractBrand($product);
        if ($brand !== null) {
            $result['brand'] = $brand;
        }

        // Price mapping (simplified - only final prices)
        $price = $this->extractPrice($product);
        $priceTaxExcluded = $this->extractPriceTaxExcluded($product);
        $result['price'] = $price;
        $result['priceTaxExcluded'] = $priceTaxExcluded;
        $result['basePrice'] = $price;
        $result['basePriceTaxExcluded'] = $priceTaxExcluded;

        // Categories - build hierarchical paths
        $result['categories'] = $this->buildHierarchicalCategories($product);
        $categoryDefault = $this->extractDefaultCategory($product);
        if ($categoryDefault !== null) {
            $result['categoryDefault'] = $categoryDefault;
        }

        // Stock status
        $result['inStock'] = $this->extractInStock($product);

        // Image URL from image_optimized
        $imageUrl = $this->extractImageUrl($product);
        if (!empty($imageUrl)) {
            $result['imageUrl'] = $imageUrl;
        }

        return $result;
    }

    /**
     * Extract product URL from full_url field
     */
    private function extractProductUrl(array $product): ?string
    {
        if (isset($product['full_url']) && is_string($product['full_url']) && $product['full_url'] !== '') {
            return $product['full_url'];
        }

        return null;
    }

    /**
     * Extract and clean description (strip HTML)
     */
    private function extractDescription(array $product): ?string
    {
        $html = AdapterUtils::getNestedValue($product, ['description', 'html']);
        if ($html !== null && is_string($html) && $html !== '') {
            return strip_tags($html);
        }

        return null;
    }

    /**
     * Extract and clean short description (strip HTML)
     */
    private function extractShortDescription(array $product): ?string
    {
        $html = AdapterUtils::getNestedValue($product, ['short_description', 'html']);
        if ($html !== null && is_string($html) && $html !== '') {
            return strip_tags($html);
        }

        return null;
    }

    /**
     * Extract brand from attributes array where code='manufacturer'
     */
    private function extractBrand(array $product): ?string
    {
        if (!isset($product['attributes']) || !is_array($product['attributes'])) {
            return null;
        }

        foreach ($product['attributes'] as $attr) {
            if (!is_array($attr)) {
                continue;
            }

            if (isset($attr['code']) && $attr['code'] === 'manufacturer') {
                $value = $attr['value'] ?? $attr['label'] ?? null;
                if ($value !== null && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Extract price (final_price with tax)
     */
    private function extractPrice(array $product): string
    {
        $value = AdapterUtils::getNestedValue($product, ['price_range', 'minimum_price', 'final_price', 'value']);
        if ($value !== null && (is_numeric($value) || is_string($value))) {
            return (string) $value;
        }

        return '0.00';
    }

    /**
     * Extract price tax excluded (final_price_excl_tax)
     */
    private function extractPriceTaxExcluded(array $product): string
    {
        $value = AdapterUtils::getNestedValue($product, ['price_range', 'minimum_price', 'final_price_excl_tax', 'value']);
        if ($value !== null && (is_numeric($value) || is_string($value))) {
            return (string) $value;
        }

        // Fallback to regular price if tax-excluded not available
        return $this->extractPrice($product);
    }

    /**
     * Build hierarchical category paths with " > " separator
     *
     * Transforms Magento category structure into hierarchical string paths
     * matching the format used by PrestaShop and Shopify adapters.
     */
    private function buildHierarchicalCategories(array $product): array
    {
        if (!isset($product['categories']) || !is_array($product['categories'])) {
            return [];
        }

        // Build ID -> name lookup from available categories
        $idToName = [];
        foreach ($product['categories'] as $cat) {
            if (is_array($cat) && isset($cat['id'], $cat['name'])) {
                $idToName[(string) $cat['id']] = $cat['name'];
            }
        }

        if (empty($idToName)) {
            return [];
        }

        // Build hierarchical paths
        $paths = [];
        foreach ($product['categories'] as $cat) {
            if (!is_array($cat) || !isset($cat['path'], $cat['name'])) {
                continue;
            }

            $pathIds = explode('/', $cat['path']);
            $pathNames = [];

            foreach ($pathIds as $id) {
                // Only include categories we have names for (skips root categories not in response)
                if (isset($idToName[$id])) {
                    $pathNames[] = $idToName[$id];
                }
            }

            if (!empty($pathNames)) {
                $paths[] = implode(' > ', $pathNames);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Extract default/primary category name
     */
    private function extractDefaultCategory(array $product): ?string
    {
        if (!isset($product['categories']) || !is_array($product['categories'])) {
            return null;
        }

        // Find the category with the lowest level (closest to root)
        $defaultCategory = null;
        $lowestLevel = PHP_INT_MAX;

        foreach ($product['categories'] as $cat) {
            if (!is_array($cat) || !isset($cat['name'], $cat['level'])) {
                continue;
            }

            $level = (int) $cat['level'];
            if ($level < $lowestLevel) {
                $lowestLevel = $level;
                $defaultCategory = $cat['name'];
            }
        }

        return $defaultCategory;
    }

    /**
     * Extract stock status
     */
    private function extractInStock(array $product): bool
    {
        // Check is_in_stock boolean field first
        if (isset($product['is_in_stock'])) {
            return (bool) $product['is_in_stock'];
        }

        // Fall back to stock_status enum
        if (isset($product['stock_status']) && is_string($product['stock_status'])) {
            return $product['stock_status'] === 'IN_STOCK';
        }

        return false;
    }

    /**
     * Extract image URL from image_optimized field
     * Returns both small and medium as the same URL since image_optimized is a single string
     */
    private function extractImageUrl(array $product): array
    {
        if (isset($product['image_optimized']) && is_string($product['image_optimized']) && $product['image_optimized'] !== '') {
            $url = $product['image_optimized'];
            return AdapterUtils::buildImageUrl($url, $url);
        }

        return [];
    }

    /**
     * Get required field with validation
     *
     * @throws ValidationException
     */
    private function getRequiredField(array $data, string $field): string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            throw new ValidationException("Required field '{$field}' is missing from Magento data");
        }

        return (string) $data[$field];
    }
}
