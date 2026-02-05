<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\DeleteProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class DeleteProductsPayloadTest extends TestCase
{
    public function testConstructorWithProductIds(): void
    {
        $payload = new DeleteProductsPayload(['prod-123']);

        $this->assertCount(1, $payload->productIds);
        $this->assertEquals('prod-123', $payload->productIds[0]);
    }

    public function testConstructorWithMultipleProductIds(): void
    {
        $payload = new DeleteProductsPayload(['prod-1', 'prod-2', 'prod-3']);

        $this->assertCount(3, $payload->productIds);
    }

    public function testExtendsValueObject(): void
    {
        $payload = new DeleteProductsPayload(['prod-123']);

        $this->assertInstanceOf(ValueObject::class, $payload);
    }

    public function testImplementsJsonSerializable(): void
    {
        $payload = new DeleteProductsPayload(['prod-123']);

        $this->assertInstanceOf(JsonSerializable::class, $payload);
    }

    public function testJsonSerialize(): void
    {
        $payload = new DeleteProductsPayload(['prod-123', 'prod-456']);

        $expected = [
            'product_ids' => ['prod-123', 'prod-456'],
        ];

        $this->assertEquals($expected, $payload->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $payload = new DeleteProductsPayload(['prod-123']);

        $this->assertEquals($payload->jsonSerialize(), $payload->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $payload = new DeleteProductsPayload(['prod-123', 'prod-456']);

        $json = json_encode($payload);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('product_ids', $decoded);
        $this->assertCount(2, $decoded['product_ids']);
    }

    public function testWithProductIdsReturnsNewInstance(): void
    {
        $payload = new DeleteProductsPayload(['prod-1']);
        $newPayload = $payload->withProductIds(['prod-2', 'prod-3']);

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->productIds);
        $this->assertCount(2, $newPayload->productIds);
        $this->assertEquals('prod-1', $payload->productIds[0]);
        $this->assertEquals('prod-2', $newPayload->productIds[0]);
    }

    public function testWithAddedProductIdReturnsNewInstance(): void
    {
        $payload = new DeleteProductsPayload(['prod-1']);
        $newPayload = $payload->withAddedProductId('prod-2');

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->productIds);
        $this->assertCount(2, $newPayload->productIds);
        $this->assertEquals('prod-2', $newPayload->productIds[1]);
    }

    public function testThrowsExceptionForEmptyProductIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one product ID is required in the payload.');

        new DeleteProductsPayload([]);
    }

    public function testThrowsExceptionForNonStringProductId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID at index 0 must be a string.');

        new DeleteProductsPayload([123]);
    }

    public function testThrowsExceptionForEmptyStringProductId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID at index 0 cannot be empty.');

        new DeleteProductsPayload(['']);
    }

    public function testThrowsExceptionForMixedArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID at index 1 must be a string.');

        new DeleteProductsPayload(['prod-1', 123]);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new DeleteProductsPayload([]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('productIds', $e->argumentName);
        }
    }

    public function testWithProductIdsValidatesItems(): void
    {
        $payload = new DeleteProductsPayload(['prod-1']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one product ID is required in the payload.');

        $payload->withProductIds([]);
    }

    public function testWithAddedProductIdValidatesEmptyString(): void
    {
        $payload = new DeleteProductsPayload(['prod-1']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID at index 1 cannot be empty.');

        $payload->withAddedProductId('');
    }
}
