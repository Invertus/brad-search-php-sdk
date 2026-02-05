<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\SyncV2Sdk;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexCreationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexInfoResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\QueryConfigurationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\VersionActivateResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use PHPUnit\Framework\TestCase;

/**
 * Full end-to-end workflow simulation test for Darbo Drabuziai client.
 *
 * This test simulates the complete SDK flow in the order defined by OpenAPI documentation:
 * 1. Create index v1 with field definitions
 * 2. Set search configuration
 * 3. Sync initial product data
 * 4. Verify index info
 * 5. Create index v2 (for zero-downtime migration)
 * 6. Sync data to v2
 * 7. Update configuration
 * 8. Activate v2
 * 9. Verify activation
 * 10. Cleanup v1
 *
 * Additionally tests rollback scenario: activate v1 after v2 issues.
 */
class DarboDrabuziaiWorkflowTest extends TestCase
{
    private const APP_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const API_URL = 'https://api.bradsearch.com';
    private const TOKEN = 'darbo-drabuziai-test-token';

    /**
     * @var array<int, array{method: string, endpoint: string, body: array|null}>
     */
    private array $capturedRequests = [];

    /**
     * @var int
     */
    private int $responseIndex = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $mockResponses = [];

    /**
     * Create SDK with mocked HttpClient that captures all requests in sequence.
     *
     * @param array<int, array<string, mixed>> $mockResponses Queue of mock responses
     * @return SyncV2Sdk
     */
    private function createSdkWithRequestCapture(array $mockResponses): SyncV2Sdk
    {
        $this->capturedRequests = [];
        $this->responseIndex = 0;
        $this->mockResponses = $mockResponses;

        $config = new SyncConfigV2(self::APP_ID, self::API_URL, self::TOKEN);
        $testCase = $this;

        $httpClientMock = $this->createMock(HttpClient::class);

        $httpClientMock->method('get')->willReturnCallback(
            function (string $endpoint) use ($testCase): array {
                $testCase->capturedRequests[] = [
                    'method' => 'GET',
                    'endpoint' => $endpoint,
                    'body' => null,
                ];
                return $testCase->mockResponses[$testCase->responseIndex++] ?? [];
            }
        );

        $httpClientMock->method('post')->willReturnCallback(
            function (string $endpoint, array $body) use ($testCase): array {
                $testCase->capturedRequests[] = [
                    'method' => 'POST',
                    'endpoint' => $endpoint,
                    'body' => $body,
                ];
                return $testCase->mockResponses[$testCase->responseIndex++] ?? [];
            }
        );

        $httpClientMock->method('put')->willReturnCallback(
            function (string $endpoint, array $body) use ($testCase): array {
                $testCase->capturedRequests[] = [
                    'method' => 'PUT',
                    'endpoint' => $endpoint,
                    'body' => $body,
                ];
                return $testCase->mockResponses[$testCase->responseIndex++] ?? [];
            }
        );

        $httpClientMock->method('delete')->willReturnCallback(
            function (string $endpoint) use ($testCase): array {
                $testCase->capturedRequests[] = [
                    'method' => 'DELETE',
                    'endpoint' => $endpoint,
                    'body' => null,
                ];
                return $testCase->mockResponses[$testCase->responseIndex++] ?? [];
            }
        );

        return new class ($config, $httpClientMock) extends SyncV2Sdk {
            private HttpClient $mockedClient;

            public function __construct(SyncConfigV2 $config, HttpClient $httpClientMock)
            {
                parent::__construct($config);
                $this->mockedClient = $httpClientMock;
            }

            public function createIndex(IndexCreateRequest $request): IndexCreationResponse
            {
                $response = $this->mockedClient->post(
                    $this->getBaseApiPath() . 'index',
                    $request->jsonSerialize()
                );
                return IndexCreationResponse::fromArray($response);
            }

            public function getIndexInfo(): IndexInfoResponse
            {
                $response = $this->mockedClient->get(
                    $this->getBaseApiPath() . 'index/info'
                );
                return IndexInfoResponse::fromArray($response);
            }

            public function activateIndexVersion(int $version): VersionActivateResponse
            {
                $response = $this->mockedClient->post(
                    $this->getBaseApiPath() . 'index/activate',
                    ['version' => $version]
                );
                return VersionActivateResponse::fromArray($response);
            }

            public function deleteIndexVersion(int $version): array
            {
                return $this->mockedClient->delete(
                    $this->getBaseApiPath() . 'index/version/' . $version
                );
            }

            public function setConfiguration(QueryConfigurationRequest $config): QueryConfigurationResponse
            {
                $response = $this->mockedClient->post(
                    $this->getBaseApiPath() . 'configuration',
                    $config->jsonSerialize()
                );
                return QueryConfigurationResponse::fromArray($response);
            }

            public function getConfiguration(): QueryConfigurationResponse
            {
                $response = $this->mockedClient->get(
                    $this->getBaseApiPath() . 'configuration'
                );
                return QueryConfigurationResponse::fromArray($response);
            }

            public function updateConfiguration(QueryConfigurationRequest $config): QueryConfigurationResponse
            {
                $response = $this->mockedClient->put(
                    $this->getBaseApiPath() . 'configuration',
                    $config->jsonSerialize()
                );
                return QueryConfigurationResponse::fromArray($response);
            }

            public function deleteConfiguration(): array
            {
                return $this->mockedClient->delete(
                    $this->getBaseApiPath() . 'configuration'
                );
            }

            public function bulkOperations(BulkOperationsRequest $request): BulkOperationsResponse
            {
                $response = $this->mockedClient->post(
                    $this->getBaseApiPath() . 'sync/bulk-operations',
                    $request->jsonSerialize()
                );
                return BulkOperationsResponse::fromArray($response);
            }
        };
    }

    /**
     * Create Darbo Drabuziai index create request with all field definitions.
     */
    private function createDarboDrabuziaiIndexRequest(): IndexCreateRequest
    {
        return new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('name_lt-LT', FieldType::TEXT),
                new FieldDefinition('brand_lt-LT', FieldType::TEXT),
                new FieldDefinition('sku', FieldType::KEYWORD),
                new FieldDefinition('imageUrl', FieldType::IMAGE_URL),
                new FieldDefinition('description_lt-LT', FieldType::TEXT),
                new FieldDefinition('categories_lt-LT', FieldType::TEXT),
                new FieldDefinition('price', FieldType::DOUBLE),
                new FieldDefinition('variants', FieldType::VARIANTS, [
                    new VariantAttribute('size', FieldType::KEYWORD, true),
                    new VariantAttribute('color', FieldType::KEYWORD, true),
                ]),
            ]
        );
    }

    /**
     * Create Darbo Drabuziai search configuration with boosting.
     */
    private function createDarboDrabuziaiSearchConfig(): QueryConfigurationRequest
    {
        return new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name_lt-LT', 1, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('brand_lt-LT', 2, MatchMode::FUZZY),
                new SearchFieldConfig('description_lt-LT', 3, MatchMode::FUZZY),
                new SearchFieldConfig('sku', 4, MatchMode::EXACT),
            ],
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0),
            MultiWordOperator::AND,
            0.1
        );
    }

    /**
     * Create Darbo Drabuziai product with variants for bulk operations.
     */
    private function createDarboDrabuziaiProduct(
        string $id,
        string $name,
        string $brand,
        float $price
    ): Product {
        $variantPricing = new ProductPricing(
            $price,
            $price * 1.3,
            $price * 0.83,
            $price * 1.08
        );

        $variant = new ProductVariant(
            $id . '-M-RED',
            'SKU-' . $id . '-M-RED',
            $variantPricing,
            'https://shop.lt/produktas-' . $id . '?size=M&color=RED',
            new ImageUrl(
                'https://cdn.shop.lt/images/' . $id . '-small.jpg',
                'https://cdn.shop.lt/images/' . $id . '-medium.jpg'
            ),
            ['size' => 'M', 'color' => 'RED']
        );

        $productPricing = new ProductPricing($price, $price, $price, $price);

        return new Product(
            $id,
            'SKU-' . $id,
            $productPricing,
            new ImageUrl(
                'https://cdn.shop.lt/images/' . $id . '-small.jpg',
                'https://cdn.shop.lt/images/' . $id . '-medium.jpg'
            ),
            null,
            null,
            [
                'name_lt-LT' => $name,
                'brand_lt-LT' => $brand,
                'description_lt-LT' => 'Aukštos kokybės ' . strtolower($name),
                'categories_lt-LT' => ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
                'variants' => [$variant->jsonSerialize()],
            ]
        );
    }

    /**
     * Test: Full workflow simulation for Darbo Drabuziai client.
     *
     * This test simulates all 10 steps of the SDK workflow as defined in OpenAPI docs.
     */
    public function testFullWorkflowSimulation(): void
    {
        $basePath = 'api/v2/applications/' . self::APP_ID . '/';

        // Prepare mock responses for each step - using correct field naming conventions
        $mockResponses = [
            // Step 1: Create Index v1
            [
                'status' => 'success',
                'physical_index_name' => 'darbo_drabuziai_v1',
                'alias_name' => 'darbo_drabuziai',
                'version' => 1,
                'fields_created' => 9,
                'message' => 'Index created with 9 fields',
            ],
            // Step 2: Set Configuration
            [
                'status' => 'success',
                'index_name' => 'darbo_drabuziai',
                'cache_ttl_hours' => 24,
                'search_fields' => [
                    ['field' => 'name_lt-LT', 'position' => 1, 'match_mode' => 'phrase_prefix'],
                ],
            ],
            // Step 3: Sync Initial Data (Bulk Operations)
            [
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['type' => 'index_products', 'status' => 'success', 'items_processed' => 2, 'items_failed' => 0],
                ],
            ],
            // Step 4: Verify Index Info
            [
                'alias_name' => 'darbo_drabuziai',
                'active_version' => 1,
                'active_index' => 'darbo_drabuziai_v1',
                'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => true]],
            ],
            // Step 5: Create Index v2
            [
                'status' => 'success',
                'physical_index_name' => 'darbo_drabuziai_v2',
                'alias_name' => 'darbo_drabuziai',
                'version' => 2,
                'fields_created' => 9,
                'message' => 'Index created with 9 fields',
            ],
            // Step 6: Sync Data to v2
            [
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['type' => 'index_products', 'status' => 'success', 'items_processed' => 3, 'items_failed' => 0],
                ],
            ],
            // Step 7: Update Configuration
            [
                'status' => 'success',
                'index_name' => 'darbo_drabuziai',
                'cache_ttl_hours' => 12,
                'search_fields' => [
                    ['field' => 'name_lt-LT', 'position' => 1, 'match_mode' => 'phrase_prefix'],
                ],
            ],
            // Step 8: Activate v2
            [
                'previous_version' => 1,
                'new_version' => 2,
                'alias_name' => 'darbo_drabuziai',
            ],
            // Step 9: Verify Activation (Get Index Info)
            [
                'alias_name' => 'darbo_drabuziai',
                'active_version' => 2,
                'active_index' => 'darbo_drabuziai_v2',
                'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => false], ['version' => 2, 'index_name' => 'darbo_drabuziai_v2', 'document_count' => 150, 'created_at' => '2024-01-02T00:00:00Z', 'is_active' => true]],
            ],
            // Step 10: Cleanup v1
            [
                'status' => 'deleted',
                'message' => 'Index version 1 deleted successfully',
            ],
        ];

        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        // Step 1: Create Index v1 with Darbo Drabuziai field definitions
        $indexRequest = $this->createDarboDrabuziaiIndexRequest();
        $indexResponse = $sdk->createIndex($indexRequest);

        $this->assertInstanceOf(IndexCreationResponse::class, $indexResponse);
        $this->assertEquals('success', $indexResponse->status);
        $this->assertEquals(1, $indexResponse->version);

        // Step 2: Set Configuration with search boosting and fuzzy matching
        $configRequest = $this->createDarboDrabuziaiSearchConfig();
        $configResponse = $sdk->setConfiguration($configRequest);

        $this->assertInstanceOf(QueryConfigurationResponse::class, $configResponse);
        $this->assertEquals('success', $configResponse->status);

        // Step 3: Sync Initial Data with Darbo Drabuziai products
        $products = [
            $this->createDarboDrabuziaiProduct('12345', 'Darbo drabužis Premium', 'WorkWear Pro', 99.99),
            $this->createDarboDrabuziaiProduct('12346', 'Darbo kelnės Classic', 'WorkWear Pro', 79.99),
        ];
        $bulkRequest = new BulkOperationsRequest([BulkOperation::indexProducts($products)]);
        $bulkResponse = $sdk->bulkOperations($bulkRequest);

        $this->assertInstanceOf(BulkOperationsResponse::class, $bulkResponse);
        $this->assertEquals('success', $bulkResponse->status);
        $this->assertEquals(1, $bulkResponse->successfulOperations);
        $this->assertEquals(0, $bulkResponse->failedOperations);

        // Step 4: Verify Index Info returns v1 as active
        $indexInfo = $sdk->getIndexInfo();

        $this->assertInstanceOf(IndexInfoResponse::class, $indexInfo);
        $this->assertEquals(1, $indexInfo->activeVersion);
        $this->assertEquals('darbo_drabuziai_v1', $indexInfo->activeIndex);

        // Step 5: Create Index v2 for zero-downtime migration
        $indexResponseV2 = $sdk->createIndex($indexRequest);

        $this->assertInstanceOf(IndexCreationResponse::class, $indexResponseV2);
        $this->assertEquals(2, $indexResponseV2->version);
        $this->assertEquals('darbo_drabuziai_v2', $indexResponseV2->physicalIndexName);

        // Step 6: Sync Data to v2 with updated/new products
        $productsV2 = [
            $this->createDarboDrabuziaiProduct('12345', 'Darbo drabužis Premium V2', 'WorkWear Pro', 109.99),
            $this->createDarboDrabuziaiProduct('12346', 'Darbo kelnės Classic V2', 'WorkWear Pro', 89.99),
            $this->createDarboDrabuziaiProduct('12347', 'Darbo striukė Elite', 'WorkWear Elite', 149.99),
        ];
        $bulkRequestV2 = new BulkOperationsRequest([BulkOperation::indexProducts($productsV2)]);
        $bulkResponseV2 = $sdk->bulkOperations($bulkRequestV2);

        $this->assertInstanceOf(BulkOperationsResponse::class, $bulkResponseV2);
        $this->assertEquals('success', $bulkResponseV2->status);

        // Step 7: Update Configuration with modified search config
        $updatedConfig = new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name_lt-LT', 1, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('brand_lt-LT', 2, MatchMode::FUZZY),
                new SearchFieldConfig('description_lt-LT', 3, MatchMode::FUZZY),
                new SearchFieldConfig('sku', 4, MatchMode::EXACT),
            ],
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 4.0),
            MultiWordOperator::AND,
            0.05
        );
        $updatedConfigResponse = $sdk->updateConfiguration($updatedConfig);

        $this->assertInstanceOf(QueryConfigurationResponse::class, $updatedConfigResponse);
        $this->assertEquals('success', $updatedConfigResponse->status);

        // Step 8: Activate v2
        $activateResponse = $sdk->activateIndexVersion(2);

        $this->assertInstanceOf(VersionActivateResponse::class, $activateResponse);
        $this->assertEquals(1, $activateResponse->previousVersion);
        $this->assertEquals(2, $activateResponse->newVersion);

        // Step 9: Verify Activation
        $indexInfoAfterActivate = $sdk->getIndexInfo();

        $this->assertInstanceOf(IndexInfoResponse::class, $indexInfoAfterActivate);
        $this->assertEquals(2, $indexInfoAfterActivate->activeVersion);
        $this->assertEquals('darbo_drabuziai_v2', $indexInfoAfterActivate->activeIndex);
        $this->assertCount(2, $indexInfoAfterActivate->allVersions);
        $this->assertEquals(1, $indexInfoAfterActivate->allVersions[0]->version);
        $this->assertEquals(2, $indexInfoAfterActivate->allVersions[1]->version);

        // Step 10: Cleanup v1
        $deleteResponse = $sdk->deleteIndexVersion(1);

        $this->assertIsArray($deleteResponse);
        $this->assertEquals('deleted', $deleteResponse['status']);

        // Verify correct API endpoint order
        $expectedEndpointOrder = [
            ['method' => 'POST', 'endpoint' => $basePath . 'index'],
            ['method' => 'POST', 'endpoint' => $basePath . 'configuration'],
            ['method' => 'POST', 'endpoint' => $basePath . 'sync/bulk-operations'],
            ['method' => 'GET', 'endpoint' => $basePath . 'index/info'],
            ['method' => 'POST', 'endpoint' => $basePath . 'index'],
            ['method' => 'POST', 'endpoint' => $basePath . 'sync/bulk-operations'],
            ['method' => 'PUT', 'endpoint' => $basePath . 'configuration'],
            ['method' => 'POST', 'endpoint' => $basePath . 'index/activate'],
            ['method' => 'GET', 'endpoint' => $basePath . 'index/info'],
            ['method' => 'DELETE', 'endpoint' => $basePath . 'index/version/1'],
        ];

        $this->assertCount(count($expectedEndpointOrder), $this->capturedRequests);

        foreach ($expectedEndpointOrder as $index => $expected) {
            $this->assertEquals(
                $expected['method'],
                $this->capturedRequests[$index]['method'],
                "Step " . ($index + 1) . ": Expected method {$expected['method']}"
            );
            $this->assertEquals(
                $expected['endpoint'],
                $this->capturedRequests[$index]['endpoint'],
                "Step " . ($index + 1) . ": Expected endpoint {$expected['endpoint']}"
            );
        }
    }

    /**
     * Test: Request payloads match expected JSON structure.
     */
    public function testRequestPayloadsMatchExpectedStructure(): void
    {
        $mockResponses = [
            // Index creation
            [
                'status' => 'success',
                'physical_index_name' => 'darbo_drabuziai_v1',
                'alias_name' => 'darbo_drabuziai',
                'version' => 1,
                'fields_created' => 9,
                'message' => 'Index created',
            ],
            // Configuration
            [
                'status' => 'success',
                'index_name' => 'darbo_drabuziai',
                'cache_ttl_hours' => 24,
                'search_fields' => [
                    ['field' => 'name_lt-LT', 'position' => 1, 'match_mode' => 'phrase_prefix'],
                ],
            ],
            // Bulk operations
            [
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0],
                ],
            ],
        ];

        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        // Create index
        $indexRequest = $this->createDarboDrabuziaiIndexRequest();
        $sdk->createIndex($indexRequest);

        // Verify index creation payload structure
        $indexPayload = $this->capturedRequests[0]['body'];
        $this->assertArrayHasKey('locales', $indexPayload);
        $this->assertArrayHasKey('fields', $indexPayload);
        $this->assertEquals(['lt-LT'], $indexPayload['locales']);
        $this->assertCount(9, $indexPayload['fields']);

        // Verify field structure
        $idField = $indexPayload['fields'][0];
        $this->assertEquals('id', $idField['name']);
        $this->assertEquals('keyword', $idField['type']);

        // Verify variants field with attributes
        $variantsField = $indexPayload['fields'][8];
        $this->assertEquals('variants', $variantsField['name']);
        $this->assertEquals('variants', $variantsField['type']);
        $this->assertArrayHasKey('attributes', $variantsField);
        $this->assertCount(2, $variantsField['attributes']);

        // Set configuration
        $configRequest = $this->createDarboDrabuziaiSearchConfig();
        $sdk->setConfiguration($configRequest);

        // Verify configuration payload structure
        $configPayload = $this->capturedRequests[1]['body'];
        $this->assertArrayHasKey('search_fields', $configPayload);
        $this->assertArrayHasKey('popularity_boost', $configPayload);
        $this->assertArrayHasKey('multi_word_operator', $configPayload);
        $this->assertArrayHasKey('min_score', $configPayload);

        // Verify search fields structure
        $this->assertCount(4, $configPayload['search_fields']);
        $firstField = $configPayload['search_fields'][0];
        $this->assertEquals('name_lt-LT', $firstField['field']);
        $this->assertEquals(1, $firstField['position']);
        $this->assertEquals('phrase_prefix', $firstField['match_mode']);

        // Sync products
        $product = $this->createDarboDrabuziaiProduct('12345', 'Test', 'Brand', 99.99);
        $bulkRequest = new BulkOperationsRequest([BulkOperation::indexProducts([$product])]);
        $sdk->bulkOperations($bulkRequest);

        // Verify bulk operations payload structure
        $bulkPayload = $this->capturedRequests[2]['body'];
        $this->assertArrayHasKey('operations', $bulkPayload);
        $this->assertCount(1, $bulkPayload['operations']);

        $operation = $bulkPayload['operations'][0];
        $this->assertEquals('index_products', $operation['type']);
        $this->assertArrayHasKey('payload', $operation);
        $this->assertArrayHasKey('products', $operation['payload']);

        // Verify product structure
        $productPayload = $operation['payload']['products'][0];
        $this->assertEquals('12345', $productPayload['id']);
        $this->assertEquals(99.99, $productPayload['price']);
        $this->assertArrayHasKey('imageUrl', $productPayload);
        $this->assertArrayHasKey('variants', $productPayload);
        $this->assertArrayHasKey('name_lt-LT', $productPayload);
        $this->assertArrayHasKey('brand_lt-LT', $productPayload);
        $this->assertArrayHasKey('categories_lt-LT', $productPayload);

        // Verify variant structure
        $variantPayload = $productPayload['variants'][0];
        $this->assertArrayHasKey('id', $variantPayload);
        $this->assertArrayHasKey('sku', $variantPayload);
        $this->assertArrayHasKey('price', $variantPayload);
        $this->assertArrayHasKey('basePrice', $variantPayload);
        $this->assertArrayHasKey('priceTaxExcluded', $variantPayload);
        $this->assertArrayHasKey('basePriceTaxExcluded', $variantPayload);
        $this->assertArrayHasKey('productUrl', $variantPayload);
        $this->assertArrayHasKey('imageUrl', $variantPayload);
        $this->assertArrayHasKey('attrs', $variantPayload);
        $this->assertEquals(['size' => 'M', 'color' => 'RED'], $variantPayload['attrs']);
    }

    /**
     * Test: Response parsing works correctly for each step.
     */
    public function testResponseParsingWorksCorrectly(): void
    {
        $mockResponses = [
            // Index creation response
            [
                'status' => 'success',
                'physical_index_name' => 'darbo_drabuziai_v1',
                'alias_name' => 'darbo_drabuziai',
                'version' => 1,
                'fields_created' => 9,
                'message' => 'Index created with 9 fields',
            ],
            // Configuration response
            [
                'status' => 'success',
                'index_name' => 'darbo_drabuziai',
                'cache_ttl_hours' => 24,
                'search_fields' => [
                    ['field' => 'name_lt-LT', 'position' => 1, 'match_mode' => 'phrase_prefix'],
                ],
            ],
            // Bulk operations response
            [
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['type' => 'index_products', 'status' => 'success', 'items_processed' => 2, 'items_failed' => 0],
                ],
            ],
            // Index info response
            [
                'alias_name' => 'darbo_drabuziai',
                'active_version' => 1,
                'active_index' => 'darbo_drabuziai_v1',
                'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => true]],
            ],
            // Version activate response
            [
                'previous_version' => 1,
                'new_version' => 2,
                'alias_name' => 'darbo_drabuziai',
            ],
        ];

        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        // Test IndexCreationResponse parsing
        $indexResponse = $sdk->createIndex($this->createDarboDrabuziaiIndexRequest());
        $this->assertInstanceOf(IndexCreationResponse::class, $indexResponse);
        $this->assertEquals('success', $indexResponse->status);
        $this->assertEquals(1, $indexResponse->version);
        $this->assertEquals('darbo_drabuziai_v1', $indexResponse->physicalIndexName);
        $this->assertEquals('darbo_drabuziai', $indexResponse->aliasName);
        $this->assertEquals(9, $indexResponse->fieldsCreated);

        // Test QueryConfigurationResponse parsing
        $configResponse = $sdk->setConfiguration($this->createDarboDrabuziaiSearchConfig());
        $this->assertInstanceOf(QueryConfigurationResponse::class, $configResponse);
        $this->assertEquals('success', $configResponse->status);
        $this->assertEquals('darbo_drabuziai', $configResponse->indexName);
        $this->assertEquals(24, $configResponse->cacheTtlHours);

        // Test BulkOperationsResponse parsing
        $product = $this->createDarboDrabuziaiProduct('1', 'Test', 'Brand', 10.0);
        $bulkResponse = $sdk->bulkOperations(new BulkOperationsRequest([BulkOperation::indexProducts([$product])]));
        $this->assertInstanceOf(BulkOperationsResponse::class, $bulkResponse);
        $this->assertEquals('success', $bulkResponse->status);
        $this->assertEquals(1, $bulkResponse->totalOperations);
        $this->assertEquals(1, $bulkResponse->successfulOperations);
        $this->assertEquals(0, $bulkResponse->failedOperations);
        $this->assertCount(1, $bulkResponse->results);

        // Test IndexInfoResponse parsing
        $indexInfo = $sdk->getIndexInfo();
        $this->assertInstanceOf(IndexInfoResponse::class, $indexInfo);
        $this->assertEquals('darbo_drabuziai', $indexInfo->aliasName);
        $this->assertEquals(1, $indexInfo->activeVersion);
        $this->assertEquals('darbo_drabuziai_v1', $indexInfo->activeIndex);
        $this->assertCount(1, $indexInfo->allVersions);
        $this->assertEquals(1, $indexInfo->allVersions[0]->version);

        // Test VersionActivateResponse parsing
        $activateResponse = $sdk->activateIndexVersion(2);
        $this->assertInstanceOf(VersionActivateResponse::class, $activateResponse);
        $this->assertEquals(1, $activateResponse->previousVersion);
        $this->assertEquals(2, $activateResponse->newVersion);
        $this->assertEquals('darbo_drabuziai', $activateResponse->aliasName);
    }

    /**
     * Test: Rollback scenario - activate v1 after v2 issues.
     */
    public function testRollbackScenarioActivateV1AfterV2Issues(): void
    {
        $basePath = 'api/v2/applications/' . self::APP_ID . '/';

        $mockResponses = [
            // Step 1: Create v1
            ['status' => 'success', 'physical_index_name' => 'dd_v1', 'alias_name' => 'dd', 'version' => 1, 'fields_created' => 9, 'message' => 'Created'],
            // Step 2: Sync to v1
            ['status' => 'success', 'total_operations' => 1, 'successful_operations' => 1, 'failed_operations' => 0, 'results' => [['type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0]]],
            // Step 3: Create v2
            ['status' => 'success', 'physical_index_name' => 'dd_v2', 'alias_name' => 'dd', 'version' => 2, 'fields_created' => 9, 'message' => 'Created'],
            // Step 4: Sync to v2
            ['status' => 'success', 'total_operations' => 1, 'successful_operations' => 1, 'failed_operations' => 0, 'results' => [['type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0]]],
            // Step 5: Activate v2
            ['previous_version' => 1, 'new_version' => 2, 'alias_name' => 'darbo_drabuziai'],
            // Step 6: Verify v2 active
            ['alias_name' => 'darbo_drabuziai', 'active_version' => 2, 'active_index' => 'darbo_drabuziai_v2', 'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => false], ['version' => 2, 'index_name' => 'darbo_drabuziai_v2', 'document_count' => 150, 'created_at' => '2024-01-02T00:00:00Z', 'is_active' => true]]],
            // Step 7: ROLLBACK - Activate v1 due to issues
            ['previous_version' => 2, 'new_version' => 1, 'alias_name' => 'darbo_drabuziai'],
            // Step 8: Verify rollback
            ['alias_name' => 'darbo_drabuziai', 'active_version' => 1, 'active_index' => 'darbo_drabuziai_v1', 'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => false], ['version' => 2, 'index_name' => 'darbo_drabuziai_v2', 'document_count' => 150, 'created_at' => '2024-01-02T00:00:00Z', 'is_active' => true]]],
            // Step 9: Cleanup v2
            ['status' => 'deleted', 'message' => 'Index version 2 deleted successfully'],
        ];

        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        // Create and populate v1
        $indexRequest = $this->createDarboDrabuziaiIndexRequest();
        $v1Response = $sdk->createIndex($indexRequest);
        $this->assertEquals(1, $v1Response->version);
        $this->assertEquals('success', $v1Response->status);

        $product = $this->createDarboDrabuziaiProduct('1', 'Test', 'Brand', 10.0);
        $sdk->bulkOperations(new BulkOperationsRequest([BulkOperation::indexProducts([$product])]));

        // Create and populate v2
        $v2Response = $sdk->createIndex($indexRequest);
        $this->assertEquals(2, $v2Response->version);
        $this->assertEquals('success', $v2Response->status);

        $sdk->bulkOperations(new BulkOperationsRequest([BulkOperation::indexProducts([$product])]));

        // Activate v2
        $activateV2 = $sdk->activateIndexVersion(2);
        $this->assertEquals(1, $activateV2->previousVersion);
        $this->assertEquals(2, $activateV2->newVersion);

        // Verify v2 is active
        $infoAfterV2 = $sdk->getIndexInfo();
        $this->assertEquals(2, $infoAfterV2->activeVersion);

        // ROLLBACK: Activate v1 due to issues with v2
        $rollbackResponse = $sdk->activateIndexVersion(1);
        $this->assertEquals(2, $rollbackResponse->previousVersion);
        $this->assertEquals(1, $rollbackResponse->newVersion);

        // Verify rollback successful
        $infoAfterRollback = $sdk->getIndexInfo();
        $this->assertEquals(1, $infoAfterRollback->activeVersion);
        $this->assertEquals('darbo_drabuziai_v1', $infoAfterRollback->activeIndex);

        // Cleanup problematic v2
        $deleteResponse = $sdk->deleteIndexVersion(2);
        $this->assertEquals('deleted', $deleteResponse['status']);

        // Verify rollback request sequence
        $rollbackRequest = $this->capturedRequests[6];
        $this->assertEquals('POST', $rollbackRequest['method']);
        $this->assertEquals($basePath . 'index/activate', $rollbackRequest['endpoint']);
        $this->assertEquals(['version' => 1], $rollbackRequest['body']);
    }

    /**
     * Test: All API endpoints use correct V2 path format.
     */
    public function testAllEndpointsUseCorrectV2PathFormat(): void
    {
        // Full responses for each operation type
        $indexResponse = ['status' => 'success', 'physical_index_name' => 'test_v1', 'alias_name' => 'test', 'version' => 1, 'fields_created' => 9, 'message' => 'Created'];
        $configResponse = ['status' => 'success', 'index_name' => 'test', 'cache_ttl_hours' => 24, 'search_fields' => [['field' => 'name', 'position' => 1, 'match_mode' => 'fuzzy']]];
        $bulkResponse = ['status' => 'success', 'total_operations' => 1, 'successful_operations' => 1, 'failed_operations' => 0, 'results' => [['type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0]]];
        $infoResponse = ['alias_name' => 'test', 'active_version' => 1, 'active_index' => 'test_v1', 'all_versions' => [['version' => 1, 'index_name' => 'darbo_drabuziai_v1', 'document_count' => 100, 'created_at' => '2024-01-01T00:00:00Z', 'is_active' => true]]];
        $activateResponse = ['previous_version' => 0, 'new_version' => 1, 'alias_name' => 'test'];
        $deleteResponse = ['status' => 'deleted', 'message' => 'Deleted'];

        $mockResponses = [
            $indexResponse,     // createIndex
            $configResponse,    // setConfiguration
            $bulkResponse,      // bulkOperations
            $infoResponse,      // getIndexInfo
            $activateResponse,  // activateIndexVersion
            $configResponse,    // updateConfiguration
            $configResponse,    // getConfiguration
            $deleteResponse,    // deleteConfiguration
            $deleteResponse,    // deleteIndexVersion
        ];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $indexRequest = $this->createDarboDrabuziaiIndexRequest();
        $configRequest = $this->createDarboDrabuziaiSearchConfig();
        $product = $this->createDarboDrabuziaiProduct('1', 'Test', 'Brand', 10.0);
        $bulkRequest = new BulkOperationsRequest([BulkOperation::indexProducts([$product])]);

        // Execute various operations
        $sdk->createIndex($indexRequest);
        $sdk->setConfiguration($configRequest);
        $sdk->bulkOperations($bulkRequest);
        $sdk->getIndexInfo();
        $sdk->activateIndexVersion(1);
        $sdk->updateConfiguration($configRequest);
        $sdk->getConfiguration();
        $sdk->deleteConfiguration();
        $sdk->deleteIndexVersion(1);

        // Verify all endpoints contain correct V2 path
        foreach ($this->capturedRequests as $request) {
            $this->assertStringStartsWith(
                'api/v2/applications/',
                $request['endpoint'],
                "Endpoint does not use V2 path format: {$request['endpoint']}"
            );
            $this->assertStringContainsString(
                self::APP_ID,
                $request['endpoint'],
                "Endpoint does not contain app ID: {$request['endpoint']}"
            );
        }
    }

    /**
     * Test: Index create request matches OpenAPI workflow documentation.
     */
    public function testIndexCreateMatchesOpenApiDocumentation(): void
    {
        $mockResponses = [['status' => 'success', 'physical_index_name' => 'test_v1', 'alias_name' => 'test', 'version' => 1, 'fields_created' => 9, 'message' => 'Created']];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $sdk->createIndex($this->createDarboDrabuziaiIndexRequest());

        $payload = $this->capturedRequests[0]['body'];

        // Verify exact field structure as documented
        $expectedFieldNames = [
            'id',
            'name_lt-LT',
            'brand_lt-LT',
            'sku',
            'imageUrl',
            'description_lt-LT',
            'categories_lt-LT',
            'price',
            'variants',
        ];

        $actualFieldNames = array_map(fn($field) => $field['name'], $payload['fields']);
        $this->assertEquals($expectedFieldNames, $actualFieldNames);

        // Verify field types
        $expectedTypes = [
            'keyword',
            'text',
            'text',
            'keyword',
            'image_url',
            'text',
            'text',
            'double',
            'variants',
        ];

        $actualTypes = array_map(fn($field) => $field['type'], $payload['fields']);
        $this->assertEquals($expectedTypes, $actualTypes);
    }

    /**
     * Test: Configuration with nested variants search.
     */
    public function testConfigurationWithNestedVariantsSearch(): void
    {
        $mockResponses = [['status' => 'success', 'index_name' => 'test', 'cache_ttl_hours' => 24, 'search_fields' => [['field' => 'name_lt-LT', 'position' => 1, 'match_mode' => 'phrase_prefix']]]];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $config = $this->createDarboDrabuziaiSearchConfig();
        $sdk->setConfiguration($config);

        $payload = $this->capturedRequests[0]['body'];

        // Verify search fields include variant-searchable fields
        $searchFieldNames = array_map(fn($field) => $field['field'], $payload['search_fields']);
        $this->assertContains('sku', $searchFieldNames);

        // Verify popularity boost for sorting by sales
        $this->assertTrue($payload['popularity_boost']['enabled']);
        $this->assertEquals('sales_count', $payload['popularity_boost']['field']);
        $this->assertEquals('logarithmic', $payload['popularity_boost']['algorithm']);

        // Verify multi-word operator for precise matching
        $this->assertEquals('and', $payload['multi_word_operator']);
    }

    /**
     * Test: Bulk operations with multiple products containing variants.
     */
    public function testBulkOperationsWithMultipleProductsAndVariants(): void
    {
        $mockResponses = [['status' => 'success', 'total_operations' => 1, 'successful_operations' => 1, 'failed_operations' => 0, 'results' => [['type' => 'index_products', 'status' => 'success', 'items_processed' => 3, 'items_failed' => 0]]]];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $products = [
            $this->createDarboDrabuziaiProduct('P001', 'Darbo kelnės', 'WorkWear', 89.99),
            $this->createDarboDrabuziaiProduct('P002', 'Darbo striukė', 'SafetyFirst', 129.99),
            $this->createDarboDrabuziaiProduct('P003', 'Darbo kepurė', 'WorkWear', 29.99),
        ];

        $bulkRequest = new BulkOperationsRequest([BulkOperation::indexProducts($products)]);
        $sdk->bulkOperations($bulkRequest);

        $payload = $this->capturedRequests[0]['body'];
        $productsPayload = $payload['operations'][0]['payload']['products'];

        $this->assertCount(3, $productsPayload);

        // Verify each product has correct structure
        foreach ($productsPayload as $index => $productData) {
            $this->assertArrayHasKey('id', $productData, "Product {$index} missing id");
            $this->assertArrayHasKey('price', $productData, "Product {$index} missing price");
            $this->assertArrayHasKey('imageUrl', $productData, "Product {$index} missing imageUrl");
            $this->assertArrayHasKey('variants', $productData, "Product {$index} missing variants");
            $this->assertArrayHasKey('name_lt-LT', $productData, "Product {$index} missing name_lt-LT");
            $this->assertArrayHasKey('brand_lt-LT', $productData, "Product {$index} missing brand_lt-LT");
            $this->assertArrayHasKey('categories_lt-LT', $productData, "Product {$index} missing categories_lt-LT");

            // Verify variant structure
            $this->assertCount(1, $productData['variants']);
            $variant = $productData['variants'][0];
            $this->assertArrayHasKey('attrs', $variant);
            $this->assertEquals(['size' => 'M', 'color' => 'RED'], $variant['attrs']);
        }
    }

    /**
     * Test: Version activation request format.
     */
    public function testVersionActivationRequestFormat(): void
    {
        $mockResponses = [['previous_version' => 1, 'new_version' => 2, 'alias_name' => 'test']];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $sdk->activateIndexVersion(2);

        $request = $this->capturedRequests[0];

        $this->assertEquals('POST', $request['method']);
        $this->assertStringEndsWith('/index/activate', $request['endpoint']);
        $this->assertEquals(['version' => 2], $request['body']);
    }

    /**
     * Test: Delete index version request format.
     */
    public function testDeleteIndexVersionRequestFormat(): void
    {
        $mockResponses = [['status' => 'deleted']];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $sdk->deleteIndexVersion(3);

        $request = $this->capturedRequests[0];

        $this->assertEquals('DELETE', $request['method']);
        $this->assertStringEndsWith('/index/version/3', $request['endpoint']);
        $this->assertNull($request['body']);
    }

    /**
     * Test: SDK correctly handles app ID in base path.
     */
    public function testSdkCorrectlyHandlesAppIdInBasePath(): void
    {
        $mockResponses = [['status' => 'success']];
        $sdk = $this->createSdkWithRequestCapture($mockResponses);

        $this->assertEquals(self::APP_ID, $sdk->getAppId());
        $this->assertEquals(
            'api/v2/applications/' . self::APP_ID . '/',
            $sdk->getBaseApiPath()
        );
    }
}
