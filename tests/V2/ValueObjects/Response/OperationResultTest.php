<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Response\OperationResult;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class OperationResultTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $result = new OperationResult(
            operationType: BulkOperationType::INDEX_PRODUCTS,
            status: 'success',
            itemsProcessed: 100,
            itemsFailed: 0
        );

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $result->operationType);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(100, $result->itemsProcessed);
        $this->assertEquals(0, $result->itemsFailed);
        $this->assertNull($result->errors);
    }

    public function testConstructorWithErrors(): void
    {
        $errors = [
            ['id' => 'prod_1', 'message' => 'Invalid product ID'],
            ['id' => 'prod_2', 'message' => 'Missing required field'],
        ];

        $result = new OperationResult(
            operationType: BulkOperationType::INDEX_PRODUCTS,
            status: 'partial',
            itemsProcessed: 100,
            itemsFailed: 2,
            errors: $errors
        );

        $this->assertEquals($errors, $result->errors);
    }

    public function testExtendsValueObject(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 10, 0);

        $this->assertInstanceOf(ValueObject::class, $result);
    }

    public function testImplementsJsonSerializable(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 10, 0);

        $this->assertInstanceOf(JsonSerializable::class, $result);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'type' => 'index_products',
            'status' => 'success',
            'items_processed' => 50,
            'items_failed' => 0,
        ];

        $result = OperationResult::fromArray($data);

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $result->operationType);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(50, $result->itemsProcessed);
        $this->assertEquals(0, $result->itemsFailed);
    }

    public function testFromArrayWithErrors(): void
    {
        $errors = [['id' => 'test', 'error' => 'Failed']];
        $data = [
            'type' => 'index_products',
            'status' => 'partial',
            'items_processed' => 10,
            'items_failed' => 1,
            'errors' => $errors,
        ];

        $result = OperationResult::fromArray($data);

        $this->assertEquals($errors, $result->errors);
    }

    public function testFromArrayThrowsOnMissingOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: type');

        OperationResult::fromArray([
            'status' => 'success',
            'items_processed' => 10,
            'items_failed' => 0,
        ]);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        OperationResult::fromArray([
            'type' => 'index_products',
            'items_processed' => 10,
            'items_failed' => 0,
        ]);
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new OperationResult(BulkOperationType::INDEX_PRODUCTS, '', 10, 0);
    }

    public function testRejectsNegativeItemsProcessed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('items_processed must be non-negative');

        new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', -10, 0);
    }

    public function testRejectsNegativeItemsFailed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('items_failed must be non-negative');

        new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 10, -5);
    }

    public function testIsSuccessfulReturnsTrueForSuccess(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForFailures(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 5);

        $this->assertFalse($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForNonSuccessStatus(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'partial', 100, 0);

        $this->assertFalse($result->isSuccessful());
    }

    public function testHasFailuresReturnsTrue(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'partial', 100, 5);

        $this->assertTrue($result->hasFailures());
    }

    public function testHasFailuresReturnsFalse(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $this->assertFalse($result->hasFailures());
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $expected = [
            'type' => 'index_products',
            'status' => 'success',
            'items_processed' => 100,
            'items_failed' => 0,
        ];

        $this->assertEquals($expected, $result->jsonSerialize());
    }

    public function testJsonSerializeIncludesErrors(): void
    {
        $errors = [['id' => 'test', 'error' => 'Failed']];
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'partial', 10, 1, $errors);

        $serialized = $result->jsonSerialize();

        $this->assertArrayHasKey('errors', $serialized);
        $this->assertEquals($errors, $serialized['errors']);
    }

    public function testJsonSerializeExcludesNullErrors(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $serialized = $result->jsonSerialize();

        $this->assertArrayNotHasKey('errors', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $this->assertEquals($result->jsonSerialize(), $result->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'type' => 'index_products',
            'status' => 'success',
            'items_processed' => 150,
            'items_failed' => 0,
        ];

        $result = OperationResult::fromArray($apiResponse);

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $result->operationType);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(150, $result->itemsProcessed);
        $this->assertEquals(0, $result->itemsFailed);
        $this->assertTrue($result->isSuccessful());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 100, 0);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertEquals('index_products', $decoded['type']);
        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals(100, $decoded['items_processed']);
        $this->assertEquals(0, $decoded['items_failed']);
    }

    public function testAcceptsZeroItemsProcessed(): void
    {
        $result = new OperationResult(BulkOperationType::INDEX_PRODUCTS, 'success', 0, 0);

        $this->assertEquals(0, $result->itemsProcessed);
    }
}
