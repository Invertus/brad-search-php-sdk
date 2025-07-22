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
     * Create an index with field configuration
     */
    public function createIndex(string $index): void
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

        $this->httpClient->put('api/v1/sync/', $data);
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
     */
    private function validateIndexName(string $index): void
    {
        if (empty(trim($index))) {
            throw new ValidationException('Index name cannot be empty');
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
