<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\OperationResult;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class BulkOperationsResponseTest extends TestCase
{
    private function createOperationResult(
        string $status = 'success',
        int $processed = 100,
        int $failed = 0
    ): OperationResult {
        return new OperationResult(BulkOperationType::INDEX_PRODUCTS, $status, $processed, $failed);
    }

    public function testConstructorWithValidValues(): void
    {
        $results = [$this->createOperationResult()];

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

    public function testExtendsValueObject(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertInstanceOf(JsonSerializable::class, $response);
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
                    'operation_type' => 'index_products',
                    'status' => 'success',
                    'items_processed' => 100,
                    'items_failed' => 0,
                ],
                [
                    'operation_type' => 'index_products',
                    'status' => 'success',
                    'items_processed' => 50,
                    'items_failed' => 0,
                ],
            ],
        ];

        $response = BulkOperationsResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertEquals(2, $response->totalOperations);
        $this->assertEquals(2, $response->successfulOperations);
        $this->assertEquals(0, $response->failedOperations);
        $this->assertCount(2, $response->results);
        $this->assertInstanceOf(OperationResult::class, $response->results[0]);
        $this->assertInstanceOf(OperationResult::class, $response->results[1]);
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

    public function testFromArrayThrowsOnMissingTotalOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: total_operations');

        BulkOperationsResponse::fromArray([
            'status' => 'success',
            'successful_operations' => 1,
            'failed_operations' => 0,
            'results' => [],
        ]);
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new BulkOperationsResponse('', 1, 1, 0, [$this->createOperationResult()]);
    }

    public function testRejectsNegativeTotalOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('total_operations must be non-negative');

        new BulkOperationsResponse('success', -1, 1, 0, [$this->createOperationResult()]);
    }

    public function testRejectsNegativeSuccessfulOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('successful_operations must be non-negative');

        new BulkOperationsResponse('success', 1, -1, 0, [$this->createOperationResult()]);
    }

    public function testRejectsNegativeFailedOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed_operations must be non-negative');

        new BulkOperationsResponse('success', 1, 1, -1, [$this->createOperationResult()]);
    }

    public function testRejectsNonOperationResultInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result at index 1 must be an instance of OperationResult');

        new BulkOperationsResponse('success', 2, 2, 0, [
            $this->createOperationResult(),
            'not an OperationResult',
        ]);
    }

    public function testIsFullySuccessfulReturnsTrue(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertTrue($response->isFullySuccessful());
    }

    public function testIsFullySuccessfulReturnsFalseForFailedOperations(): void
    {
        $response = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createOperationResult(),
            $this->createOperationResult('failed', 50, 50),
        ]);

        $this->assertFalse($response->isFullySuccessful());
    }

    public function testIsFullySuccessfulReturnsFalseForNonSuccessStatus(): void
    {
        $response = new BulkOperationsResponse('partial', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertFalse($response->isFullySuccessful());
    }

    public function testHasFailuresReturnsTrue(): void
    {
        $response = new BulkOperationsResponse('partial', 2, 1, 1, [
            $this->createOperationResult(),
            $this->createOperationResult('failed', 50, 50),
        ]);

        $this->assertTrue($response->hasFailures());
    }

    public function testHasFailuresReturnsFalse(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertFalse($response->hasFailures());
    }

    public function testGetFailedResultsReturnsFailedOnly(): void
    {
        $successResult = $this->createOperationResult('success', 100, 0);
        $failedResult = $this->createOperationResult('partial', 50, 10);

        $response = new BulkOperationsResponse('partial', 2, 1, 1, [$successResult, $failedResult]);

        $failedResults = $response->getFailedResults();

        $this->assertCount(1, $failedResults);
        $this->assertSame($failedResult, array_values($failedResults)[0]);
    }

    public function testGetFailedResultsReturnsEmptyForAllSuccess(): void
    {
        $response = new BulkOperationsResponse('success', 2, 2, 0, [
            $this->createOperationResult(),
            $this->createOperationResult(),
        ]);

        $this->assertEmpty($response->getFailedResults());
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('status', $serialized);
        $this->assertArrayHasKey('total_operations', $serialized);
        $this->assertArrayHasKey('successful_operations', $serialized);
        $this->assertArrayHasKey('failed_operations', $serialized);
        $this->assertArrayHasKey('results', $serialized);
        $this->assertEquals('success', $serialized['status']);
        $this->assertEquals(1, $serialized['total_operations']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'status' => 'success',
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'results' => [
                [
                    'operation_type' => 'index_products',
                    'status' => 'success',
                    'items_processed' => 150,
                    'items_failed' => 0,
                ],
            ],
        ];

        $response = BulkOperationsResponse::fromArray($apiResponse);

        $this->assertEquals('success', $response->status);
        $this->assertEquals(1, $response->totalOperations);
        $this->assertEquals(1, $response->successfulOperations);
        $this->assertEquals(0, $response->failedOperations);
        $this->assertCount(1, $response->results);
        $this->assertTrue($response->isFullySuccessful());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new BulkOperationsResponse('success', 1, 1, 0, [$this->createOperationResult()]);

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals(1, $decoded['total_operations']);
        $this->assertEquals(1, $decoded['successful_operations']);
        $this->assertEquals(0, $decoded['failed_operations']);
        $this->assertCount(1, $decoded['results']);
    }

    public function testAcceptsEmptyResultsArray(): void
    {
        $response = new BulkOperationsResponse('success', 0, 0, 0, []);

        $this->assertCount(0, $response->results);
    }

    public function testMultipleOperationsWithMixedResults(): void
    {
        $response = new BulkOperationsResponse(
            'partial',
            3,
            2,
            1,
            [
                $this->createOperationResult('success', 100, 0),
                $this->createOperationResult('success', 50, 0),
                $this->createOperationResult('failed', 20, 20),
            ]
        );

        $this->assertEquals(3, $response->totalOperations);
        $this->assertEquals(2, $response->successfulOperations);
        $this->assertEquals(1, $response->failedOperations);
        $this->assertTrue($response->hasFailures());
        $this->assertCount(1, $response->getFailedResults());
    }
}
