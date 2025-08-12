<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Validators\DataValidator;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\Enums\FieldType;

class SynchronizationApiSdk
{
    private readonly HttpClient $httpClient;
    private readonly DataValidator $validator;

    /**
     * @param array<string, FieldConfig> $fieldConfiguration
     */
    public function __construct(
        SyncConfig $config,
        public readonly array $fieldConfiguration
    ) {
        $this->httpClient = new HttpClient($config);
        $this->validator = new DataValidator($fieldConfiguration);
    }

    /**
     * Delete an index
     */
    public function deleteIndex(string $index): void
    {
        $this->validateIndexName($index);
        $this->httpClient->delete("api/v1/sync/{$index}");
    }

    /**
     * Create an index with field configuration and optional alias
     *
     * @throws ValidationException
     */
    public function createIndex(string $index, ?string $alias = null): void
    {
        $this->validateIndexName($index);

        $fields = [];
        foreach ($this->fieldConfiguration as $fieldName => $fieldConfig) {
            $fields[$fieldName] = $fieldConfig->toArray();
        }

        $data = [
            'index_name' => $index,
            'fields' => $fields,
        ];

        // Add alias if provided
        if ($alias !== null) {
            $this->validateIndexAlias($alias);
            $data['aliases'] = [
                $alias => (object)[]
            ];
        }

        $this->httpClient->put('api/v1/sync/', $data);
    }

    /**
     * Copy source index to target index
     * @throws ValidationException
     */
    public function copyIndex(string $sourceIndex, string $targetIndex, string $alias): void
    {
        $this->validateIndexName($sourceIndex);
        $this->validateIndexName($targetIndex);

        // First, create the target index with proper field configuration
        $this->createIndex($targetIndex, $alias);

        // Then perform the reindex operation using the Go service format
        $data = [
            'source_index' => $sourceIndex,
            'target_index' => $targetIndex
        ];

        $this->httpClient->post('api/v1/sync/reindex', $data);
    }

    /**
     * Synchronize a single product
     */
    public function sync(string $index, array $productData): void
    {
        $this->syncBulk($index, [$productData]);
    }

    /**
     * Synchronize multiple products in bulk
     *
     * @param array<array> $productsData
     */
    public function syncBulk(string $index, array $productsData, int $batchSize = 100): void
    {
        $this->validateIndexName($index);

        if (empty($productsData)) {
            return;
        }

        // Validate all products before sending
        $this->validator->validateProducts($productsData);

        // Process in batches
        $batches = array_chunk($productsData, $batchSize);

        foreach ($batches as $batch) {
            $this->sendBatch($index, $batch);
        }
    }

    /**
     * Send a batch of products to the API
     *
     * @param array<array> $products
     */
    private function sendBatch(string $index, array $products): void
    {
        // Filter products to only include fields that are defined in the configuration
        $filteredProducts = array_map(
            fn(array $product) => $this->filterProductFields($product),
            $products
        );

        $data = [
            'index_name' => $index,
            'products' => $filteredProducts,
            'count' => count($filteredProducts),
            'subfields' => [
                'sku' => [
                    'split_by' => ['/', '.'],
                    'max_count' => 7,
                    'in_variants' => true,
                ],
            ],
            'embeddablefields' => $this->buildEmbeddableFields(),
        ];

        $this->httpClient->post('api/v1/sync/', $data);
    }

    /**
     * Update multiple products in an existing index
     *
     * @param array<array> $productsData
     * @throws ValidationException
     */
    public function updateProductsBulk(string $index, array $productsData): void
    {
        $this->validateIndexName($index);

        if (empty($productsData)) {
            return;
        }

        // Validate all products before sending
        $this->validator->validateProducts($productsData);

        $this->sendUpdateBatch($index, $productsData);
    }

    /**
     * Send a batch of product updates to the API
     *
     * @param array<array> $products
     */
    private function sendUpdateBatch(string $index, array $products): void
    {
        // Filter products to only include fields that are defined in the configuration
        $filteredProducts = array_map(
            fn(array $product) => $this->filterProductFields($product),
            $products
        );

        $data = [
            'index_name' => $index,
            'products' => $filteredProducts,
        ];

        $this->httpClient->post('api/v1/sync/update-products', $data);
    }

    /**
     * Delete multiple products in an existing index
     *
     * @param array<array> $productsIds
     * @throws ValidationException
     */
    public function deleteProductsBulk(string $index, array $productsIds): void
    {
        $this->validateIndexName($index);

        if (empty($productsIds)) {
            return;
        }

        $this->sendDeleteBatch($index, $productsIds);
    }

    /**
     * Send a batch of product deletes to the API
     *
     * @param array<array> $productsIds
     */
    private function sendDeleteBatch(string $index, array $productsIds): void
    {
        $data = [
            'index_name' => $index,
            'product_ids' => $productsIds,
        ];

        $this->httpClient->post('api/v1/sync/delete-products', $data);
    }

    /**
     * Filter product data to only include configured fields
     */
    private function filterProductFields(array $product): array
    {
        $filtered = [];

        foreach ($this->fieldConfiguration as $fieldName => $fieldConfig) {
            if (array_key_exists($fieldName, $product)) {
                $filtered[$fieldName] = $product[$fieldName];
            }
        }

        return $filtered;
    }

    /**
     * Validate index name
     *
     * @throws ValidationException
     */
    private function validateIndexName(string $index): void
    {
        $this->validateIndexOrAliasName($index);
    }

    /**
     * Validate alias name
     *
     * @throws ValidationException
     */
    private function validateIndexAlias(string $alias): void
    {
        $this->validateIndexOrAliasName($alias, 'alias');
    }

    /**
     * Validate index or alias name
     *
     * @param string $name The name to validate
     * @param string $type Either 'index' or 'alias'
     * @throws ValidationException if validation fails
     */
    private function validateIndexOrAliasName(string $name, string $type = 'index'): void
    {
        // Check if empty
        if (empty(trim($name))) {
            throw new ValidationException("{$type} name cannot be empty");
        }

        // Check length (255 bytes max)
        if (strlen($name) > 255) {
            throw new ValidationException("{$type} name cannot be longer than 255 characters");
        }

        // Check if starts with invalid characters
        if (preg_match('/^[-._+]/', $name)) {
            throw new ValidationException("{$type} name cannot start with hyphen (-), dot (.), underscore (_), or plus (+)");
        }

        // Check if contains only valid characters (different patterns for index vs alias)
        $pattern = $type === 'alias' ? '/^[a-z0-9_-]+$/' : '/^[a-z0-9._-]+$/';
        $allowedChars = $type === 'alias'
            ? 'lowercase letters, numbers, hyphens, and underscores'
            : 'lowercase letters, numbers, dots, hyphens, and underscores';

        if (!preg_match($pattern, $name)) {
            throw new ValidationException("{$type} name can only contain {$allowedChars}");
        }

        // Check for reserved names
        $reservedNames = ['.', '..'];
        if (in_array($name, $reservedNames)) {
            throw new ValidationException("{$type} name cannot be a reserved name (. or ..)");
        }
    }

    /**
     * Get the configured fields for this SDK instance
     *
     * @return array<string, FieldConfig>
     */
    public function getFieldConfiguration(): array
    {
        return $this->fieldConfiguration;
    }

    /**
     * Validate product data against field configuration without syncing
     */
    public function validateProduct(array $productData): void
    {
        $this->validator->validateProduct($productData);
    }

    /**
     * Validate multiple products without syncing
     *
     * @param array<array> $productsData
     */
    public function validateProducts(array $productsData): void
    {
        $this->validator->validateProducts($productsData);
    }

    /**
     * Build embeddable fields configuration with localized fields
     */
    private function buildEmbeddableFields(): array
    {
        // TODO: hardcoded for now
        $locales = ['en-US', 'lt-LT'];

        $baseFields = [
            'name' => FieldType::TEXT_KEYWORD,
            'brand' => FieldType::TEXT_KEYWORD,
            'categoryDefault' => FieldType::TEXT_KEYWORD,
            'features' => FieldType::NAME_VALUE_LIST,
        ];

        $embeddableFields = [];

        foreach ($baseFields as $fieldName => $fieldType) {
            foreach ($locales as $locale) {
                if ($locale === 'en-US') {
                    $embeddableFields[$fieldName] = $fieldType;
                } else {
                    $embeddableFields["{$fieldName}_{$locale}"] = $fieldType;
                }
            }
        }

        return $embeddableFields;
    }
}
