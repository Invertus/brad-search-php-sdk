<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Models;

use BradSearch\SyncSdk\Models\BulkOperation;
use BradSearch\SyncSdk\Models\BulkOperationResult;
use BradSearch\SyncSdk\Enums\BulkOperationType;
use PHPUnit\Framework\TestCase;

class BulkOperationTest extends TestCase
{
    public function testIndexProductsOperation(): void
    {
        $products = [
            [
                'id' => 'prod-123',
                'name' => 'Test Product',
                'price' => 99.99
            ]
        ];

        $subfields = [
            'name' => [
                'split_by' => [' ', '-'],
                'max_count' => 3
            ]
        ];

        $embeddableFields = [
            'description' => 'name'
        ];

        $operation = BulkOperation::indexProducts('products-v1', $products, $subfields, $embeddableFields);

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $operation->type);
        $this->assertEquals('products-v1', $operation->payload['index_name']);
        $this->assertEquals($products, $operation->payload['products']);
        $this->assertEquals($subfields, $operation->payload['subfields']);
        $this->assertEquals($embeddableFields, $operation->payload['embeddablefields']);

        $array = $operation->toArray();
        $this->assertEquals('index_products', $array['type']);
        $this->assertArrayHasKey('payload', $array);
    }

    public function testUpdateProductsOperation(): void
    {
        $updates = [
            [
                'id' => 'prod-123',
                'fields' => [
                    'name' => 'Updated Product',
                    'price' => 129.99
                ]
            ]
        ];

        $operation = BulkOperation::updateProducts('products-v1', $updates);

        $this->assertEquals(BulkOperationType::UPDATE_PRODUCTS, $operation->type);
        $this->assertEquals('products-v1', $operation->payload['index_name']);
        $this->assertEquals($updates, $operation->payload['updates']);

        $array = $operation->toArray();
        $this->assertEquals('update_products', $array['type']);
    }

    public function testDeleteProductsOperation(): void
    {
        $productIds = ['prod-123', 'prod-124', 'prod-125'];

        $operation = BulkOperation::deleteProducts('products-v1', $productIds);

        $this->assertEquals(BulkOperationType::DELETE_PRODUCTS, $operation->type);
        $this->assertEquals('products-v1', $operation->payload['index_name']);
        $this->assertEquals($productIds, $operation->payload['product_ids']);

        $array = $operation->toArray();
        $this->assertEquals('delete_products', $array['type']);
    }

    public function testDeleteIndexOperation(): void
    {
        $operation = BulkOperation::deleteIndex('old-products-index');

        $this->assertEquals(BulkOperationType::DELETE_INDEX, $operation->type);
        $this->assertEquals('old-products-index', $operation->payload['index_name']);

        $array = $operation->toArray();
        $this->assertEquals('delete_index', $array['type']);
    }

    public function testIndexProductsWithoutOptionalFields(): void
    {
        $products = [
            ['id' => 'prod-123', 'name' => 'Test Product']
        ];

        $operation = BulkOperation::indexProducts('products-v1', $products);

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $operation->type);
        $this->assertEquals('products-v1', $operation->payload['index_name']);
        $this->assertEquals($products, $operation->payload['products']);
        $this->assertArrayNotHasKey('subfields', $operation->payload);
        $this->assertArrayNotHasKey('embeddablefields', $operation->payload);
    }
}

class BulkOperationResultTest extends TestCase
{
    public function testFromApiResponseSuccess(): void
    {
        $apiResponse = [
            'status' => 'success',
            'message' => 'All 2 operations completed successfully',
            'total_operations' => 2,
            'successful_operations' => 2,
            'failed_operations' => 0,
            'processing_time_ms' => 1500,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 5,
                    'index_name' => 'products-v1'
                ],
                [
                    'type' => 'delete_products',
                    'status' => 'success',
                    'message' => 'Products deleted',
                    'count' => 3,
                    'index_name' => 'products-v1'
                ]
            ]
        ];

        $result = BulkOperationResult::fromApiResponse($apiResponse);

        $this->assertEquals('success', $result->status);
        $this->assertEquals('All 2 operations completed successfully', $result->message);
        $this->assertEquals(2, $result->totalOperations);
        $this->assertEquals(2, $result->successfulOperations);
        $this->assertEquals(0, $result->failedOperations);
        $this->assertEquals(1500, $result->processingTimeMs);
        $this->assertCount(2, $result->results);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isPartialSuccess());
        $this->assertFalse($result->hasFailures());
        $this->assertEmpty($result->getFailedResults());
        $this->assertCount(2, $result->getSuccessfulResults());
    }

    public function testFromApiResponsePartialSuccess(): void
    {
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

        $result = BulkOperationResult::fromApiResponse($apiResponse);

        $this->assertEquals('partial', $result->status);
        $this->assertEquals(2, $result->totalOperations);
        $this->assertEquals(1, $result->successfulOperations);
        $this->assertEquals(1, $result->failedOperations);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isPartialSuccess());
        $this->assertTrue($result->hasFailures());
        $this->assertCount(1, $result->getFailedResults());
        $this->assertCount(1, $result->getSuccessfulResults());

        $failedResults = $result->getFailedResults();
        $this->assertEquals('delete_index', $failedResults[0]['type']);
        $this->assertEquals('error', $failedResults[0]['status']);

        $successfulResults = $result->getSuccessfulResults();
        $this->assertEquals('index_products', $successfulResults[0]['type']);
        $this->assertEquals('success', $successfulResults[0]['status']);
    }

    public function testFromApiResponseCompleteFailure(): void
    {
        $apiResponse = [
            'status' => 'error',
            'message' => 'All 1 operations failed',
            'total_operations' => 1,
            'successful_operations' => 0,
            'failed_operations' => 1,
            'processing_time_ms' => 100,
            'results' => [
                [
                    'type' => 'invalid_operation',
                    'status' => 'error',
                    'message' => 'Unsupported operation type',
                    'error' => 'operation at index 0 has unsupported type'
                ]
            ]
        ];

        $result = BulkOperationResult::fromApiResponse($apiResponse);

        $this->assertEquals('error', $result->status);
        $this->assertEquals(1, $result->totalOperations);
        $this->assertEquals(0, $result->successfulOperations);
        $this->assertEquals(1, $result->failedOperations);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isPartialSuccess());
        $this->assertTrue($result->hasFailures());
        $this->assertCount(1, $result->getFailedResults());
        $this->assertEmpty($result->getSuccessfulResults());
    }
}