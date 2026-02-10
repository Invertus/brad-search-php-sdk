<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;

/**
 * Transforms PrestaShop product data into V2 ValueObjects for bulk operations.
 */
class PrestaShopAdapterV2
{
    /**
     * @var array<int, array{type: string, product_index: int, product_id: string, message: string, exception: string}>
     */
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * Transform PrestaShop product data to BulkOperationsRequest.
     *
     * @param array<string, mixed> $prestaShopData The PrestaShop API response
     * @return array{request: BulkOperationsRequest|null, products: array<int, Product>, errors: array<int, array{type: string, product_index: int, product_id: string, message: string, exception: string}>}
     */
    public function transform(array $prestaShopData): array
    {
        if (!isset($prestaShopData['products']) || !is_array($prestaShopData['products'])) {
            throw new ValidationException('Invalid PrestaShop data: missing products array');
        }

        $this->errors = [];
        $products = [];

        foreach ($prestaShopData['products'] as $index => $product) {
            if (!is_array($product)) {
                continue;
            }
            try {
                $products[] = $this->transformProduct($product);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => (string) ($product['remoteId'] ?? ''),
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
     * Transform a single PrestaShop product to V2 Product ValueObject.
     *
     * @param array<string, mixed> $product The PrestaShop product data
     * @return Product
     */
    public function transformProduct(array $product): Product
    {
        $id = $this->getRequiredField($product, 'remoteId');
        $sku = $this->getRequiredField($product, 'sku');

        $pricing = new ProductPricing(
            $this->extractPrice($product, 'price'),
            $this->extractPrice($product, 'basePrice'),
            $this->extractPrice($product, 'priceTaxExcluded'),
            $this->extractPrice($product, 'basePriceTaxExcluded')
        );

        $imageUrl = $this->transformImageUrl($product['imageUrl'] ?? []);

        // Extract optional boolean fields
        $inStock = $this->validateBooleanField($product['inStock'] ?? null);
        $isNew = $this->validateBooleanField($product['isNew'] ?? null);

        $additionalFields = [];

        // Add optional product identifiers
        if (isset($product['ean13']) && $product['ean13'] !== null && $product['ean13'] !== '') {
            $additionalFields['ean13'] = (string) $product['ean13'];
        }
        if (isset($product['mpn']) && $product['mpn'] !== null && $product['mpn'] !== '') {
            $additionalFields['mpn'] = (string) $product['mpn'];
        }

        // Handle localized product name
        $this->addLocalizedField($additionalFields, 'name', $product['localizedNames'] ?? []);

        // Handle descriptions
        if (isset($product['description']) && is_array($product['description'])) {
            $this->addLocalizedField($additionalFields, 'description', $product['description']);
        }
        if (isset($product['descriptionShort']) && is_array($product['descriptionShort'])) {
            $this->addLocalizedField($additionalFields, 'descriptionShort', $product['descriptionShort']);
        }

        // Handle brand
        if (
            isset($product['brand']) && is_array($product['brand']) &&
            isset($product['brand']['localizedNames']) && is_array($product['brand']['localizedNames'])
        ) {
            $this->addLocalizedField($additionalFields, 'brand', $product['brand']['localizedNames']);
        }

        // Handle categories
        $this->extractCategories($additionalFields, $product);
        $this->extractCategoryDefault($additionalFields, $product);

        // Handle product URLs
        if (isset($product['productUrl']) && is_array($product['productUrl'])) {
            $this->transformProductUrls($additionalFields, $product['productUrl']);
        }

        // Handle features
        $this->transformFeatures($additionalFields, (array) ($product['features'] ?? []));

        // Handle tags
        $this->transformTags($additionalFields, $product['tags'] ?? []);

        // Transform variants by locale
        $variantsByLocale = $this->transformVariantsByLocale($product['variants'] ?? []);
        foreach ($variantsByLocale as $key => $variants) {
            $additionalFields[$key] = $variants;
        }

        return new Product(
            id: $id,
            sku: $sku,
            pricing: $pricing,
            imageUrl: $imageUrl,
            inStock: $inStock,
            isNew: $isNew,
            additionalFields: $additionalFields
        );
    }

    /**
     * Transform a single variant for a specific locale.
     *
     * @param array<string, mixed> $variant The PrestaShop variant data
     * @param string $locale The locale to transform for
     * @return ProductVariant
     */
    public function transformVariant(array $variant, string $locale): ProductVariant
    {
        $id = (string) ($variant['remoteId'] ?? '');
        if ($id === '') {
            throw new ValidationException("Variant 'remoteId' is required");
        }

        $sku = (string) ($variant['sku'] ?? '');
        if ($sku === '') {
            throw new ValidationException("Variant 'sku' is required");
        }

        $pricing = new ProductPricing(
            $this->extractPrice($variant, 'price'),
            $this->extractPrice($variant, 'basePrice'),
            $this->extractPrice($variant, 'priceTaxExcluded'),
            $this->extractPrice($variant, 'basePriceTaxExcluded')
        );

        $productUrl = $this->getLocaleSpecificUrl($variant['productUrl']['localizedValues'] ?? [], $locale);
        if ($productUrl === '') {
            throw new ValidationException("Variant 'productUrl' is required for locale '{$locale}'");
        }

        $imageUrl = $this->transformImageUrl($variant['imageUrl'] ?? []);

        $attrs = $this->transformVariantAttributesForLocale($variant['attributes'] ?? [], $locale);

        return new ProductVariant(
            id: $id,
            sku: $sku,
            pricing: $pricing,
            productUrl: $productUrl,
            imageUrl: $imageUrl,
            attrs: $attrs
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
     * Transform variants with all locales embedded in attrs.
     *
     * @param mixed $variants
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function transformVariantsByLocale(mixed $variants): array
    {
        if (!is_array($variants)) {
            return [];
        }

        $transformedVariants = [];

        foreach ($variants as $variant) {
            if (!is_array($variant) || !isset($variant['remoteId']) || $variant['remoteId'] === null) {
                continue;
            }

            // Get product URL from any available locale
            $productUrl = $this->extractFirstLocaleValue($variant['productUrl']['localizedValues'] ?? []);
            if ($productUrl === '') {
                continue;
            }

            $transformedVariant = [
                'id' => (string) $variant['remoteId'],
                'sku' => $variant['sku'] ?? '',
            ];

            // Add variant-level prices if available
            if (isset($variant['price'])) {
                $transformedVariant['price'] = $this->extractPrice($variant, 'price');
            }
            if (isset($variant['basePrice'])) {
                $transformedVariant['basePrice'] = $this->extractPrice($variant, 'basePrice');
            }
            if (isset($variant['priceTaxExcluded'])) {
                $transformedVariant['priceTaxExcluded'] = $this->extractPrice($variant, 'priceTaxExcluded');
            }
            if (isset($variant['basePriceTaxExcluded'])) {
                $transformedVariant['basePriceTaxExcluded'] = $this->extractPrice($variant, 'basePriceTaxExcluded');
            }

            $transformedVariant['productUrl'] = $productUrl;

            // Add variant-level imageUrl if available
            if (isset($variant['imageUrl']) && is_array($variant['imageUrl'])) {
                $transformedVariant['imageUrl'] = $this->transformImageUrlToArray($variant['imageUrl']);
            }

            // Transform attrs with numeric indices and locale-keyed values
            $transformedVariant['attrs'] = $this->transformVariantAttrsWithLocales($variant['attributes'] ?? []);

            $transformedVariants[] = $transformedVariant;
        }

        if (empty($transformedVariants)) {
            return [];
        }

        return ['variants' => $transformedVariants];
    }

    /**
     * Transform variant attributes using remoteId as key and locale-keyed values.
     *
     * Output format: {"123": {"lt-LT": "8"}, "456": {"lt-LT": "Juoda"}}
     * Where 123 and 456 are attribute remoteIds.
     *
     * @param array<int|string, mixed> $attributes
     * @return array<string, array<string, string>>
     */
    private function transformVariantAttrsWithLocales(array $attributes): array
    {
        $attrs = [];

        foreach ($attributes as $attributeData) {
            if (
                !is_array($attributeData) ||
                !isset($attributeData['remoteId']) ||
                !isset($attributeData['localizedValues']) ||
                !is_array($attributeData['localizedValues'])
            ) {
                continue;
            }

            $remoteId = (string) $attributeData['remoteId'];
            if ($remoteId === '') {
                continue;
            }

            $localeValues = [];
            foreach ($attributeData['localizedValues'] as $locale => $value) {
                if (
                    !is_string($locale) || $locale === '' ||
                    $value === null || $value === ''
                ) {
                    continue;
                }
                $localeValues[$locale] = (string) $value;
            }

            if (!empty($localeValues)) {
                $attrs[$remoteId] = $localeValues;
            }
        }

        return $attrs;
    }

    /**
     * Get URL for a specific locale.
     *
     * @param array<string, string> $localizedValues
     * @param string $locale
     * @return string
     */
    private function getLocaleSpecificUrl(array $localizedValues, string $locale): string
    {
        return $localizedValues[$locale] ?? $this->extractFirstLocaleValue($localizedValues);
    }

    /**
     * Extract first available locale value.
     *
     * @param array<string, string> $localizedValues
     * @return string
     */
    private function extractFirstLocaleValue(array $localizedValues): string
    {
        return array_values($localizedValues)[0] ?? '';
    }

    /**
     * Transform variant attributes for a specific locale only.
     *
     * @param array<string, mixed> $attributes
     * @param string $locale
     * @return array<string, string>
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

            $transformedAttributes[strtolower($attributeName)] = $attributeData['localizedValues'][$locale];
        }

        return $transformedAttributes;
    }

    /**
     * Transform image URL structure to ImageUrl ValueObject.
     *
     * @param mixed $imageUrl
     * @return ImageUrl
     */
    private function transformImageUrl(mixed $imageUrl): ImageUrl
    {
        if (!is_array($imageUrl)) {
            throw new ValidationException('ImageUrl is required and must be an array');
        }

        $small = $imageUrl['small'] ?? null;
        $medium = $imageUrl['medium'] ?? null;

        if (!is_string($small) || trim($small) === '') {
            throw new ValidationException("ImageUrl 'small' is required");
        }

        if (!is_string($medium) || trim($medium) === '') {
            throw new ValidationException("ImageUrl 'medium' is required");
        }

        $large = isset($imageUrl['large']) && is_string($imageUrl['large']) && trim($imageUrl['large']) !== ''
            ? $imageUrl['large']
            : null;

        $thumbnail = isset($imageUrl['thumbnail']) && is_string($imageUrl['thumbnail']) && trim($imageUrl['thumbnail']) !== ''
            ? $imageUrl['thumbnail']
            : null;

        return new ImageUrl(
            small: $small,
            medium: $medium,
            large: $large,
            thumbnail: $thumbnail
        );
    }

    /**
     * Transform image URL structure to array format (for variant embedding).
     *
     * @param array<string, string> $imageUrl
     * @return array<string, string>
     */
    private function transformImageUrlToArray(array $imageUrl): array
    {
        $result = [];

        if (isset($imageUrl['small']) && $imageUrl['small'] !== null && $imageUrl['small'] !== '') {
            $result['small'] = $imageUrl['small'];
        }
        if (isset($imageUrl['medium']) && $imageUrl['medium'] !== null && $imageUrl['medium'] !== '') {
            $result['medium'] = $imageUrl['medium'];
        }

        return $result;
    }

    /**
     * Transform product URLs to create localized fields.
     *
     * @param array<string, mixed> $result
     * @param array<string, string> $productUrls
     */
    private function transformProductUrls(array &$result, array $productUrls): void
    {
        foreach ($productUrls as $locale => $url) {
            if (!is_string($locale) || $locale === '' || $url === null || $url === '') {
                continue;
            }

            $result["productUrl_{$locale}"] = $url;
        }
    }

    /**
     * Extract categories from all levels and flatten them.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $product
     */
    private function extractCategories(array &$result, array $product): void
    {
        $fieldName = 'categories';

        if (!isset($product[$fieldName]) || !is_array($product[$fieldName])) {
            return;
        }

        foreach ($product[$fieldName] as $level => $levelCategories) {
            if (!is_array($levelCategories)) {
                continue;
            }

            foreach ($levelCategories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $this->extractCategory($category, $fieldName, $result);
            }
        }
    }

    /**
     * Extract a single category and add to result.
     *
     * @param array<string, mixed> $category
     * @param string $fieldName
     * @param array<string, mixed> $result
     */
    private function extractCategory(array $category, string $fieldName, array &$result): void
    {
        if (
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

            $key = "{$fieldName}_{$locale}";

            switch ($fieldName) {
                case 'categories':
                    if (!isset($result[$key])) {
                        $result[$key] = [];
                    }
                    $result[$key][] = $path;
                    break;
                case 'categoryDefault':
                    $result[$key] = $path;
                    break;
            }
        }
    }

    /**
     * Extract default category.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $product
     */
    private function extractCategoryDefault(array &$result, array $product): void
    {
        $categoryFieldName = 'categoryDefault';

        if (!isset($product[$categoryFieldName]) || !is_array($product[$categoryFieldName])) {
            return;
        }

        $this->extractCategory($product[$categoryFieldName], $categoryFieldName, $result);
    }

    /**
     * Transform features to flat locale-specific fields.
     *
     * Input format (requires remoteId from PrestaShop plugin):
     * [
     *     'remoteId' => '5',
     *     'localizedValues' => [
     *         'en-US' => 'Cotton',
     *         'lt-LT' => 'Medvilnė',
     *     ],
     * ]
     *
     * Output format:
     * $result['feature_5_en-US'] = 'Cotton';
     * $result['feature_5_lt-LT'] = 'Medvilnė';
     *
     * @param array<string, mixed> $result
     * @param array<int, mixed> $features
     */
    private function transformFeatures(array &$result, array $features): void
    {
        foreach ($features as $feature) {
            if (!is_array($feature) || !isset($feature['remoteId']) || !isset($feature['localizedValues'])) {
                continue;
            }

            if (!is_array($feature['localizedValues'])) {
                continue;
            }

            $featureId = (string) $feature['remoteId'];

            foreach ($feature['localizedValues'] as $locale => $value) {
                if ($locale === null || $value === null || $value === '') {
                    continue;
                }

                $fieldName = "feature_{$featureId}_{$locale}";
                $result[$fieldName] = $value;
            }
        }
    }

    /**
     * Transform tags to create localized fields.
     *
     * @param array<string, mixed> $result
     * @param mixed $tags
     */
    private function transformTags(array &$result, mixed $tags): void
    {
        if (!is_array($tags) || empty($tags)) {
            return;
        }

        foreach ($tags as $locale => $tagList) {
            if (
                !is_string($locale) || $locale === '' ||
                !is_array($tagList) || empty($tagList)
            ) {
                continue;
            }

            $filteredTags = array_values(array_filter($tagList, fn($tag) => is_string($tag) && $tag !== ''));

            if (empty($filteredTags)) {
                continue;
            }

            $result["tags_{$locale}"] = $filteredTags;
        }
    }

    /**
     * Add localized field with support for multiple locales.
     *
     * @param array<string, mixed> $result
     * @param string $fieldName
     * @param array<string, string> $localizedValues
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

            $cleanValue = strip_tags((string) $value);

            $result["{$fieldName}_{$locale}"] = $cleanValue;
        }
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
        if (!isset($data[$field]) || $data[$field] === null) {
            throw new ValidationException("Required field '{$field}' is missing from PrestaShop data");
        }

        return (string) $data[$field];
    }

    /**
     * Extract price as float from product data.
     *
     * @param array<string, mixed> $data
     * @param string $field
     * @return float
     */
    private function extractPrice(array $data, string $field): float
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Required field '{$field}' is missing from PrestaShop data");
        }

        return (float) $data[$field];
    }

    /**
     * Validate and convert a field value to a proper boolean or null.
     *
     * @param mixed $value The value to validate
     * @return bool|null Returns true/false for valid boolean values, null for invalid/missing values
     */
    private function validateBooleanField(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
