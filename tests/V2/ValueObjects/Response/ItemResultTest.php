<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Response\ItemResult;
use PHPUnit\Framework\TestCase;

final class ItemResultTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $result = new ItemResult(
            id: 'prod-123',
            operation: BulkOperationType::INDEX_PRODUCTS,
            status: 'created'
        );

        $this->assertEquals('prod-123', $result->id);
        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $result->operation);
        $this->assertEquals('created', $result->status);
        $this->assertNull($result->error);
    }

    public function testConstructorWithError(): void
    {
        $result = new ItemResult(
            id: 'prod-456',
            operation: BulkOperationType::INDEX_PRODUCTS,
            status: 'error',
            error: 'Invalid price'
        );

        $this->assertEquals('prod-456', $result->id);
        $this->assertEquals('error', $result->status);
        $this->assertEquals('Invalid price', $result->error);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'id' => 'prod-789',
            'operation' => 'index_products',
            'status' => 'created',
        ];

        $result = ItemResult::fromArray($data);

        $this->assertEquals('prod-789', $result->id);
        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $result->operation);
        $this->assertEquals('created', $result->status);
        $this->assertNull($result->error);
    }

    public function testFromArrayWithError(): void
    {
        $data = [
            'id' => 'prod-999',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'Document not found',
        ];

        $result = ItemResult::fromArray($data);

        $this->assertEquals('prod-999', $result->id);
        $this->assertEquals('error', $result->status);
        $this->assertEquals('Document not found', $result->error);
    }

    public function testFromArrayThrowsOnMissingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: id');

        ItemResult::fromArray([
            'operation' => 'index_products',
            'status' => 'created',
        ]);
    }

    public function testFromArrayThrowsOnMissingOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: operation');

        ItemResult::fromArray([
            'id' => 'prod-123',
            'status' => 'created',
        ]);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        ItemResult::fromArray([
            'id' => 'prod-123',
            'operation' => 'index_products',
        ]);
    }

    public function testIsSuccessful(): void
    {
        $success = new ItemResult('prod-1', BulkOperationType::INDEX_PRODUCTS, 'created');
        $this->assertTrue($success->isSuccessful());

        $error = new ItemResult('prod-2', BulkOperationType::INDEX_PRODUCTS, 'error', 'Failed');
        $this->assertFalse($error->isSuccessful());
    }

    public function testHasError(): void
    {
        $success = new ItemResult('prod-1', BulkOperationType::INDEX_PRODUCTS, 'created');
        $this->assertFalse($success->hasError());

        $error = new ItemResult('prod-2', BulkOperationType::INDEX_PRODUCTS, 'error', 'Failed');
        $this->assertTrue($error->hasError());
    }

    public function testJsonSerialize(): void
    {
        $result = new ItemResult('prod-123', BulkOperationType::INDEX_PRODUCTS, 'created');

        $expected = [
            'id' => 'prod-123',
            'operation' => 'index_products',
            'status' => 'created',
        ];

        $this->assertEquals($expected, $result->jsonSerialize());
    }

    public function testJsonSerializeWithError(): void
    {
        $result = new ItemResult('prod-456', BulkOperationType::INDEX_PRODUCTS, 'error', 'Invalid data');

        $expected = [
            'id' => 'prod-456',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'Invalid data',
        ];

        $this->assertEquals($expected, $result->jsonSerialize());
    }

    public function testJsonEncode(): void
    {
        $result = new ItemResult('prod-789', BulkOperationType::INDEX_PRODUCTS, 'created');
        $json = json_encode($result);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('prod-789', $decoded['id']);
        $this->assertEquals('index_products', $decoded['operation']);
        $this->assertEquals('created', $decoded['status']);
    }

    public function testThrowsOnEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('id cannot be empty');

        new ItemResult('', BulkOperationType::INDEX_PRODUCTS, 'created');
    }

    public function testThrowsOnEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new ItemResult('prod-123', BulkOperationType::INDEX_PRODUCTS, '');
    }
}
