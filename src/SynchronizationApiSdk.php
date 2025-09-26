<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Models\BulkOperation;
use BradSearch\SyncSdk\Models\BulkOperationResult;
use BradSearch\SyncSdk\Validators\DataValidator;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\Enums\FieldType;

class SynchronizationApiSdk
{
    private readonly HttpClient $httpClient;
    private readonly DataValidator $validator;

    private readonly string $apiStartUrl;

    /**
     * @param array<string, FieldConfig> $fieldConfiguration
     */
    public function __construct(
        SyncConfig $config,
        public readonly array $fieldConfiguration,
        private readonly string $endpoint = ''
    ) {
        $this->httpClient = new HttpClient($config);
        $this->apiStartUrl = 'api/v1/';
        $this->validator = new DataValidator($fieldConfiguration);
    }

    /**
     * Delete an index
     *
     * @param string $index
     * @throws ValidationException
     */
    public function deleteIndex(string $index): void
    {
        $this->validator->validateIndex($index);
        $this->httpClient->delete("{$this->apiStartUrl}sync/{$index}");
    }

    /**
     * Create an index with field configuration
     *
     * @param string $index
     * @throws ValidationException
     */
    public function createIndex(string $index): void
    {
        $this->validator->validateIndex($index);

        $fields = [];
        foreach ($this->fieldConfiguration as $fieldName => $fieldConfig) {
            $fields[$fieldName] = $fieldConfig->toArray();
        }

        $data = [
            'index_name' => $index,
            'fields' => $fields,
        ];


        $url = $this->apiStartUrl . (!empty($this->endpoint) ? $this->endpoint . '/' : '') . 'sync/';

        $this->httpClient->put($url, $data);
    }

    /**
     * Copy source index to target index
     *
     * @param string $sourceIndex
     * @param string $targetIndex
     * @throws ValidationException
     */
    public function copyIndex(string $sourceIndex, string $targetIndex): void
    {
        $this->validator->validateIndex($sourceIndex);

        // First, create the target index with proper field configuration
        $this->createIndex($targetIndex);

        // Then perform the reindex operation using the Go service format
        $data = [
            'source_index' => $sourceIndex,
            'target_index' => $targetIndex
        ];

        $this->httpClient->post("{$this->apiStartUrl}sync/reindex", $data);
    }

    /**
     * Synchronize a single product
     *
     * @param string $index
     * @param array $productData
     * @throws ValidationException
     */
    public function sync(string $index, array $productData): void
    {
        $this->validator->validateIndex($index);

        $this->syncBulk($index, [$productData]);
    }

    /**
     * Synchronize multiple products in bulk
     *
     * @param array<array> $productsData
     */
    public function syncBulk(string $index, array $productsData, int $batchSize = 100): void
    {
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
     * Delete multiple products in an existing index
     *
     * @param array<array> $productsIds
     * @throws ValidationException
     */
    public function deleteProductsBulk(string $index, array $productsIds): void
    {
        $this->validator->validateIndex($index);

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
     * Update specific fields of multiple products in bulk
     *
     * @param string $index
     * @param array $updates Array of updates in format: [['id' => 'product_id', 'fields' => ['field' => 'value']]]
     * @return array Response from the API
     * @throws ValidationException
     */
    public function updateProductsBulk(string $index, array $updates): array
    {
        $this->validator->validateIndex($index);

        if (empty($updates)) {
            return [
                'status' => 'success',
                'message' => 'No updates provided',
                'updated_count' => 0,
            ];
        }

        $data = [
            'index_name' => $index,
            'updates' => $updates,
        ];

        return $this->httpClient->patch('api/v1/sync/update-products', $data);
    }

    /**
     * Execute multiple bulk operations in a single API call
     *
     * @param BulkOperation[] $operations Array of bulk operations to execute
     * @return BulkOperationResult Result of all operations
     * @throws ValidationException
     */
    public function bulkOperations(array $operations): BulkOperationResult
    {
        if (empty($operations)) {
            throw new ValidationException('No operations provided');
        }

        // Validate all operations
        foreach ($operations as $operation) {
            if (!$operation instanceof BulkOperation) {
                throw new ValidationException('All operations must be instances of BulkOperation');
            }
            $this->validateBulkOperation($operation);
        }

        $data = [
            'operations' => array_map(fn(BulkOperation $op) => $op->toArray(), $operations)
        ];

        $response = $this->httpClient->post("{$this->apiStartUrl}sync/bulk-operations", $data);

        return BulkOperationResult::fromApiResponse($response);
    }

    /**
     * Validate a single bulk operation
     *
     * @throws ValidationException
     */
    protected function validateBulkOperation(BulkOperation $operation): void
    {
        $payload = $operation->payload;

        switch ($operation->type) {
            case \BradSearch\SyncSdk\Enums\BulkOperationType::INDEX_PRODUCTS:
                if (!isset($payload['index_name']) || !isset($payload['products'])) {
                    throw new ValidationException('INDEX_PRODUCTS operation requires index_name and products');
                }
                $this->validator->validateIndex($payload['index_name']);
                if (!empty($payload['products'])) {
                    $this->validator->validateProducts($payload['products']);
                }
                break;

            case \BradSearch\SyncSdk\Enums\BulkOperationType::UPDATE_PRODUCTS:
                if (!isset($payload['index_name']) || !isset($payload['updates'])) {
                    throw new ValidationException('UPDATE_PRODUCTS operation requires index_name and updates');
                }
                $this->validator->validateIndex($payload['index_name']);
                break;

            case \BradSearch\SyncSdk\Enums\BulkOperationType::DELETE_PRODUCTS:
                if (!isset($payload['index_name']) || !isset($payload['product_ids'])) {
                    throw new ValidationException('DELETE_PRODUCTS operation requires index_name and product_ids');
                }
                $this->validator->validateIndex($payload['index_name']);
                if (empty($payload['product_ids']) || !is_array($payload['product_ids'])) {
                    throw new ValidationException('product_ids must be a non-empty array');
                }
                break;
            default:
                throw new ValidationException('Unsupported operation type: ' . $operation->type->value);
        }
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
