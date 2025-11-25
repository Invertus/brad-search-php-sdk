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
     * Performs minimal transformation:
     * - Validates required fields (id, sku, name)
     * - Casts id to string
     * - Transforms image fields to SDK-compatible format
     * - Passes all other fields through unchanged
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

        // Transform image fields to SDK-compatible format (small/medium keys)
        // Magento: small_image.url -> small, image.url -> medium
        $smallUrl = AdapterUtils::extractNestedImageUrl($product, 'small_image')
            ?? AdapterUtils::extractNestedImageUrl($product, 'thumbnail');
        $mediumUrl = AdapterUtils::extractNestedImageUrl($product, 'image');

        $imageUrl = AdapterUtils::buildImageUrl($smallUrl, $mediumUrl);
        if (!empty($imageUrl)) {
            $result['imageUrl'] = $imageUrl;
        }

        return $result;
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
