<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\SynchronizationApiSdk;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Models\BulkOperation;
use BradSearch\SyncSdk\Models\BulkOperationResult;
use BradSearch\SyncSdk\Enums\FieldType;
use BradSearch\SyncSdk\Enums\BulkOperationType;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\Client\HttpClient;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SynchronizationApiSdkBulkOperationsTest extends TestCase
{
    private array $fieldConfiguration;

    protected function setUp(): void
    {
        $this->fieldConfiguration = [
            'id' => new FieldConfig(FieldType::KEYWORD, []),
            'name' => new FieldConfig(FieldType::TEXT_KEYWORD, []),
            'price' => new FieldConfig(FieldType::FLOAT, []),
            'brand' => new FieldConfig(FieldType::TEXT_KEYWORD, [])
        ];
    }

    private function createSdkWithMockedHttpClient(HttpClient $httpClientMock): SynchronizationApiSdk
    {
        $config = new SyncConfig('http://api.test.com', 'test-token');

        return new class($config, $this->fieldConfiguration, $httpClientMock) extends SynchronizationApiSdk {
            public function __construct(SyncConfig $config, array $fieldConfiguration, private HttpClient $mockedHttpClient)
            {
                parent::__construct($config, $fieldConfiguration);
            }

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

                $response = $this->mockedHttpClient->post("api/v1/sync/bulk-operations", $data);

                return BulkOperationResult::fromApiResponse($response);
            }
        };
    }

    public function testBulkOperationsSuccess(): void
    {
        $operations = [
            BulkOperation::indexProducts('products-v1', [
                ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99]
            ]),
            BulkOperation::updateProducts('products-v1', [
                ['id' => 'prod-124', 'fields' => ['price' => 129.99]]
            ]),
            BulkOperation::deleteProducts('products-v1', ['prod-125']),
        ];

        $apiResponse = [
            'status' => 'success',
            'message' => 'All 3 operations completed successfully',
            'total_operations' => 3,
            'successful_operations' => 3,
            'failed_operations' => 0,
            'processing_time_ms' => 2156,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1'
                ],
                [
                    'type' => 'update_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1'
                ],
                [
                    'type' => 'delete_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1'
                ]
            ]
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with('api/v1/sync/bulk-operations', [
                'operations' => [
                    [
                        'type' => 'index_products',
                        'payload' => [
                            'index_name' => 'products-v1',
                            'products' => [
                                ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99]
                            ]
                        ]
                    ],
                    [
                        'type' => 'update_products',
                        'payload' => [
                            'index_name' => 'products-v1',
                            'updates' => [
                                ['id' => 'prod-124', 'fields' => ['price' => 129.99]]
                            ]
                        ]
                    ],
                    [
                        'type' => 'delete_products',
                        'payload' => [
                            'index_name' => 'products-v1',
                            'product_ids' => ['prod-125']
                        ]
                    ]
                ]
            ])
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertInstanceOf(BulkOperationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $result->totalOperations);
        $this->assertEquals(3, $result->successfulOperations);
        $this->assertEquals(0, $result->failedOperations);
        $this->assertFalse($result->hasFailures());
    }

    public function testBulkOperationsPartialFailure(): void
    {
        $operations = [
            BulkOperation::indexProducts('products-v1', [
                ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99]
            ]),
            BulkOperation::deleteIndex('non-existent-index')
        ];

        $apiResponse = [
            'status' => 'partial',
            'message' => '1 operations succeeded, 1 operations failed',
            'total_operations' => 2,
            'successful_operations' => 1,
            'failed_operations' => 1,
            'processing_time_ms' => 856,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1'
                ],
                [
                    'type' => 'delete_index',
                    'status' => 'error',
                    'message' => 'Index does not exist',
                    'error' => 'index not found',
                    'index_name' => 'non-existent-index'
                ]
            ]
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertTrue($result->isPartialSuccess());
        $this->assertTrue($result->hasFailures());
        $this->assertEquals(1, $result->failedOperations);
        $this->assertCount(1, $result->getFailedResults());
        $this->assertCount(1, $result->getSuccessfulResults());
    }

    public function testBulkOperationsEmptyOperations(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No operations provided');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([]);
    }

    public function testBulkOperationsInvalidOperationType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('All operations must be instances of BulkOperation');

        $operations = [
            BulkOperation::indexProducts('products-v1', []),
            'invalid-operation' // Not a BulkOperation instance
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($operations);
    }

    public function testBulkOperationValidationIndexProducts(): void
    {
        // Test missing index_name
        $operation = new BulkOperation(BulkOperationType::INDEX_PRODUCTS, [
            'products' => [['id' => 'prod-123']]
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('INDEX_PRODUCTS operation requires index_name and products');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testBulkOperationValidationUpdateProducts(): void
    {
        // Test missing updates
        $operation = new BulkOperation(BulkOperationType::UPDATE_PRODUCTS, [
            'index_name' => 'products-v1'
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('UPDATE_PRODUCTS operation requires index_name and updates');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testBulkOperationValidationDeleteProducts(): void
    {
        // Test empty product_ids
        $operation = new BulkOperation(BulkOperationType::DELETE_PRODUCTS, [
            'index_name' => 'products-v1',
            'product_ids' => []
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('product_ids must be a non-empty array');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testBulkOperationValidationDeleteIndex(): void
    {
        // Test missing index_name
        $operation = new BulkOperation(BulkOperationType::DELETE_INDEX, []);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('DELETE_INDEX operation requires index_name');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testBulkOperationValidationInvalidIndex(): void
    {
        $operation = BulkOperation::indexProducts('', []);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Index name cannot be empty');

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testBulkOperationValidationInvalidProductData(): void
    {
        // Create product with invalid data (missing required field)
        $operation = BulkOperation::indexProducts('products-v1', [
            ['invalid_field' => 'value'] // Missing 'id' field
        ]);

        $this->expectException(ValidationException::class);

        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations([$operation]);
    }

    public function testDeleteIndexOperation(): void
    {
        $operations = [
            BulkOperation::deleteIndex('old-products-index')
        ];

        $apiResponse = [
            'status' => 'success',
            'message' => 'All 1 operations completed successfully',
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'processing_time_ms' => 456,
            'results' => [
                [
                    'type' => 'delete_index',
                    'status' => 'success',
                    'message' => 'Index deleted successfully',
                    'count' => 1,
                    'index_name' => 'old-products-index'
                ]
            ]
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with('api/v1/sync/bulk-operations', [
                'operations' => [
                    [
                        'type' => 'delete_index',
                        'payload' => [
                            'index_name' => 'old-products-index'
                        ]
                    ]
                ]
            ])
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->totalOperations);
        $this->assertEquals(1, $result->successfulOperations);
        $this->assertEquals(0, $result->failedOperations);
    }

    public function testIndexProductsWithSubfieldsAndEmbeddableFields(): void
    {
        $subfields = [
            'name' => [
                'split_by' => [' ', '-'],
                'max_count' => 3
            ]
        ];

        $embeddableFields = [
            'description' => 'name'
        ];

        $operations = [
            BulkOperation::indexProducts('products-v1', [
                ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99]
            ], $subfields, $embeddableFields)
        ];

        $apiResponse = [
            'status' => 'success',
            'message' => 'All 1 operations completed successfully',
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'processing_time_ms' => 1245,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1'
                ]
            ]
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with('api/v1/sync/bulk-operations', [
                'operations' => [
                    [
                        'type' => 'index_products',
                        'payload' => [
                            'index_name' => 'products-v1',
                            'products' => [
                                ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99]
                            ],
                            'subfields' => $subfields,
                            'embeddablefields' => $embeddableFields
                        ]
                    ]
                ]
            ])
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);
        $this->assertTrue($result->isSuccess());
    }
}