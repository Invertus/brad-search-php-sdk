<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;

/**
 * Transforms Magento GraphQL product data into V2 ValueObjects for bulk operations.
 *
 * Magento syncs one store view at a time, so a locale must be provided to suffix
 * locale-aware fields (e.g., name → name_lt-LT, feature_diameter → feature_diameter_lt-LT).
 *
 * Produces the unified cross-platform data format:
 * - Locale-suffixed text fields: `name_{locale}`, `brand_{locale}`, `description_{locale}`, etc.
 * - Flat `feature_{code}_{locale}` fields for text search (searchable attributes only)
 * - Nested `features` array with `{name, value}` pairs for filtering/aggregations (filterable-only attributes)
 * - Brand extraction from `code === 'manufacturer'`
 * - Product identifiers: `mpn`, `barcode`, `mpn_without_symbols` → top-level fields (no locale suffix)
 * - Name prefix: `beginning_of_product_nam` → `nameShort` for fuzzy matching (no locale suffix)
 * - SDK sends raw {name, value} pairs — Go handles numeric_value/unit enrichment
 */
class MagentoAdapterV2
{
    /**
     * @var array<int, array{type: string, product_index: int, product_id: string, message: string, exception: string}>
     */
    private array $errors = [];

    /**
     * @param string $locale Store view locale (e.g., "lt_LT", "lt-LT", "lt"). Normalized to BCP 47 format.
     */
    public function __construct(private readonly string $locale)
    {
    }

    /**
     * Transform Magento GraphQL product data to BulkOperationsRequest.
     *
     * @param array<string, mixed> $magentoData The Magento GraphQL API response
     * @return array{request: BulkOperationsRequest|null, products: array<int, Product>, errors: array<int, array{type: string, product_index: int, product_id: string, message: string, exception: string}>}
     */
    public function transform(array $magentoData): array
    {
        if (!isset($magentoData['data'])) {
            throw new ValidationException('Invalid Magento data: missing data field');
        }

        $responseKey = $this->resolveResponseKey($magentoData['data']);

        if ($responseKey === null) {
            throw new ValidationException('Invalid Magento data: missing products field');
        }

        if (!isset($magentoData['data'][$responseKey]['items'])) {
            return [
                'request' => null,
                'products' => [],
                'errors' => [],
            ];
        }

        if (!is_array($magentoData['data'][$responseKey]['items'])) {
            throw new ValidationException('Invalid Magento data: products items must be an array');
        }

        $this->errors = [];
        $products = [];

        foreach ($magentoData['data'][$responseKey]['items'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            try {
                $products[] = $this->transformProduct($item);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => (string) ($item['id'] ?? ''),
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
            }
        }

        $request = null;
        if (count($products) > 0) {
            $request = new BulkOperationsRequest([
                BulkOperation::indexProducts($products),
            ]);
        }

        return [
            'request' => $request,
            'products' => $products,
            'errors' => $this->errors,
        ];
    }

    /**
     * Transform a single Magento product to V2 Product ValueObject.
     *
     * @param array<string, mixed> $product The Magento product data
     * @return Product
     */
    public function transformProduct(array $product): Product
    {
        $id = $this->getRequiredField($product, 'id');
        $sku = $this->getRequiredField($product, 'sku');

        $pricing = $this->extractPricing($product);
        $imageUrl = $this->extractImageUrl($product);
        $inStock = $this->extractInStock($product);

        $additionalFields = [];
        $locale = $this->locale;

        // Name (locale-aware)
        if (isset($product['name']) && is_string($product['name']) && $product['name'] !== '') {
            $additionalFields["name_{$locale}"] = strip_tags($product['name']);
        }

        // Description (locale-aware)
        $description = $this->extractHtmlField($product, 'description');
        if ($description !== null) {
            $additionalFields["description_{$locale}"] = $description;
        }

        // Short description (locale-aware)
        $shortDescription = $this->extractHtmlField($product, 'short_description');
        if ($shortDescription !== null) {
            $additionalFields["descriptionShort_{$locale}"] = $shortDescription;
        }

        // Product URL (locale-aware)
        if (isset($product['full_url']) && is_string($product['full_url']) && $product['full_url'] !== '') {
            $additionalFields["productUrl_{$locale}"] = $product['full_url'];
        }

        // Categories (locale-aware)
        $categories = $this->buildHierarchicalCategories($product);
        if (!empty($categories)) {
            $additionalFields["categories_{$locale}"] = $categories;
        }

        $categoryDefault = $this->extractDefaultCategory($product);
        if ($categoryDefault !== null) {
            $additionalFields["categoryDefault_{$locale}"] = $categoryDefault;
        }

        // Popularity/sorting metrics (locale-agnostic)
        // Magento's sort_popularity_sales: 1 = most popular, 999 = least popular.
        // V2 "popularity" scoring uses field_value_factor (higher = better boost),
        // so we invert: 1000 - original, making higher values = more popular.
        if (isset($product['sort_popularity_sales'])) {
            $original = (int) $product['sort_popularity_sales'];
            $additionalFields['sort_popularity_sales'] = max(0, 1000 - $original);
        }

        // Extract hasImage and delivery_speed from sort_popularity composite string
        // Format: [stock(1)][sales_rank(3)][has_image(1)][delivery_delay(3)] e.g. "I042I003"
        if (isset($product['sort_popularity']) && is_string($product['sort_popularity']) && strlen($product['sort_popularity']) === 8) {
            $sortPopularity = $product['sort_popularity'];

            // Position 5: Has image (I=true, N=false) — used to penalize products without images
            $imageFlag = $sortPopularity[4];
            if ($imageFlag === 'I' || $imageFlag === 'N') {
                $additionalFields['hasImage'] = ($imageFlag === 'I');
            }

            // Positions 6-8: Delivery delay in days (000-999, NUL = no data)
            // Invert so higher = faster delivery = bigger boost (same pattern as sort_popularity_sales)
            $delayPart = substr($sortPopularity, 5, 3);
            if ($delayPart !== 'NUL' && ctype_digit($delayPart)) {
                $additionalFields['delivery_speed'] = max(0, 999 - (int) $delayPart);
            }
        }

        // Process attributes: flat feature_ fields + nested features array + brand
        $this->processAttributes($additionalFields, $product);

        return new Product(
            id: $id,
            sku: $sku,
            pricing: $pricing,
            imageUrl: $imageUrl,
            inStock: $inStock,
            additionalFields: $additionalFields
        );
    }

    /**
     * Get transformation errors from the last transform() call.
     *
     * @return array<int, array{type: string, product_index: int, product_id: string, message: string, exception: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Resolve the response key from Magento GraphQL data.
     *
     * @param array<string, mixed> $data
     * @return string|null
     */
    private function resolveResponseKey(array $data): ?string
    {
        if (isset($data['bradProducts'])) {
            return 'bradProducts';
        }

        if (isset($data['products'])) {
            return 'products';
        }

        return null;
    }

    /**
     * Process attributes into flat feature_ fields, nested features array, and brand.
     *
     * - code=manufacturer → extracted as `brand_{locale}`
     * - ALL non-special attributes → flat `feature_{code}_{locale}` field for text search (v1 searched all attributes)
     * - is_filterable=true → also added to nested `features` array for filtering/aggregations
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $product
     */
    private function processAttributes(array &$result, array $product): void
    {
        if (!isset($product['attributes']) || !is_array($product['attributes'])) {
            return;
        }

        $locale = $this->locale;
        $features = [];

        foreach ($product['attributes'] as $attr) {
            if (!is_array($attr) || !isset($attr['code'], $attr['value'])) {
                continue;
            }

            $code = (string) $attr['code'];
            $value = (string) $attr['value'];

            if ($code === '' || $value === '') {
                continue;
            }

            // Brand extraction from manufacturer (locale-aware)
            // Also falls through to create feature_manufacturer_{locale} for aggregations
            if ($code === 'manufacturer') {
                $result["brand_{$locale}"] = $value;
            }

            // Product identifiers — top-level fields, no locale suffix (searchable alongside sku)
            if ($code === 'mpn') {
                $result['mpn'] = $value;
                continue;
            }
            if ($code === 'barcode') {
                $result['barcode'] = $value;
                continue;
            }
            if ($code === 'mpn_without_symbols') {
                $result['mpn_without_symbols'] = $value;
                continue;
            }

            // Name components — top-level fields, no locale suffix
            if ($code === 'beginning_of_product_nam') {
                $result['nameShort'] = $value;
                continue;
            }

            $isFilterable = (bool) ($attr['is_filterable'] ?? false);

            // Flat field for text search with locale suffix (all attributes, matching v1 behavior)
            $result["feature_{$code}_{$locale}"] = $value;

            // Nested array for filtering/aggregations (filterable attributes)
            if ($isFilterable) {
                $features[] = [
                    'name' => $code,
                    'value' => $value,
                ];
            }
        }

        if (!empty($features)) {
            $result['features'] = $features;
        }
    }

    /**
     * Extract pricing from Magento price_range structure.
     *
     * @param array<string, mixed> $product
     * @return ProductPricing
     */
    private function extractPricing(array $product): ProductPricing
    {
        $price = (float) (AdapterUtils::getNestedValue(
            $product,
            ['price_range', 'minimum_price', 'final_price', 'value']
        ) ?? 0);

        $priceTaxExcluded = (float) (AdapterUtils::getNestedValue(
            $product,
            ['price_range', 'minimum_price', 'final_price_excl_tax', 'value']
        ) ?? $price);

        return new ProductPricing(
            price: $price,
            basePrice: $price,
            priceTaxExcluded: $priceTaxExcluded,
            basePriceTaxExcluded: $priceTaxExcluded
        );
    }

    /**
     * Extract image URL from Magento image_optimized or image field.
     *
     * @param array<string, mixed> $product
     * @return ImageUrl
     */
    private function extractImageUrl(array $product): ImageUrl
    {
        $url = null;

        // Try image_optimized first (single string URL)
        if (isset($product['image_optimized']) && is_string($product['image_optimized']) && $product['image_optimized'] !== '') {
            $url = $product['image_optimized'];
        }

        // Fallback to nested image.url
        if ($url === null) {
            $url = AdapterUtils::extractNestedImageUrl($product, 'image');
        }

        // Fallback to small_image.url
        if ($url === null) {
            $url = AdapterUtils::extractNestedImageUrl($product, 'small_image');
        }

        if ($url === null) {
            throw new ValidationException("Product image URL is required");
        }

        return new ImageUrl(
            small: $url,
            medium: $url
        );
    }

    /**
     * Extract stock status.
     *
     * @param array<string, mixed> $product
     * @return bool
     */
    private function extractInStock(array $product): bool
    {
        if (isset($product['is_in_stock'])) {
            return (bool) $product['is_in_stock'];
        }

        if (isset($product['stock_status']) && is_string($product['stock_status'])) {
            return $product['stock_status'] === 'IN_STOCK';
        }

        return false;
    }

    /**
     * Extract and clean an HTML field (description, short_description).
     *
     * @param array<string, mixed> $product
     * @param string $field
     * @return string|null
     */
    private function extractHtmlField(array $product, string $field): ?string
    {
        $html = AdapterUtils::getNestedValue($product, [$field, 'html']);
        if ($html !== null && is_string($html) && $html !== '') {
            return strip_tags($html);
        }

        return null;
    }

    /**
     * Build hierarchical category paths.
     *
     * @param array<string, mixed> $product
     * @return array<int, string>
     */
    private function buildHierarchicalCategories(array $product): array
    {
        if (!isset($product['categories']) || !is_array($product['categories'])) {
            return [];
        }

        $idToName = [];
        foreach ($product['categories'] as $cat) {
            if (is_array($cat) && isset($cat['id'], $cat['name'])) {
                $idToName[(string) $cat['id']] = $cat['name'];
            }
        }

        if (empty($idToName)) {
            return [];
        }

        $paths = [];
        foreach ($product['categories'] as $cat) {
            if (!is_array($cat) || !isset($cat['path'], $cat['name'])) {
                continue;
            }

            $pathIds = explode('/', $cat['path']);
            $pathNames = [];

            foreach ($pathIds as $id) {
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
     * Extract default/primary category (deepest level).
     *
     * @param array<string, mixed> $product
     * @return string|null
     */
    private function extractDefaultCategory(array $product): ?string
    {
        if (!isset($product['categories']) || !is_array($product['categories'])) {
            return null;
        }

        $defaultCategory = null;
        $highestLevel = -1;

        foreach ($product['categories'] as $cat) {
            if (!is_array($cat) || !isset($cat['name'], $cat['level'])) {
                continue;
            }

            $level = (int) $cat['level'];
            if ($level > $highestLevel) {
                $highestLevel = $level;
                $defaultCategory = $cat['name'];
            }
        }

        return $defaultCategory;
    }

    /**
     * Get required field with validation.
     *
     * @param array<string, mixed> $data
     * @param string $field
     * @return string
     */
    private function getRequiredField(array $data, string $field): string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            throw new ValidationException("Required field '{$field}' is missing from Magento data");
        }

        return (string) $data[$field];
    }
}
