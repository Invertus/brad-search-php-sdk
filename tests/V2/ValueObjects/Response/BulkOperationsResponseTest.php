<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\ItemResult;
use PHPUnit\Framework\TestCase;

final class BulkOperationsResponseTest extends TestCase
{
    private function createItemResult(
        string $id = 'prod-123',
        string $status = 'created',
        ?string $error = null
    ): ItemResult {
        return new ItemResult($id, BulkOperationType::INDEX_PRODUCTS, $status, $error);
    }

    public function testConstructorWithValidValues(): void
    {
        $results = [$this->createItemResult()];

        $response = new BulkOperationsResponse(
            status: 'success',
            totalOperations: 1,
            successfulOperations: 1,
            failedOperations: 0,
            results: $results
        );

        $this->assertEquals('success', $response->status);
        $this->assertEquals(1, $response->totalOperations);
        $this->assertEquals(1, $response->successfulOperations);
        $this->assertEquals(0, $response->failedOperations);
        $this->assertCount(1, $response->results);
    }

    public function testConstructorWithWarningsAndProcessingTime(): void
    {
        $results = [$this->createItemResult()];
        $warnings = ['Warning 1', 'Warning 2'];

        $response = new BulkOperationsResponse(
            status: 'success',
            totalOperations: 1,
            successfulOperations: 1,
            failedOperations: 0,
            results: $results,
            warnings: $warnings,
            processingTimeMs: 125
        );

        $this->assertEquals($warnings, $response->warnings);
        $this->assertEquals(125, $response->processingTimeMs);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'status' => 'success',
            'total_operations' => 2,
            'successful_operations' => 2,
            'failed_operations' => 0,
            'results' => [
                [
                    'id' => 'prod-1',
                    'operation' => 'index_products',
                    'status' => 'created',
                ],
                [
                    'id' => 'prod-2',
                    'operation' => 'index_products',
                    'status' => 'created',
                ],
            ],
        ];

        $response = BulkOperationsResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertEquals(2, $response->totalOperations);
        $this->assertCount(2, $response->results);
        $this->assertInstanceOf(ItemResult::class, $response->results[0]);
        $this->assertInstanceOf(ItemResult::class, $response->results[1]);
    }

    public function testFromArrayWithWarningsAndProcessingTime(): void
    {
        $data = [
            'status' => 'partial',
            'total_operations' => 1,
            'successful_operations' => 0,
            'failed_operations' => 1,
            'results' => [
                [
                    'id' => 'prod-1',
                    'operation' => 'index_products',
                    'status' => 'error',
                    'error' => 'Invalid data',
                ],
            ],
            'warnings' => ['Locale not supported'],
            'processing_time_ms' => 250,
        ];

        $response = BulkOperationsResponse::fromArray($data);

        $this->assertEquals('partial', $response->status);
        $this->assertEquals(['Locale not supported'], $response->warnings);
        $this->assertEquals(250, $response->processingTimeMs);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        BulkOperationsResponse::fromArray([
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'results' => [],
        ]);
    }

    public function testIsFullySuccessful(): void
    {
        $success = new BulkOperationsResponse('success', 1, 1, 0, [$this->createItemResult()]);
        $this->assertTrue($success->isFullySuccessful());

        $partial = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createItemResult('prod-1', 'created'),
            $this->createItemResult('prod-2', 'error', 'Failed'),
        ]);
        $this->assertFalse($partial->isFullySuccessful());
    }

    public function testHasFailures(): void
    {
        $success = new BulkOperationsResponse('success', 1, 1, 0, [$this->createItemResult()]);
        $this->assertFalse($success->hasFailures());

        $partial = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createItemResult('prod-1', 'created'),
            $this->createItemResult('prod-2', 'error', 'Failed'),
        ]);
        $this->assertTrue($partial->hasFailures());
    }

    public function testGetFailedResults(): void
    {
        $response = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createItemResult('prod-1', 'created'),
            $this->createItemResult('prod-2', 'error', 'Failed'),
        ]);

        $failed = $response->getFailedResults();
        $this->assertCount(1, $failed);
        $this->assertEquals('prod-2', array_values($failed)[0]->id);
    }

    public function testGetSuccessfulResults(): void
    {
        $response = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createItemResult('prod-1', 'created'),
            $this->createItemResult('prod-2', 'error', 'Failed'),
        ]);

        $successful = $response->getSuccessfulResults();
        $this->assertCount(1, $successful);
        $this->assertEquals('prod-1', array_values($successful)[0]->id);
    }

    public function testJsonSerialize(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createItemResult()]);

        $expected = [
            'status' => 'success',
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'results' => [
                [
                    'id' => 'prod-123',
                    'operation' => 'index_products',
                    'status' => 'created',
                ],
            ],
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testJsonSerializeWithWarningsAndProcessingTime(): void
    {
        $response = new BulkOperationsResponse(
            'success',
            1,
            1,
            0,
            [$this->createItemResult()],
            ['Warning'],
            150
        );

        $serialized = $response->jsonSerialize();

        $this->assertEquals(['Warning'], $serialized['warnings']);
        $this->assertEquals(150, $serialized['processing_time_ms']);
    }

    public function testThrowsOnEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new BulkOperationsResponse('', 1, 1, 0, [$this->createItemResult()]);
    }

    public function testThrowsOnNegativeTotalOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BulkOperationsResponse('success', -1, 1, 0, [$this->createItemResult()]);
    }

    public function testThrowsOnNegativeSuccessfulOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BulkOperationsResponse('success', 1, -1, 0, [$this->createItemResult()]);
    }

    public function testThrowsOnNegativeFailedOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BulkOperationsResponse('success', 1, 1, -1, [$this->createItemResult()]);
    }

    public function testRejectsNonItemResultInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result at index 1 must be an instance of ItemResult');

        new BulkOperationsResponse('success', 2, 2, 0, [
            $this->createItemResult(),
            'not an ItemResult',
        ]);
    }
}
