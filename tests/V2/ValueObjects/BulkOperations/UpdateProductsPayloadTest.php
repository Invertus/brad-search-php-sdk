<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\UpdateProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class UpdateProductsPayloadTest extends TestCase
{
    public function testConstructorWithPartialFields(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99],
            ['id' => '456', 'name' => 'Updated Product'],
        ]);

        $this->assertCount(2, $payload->updates);
        $this->assertEquals('123', $payload->updates[0]['id']);
        $this->assertEquals(29.99, $payload->updates[0]['price']);
    }

    public function testConstructorWithSingleUpdate(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => 'prod-123', 'stock' => 50],
        ]);

        $this->assertCount(1, $payload->updates);
        $this->assertEquals('prod-123', $payload->updates[0]['id']);
    }

    public function testExtendsValueObject(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99],
        ]);

        $this->assertInstanceOf(ValueObject::class, $payload);
    }

    public function testImplementsJsonSerializable(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99],
        ]);

        $this->assertInstanceOf(JsonSerializable::class, $payload);
    }

    public function testJsonSerialize(): void
    {
        $updates = [
            ['id' => '123', 'price' => 29.99],
            ['id' => '456', 'stock' => 100],
        ];

        $payload = new UpdateProductsPayload($updates);

        $expected = [
            'updates' => $updates,
        ];

        $this->assertEquals($expected, $payload->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99],
        ]);

        $this->assertEquals($payload->jsonSerialize(), $payload->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99],
            ['id' => '456', 'stock' => 50],
        ]);

        $json = json_encode($payload);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('updates', $decoded);
        $this->assertCount(2, $decoded['updates']);
    }

    public function testWithUpdatesReturnsNewInstance(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'price' => 19.99],
        ]);

        $newPayload = $payload->withUpdates([
            ['id' => '2', 'price' => 29.99],
            ['id' => '3', 'price' => 39.99],
        ]);

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->updates);
        $this->assertCount(2, $newPayload->updates);
    }

    public function testWithAddedUpdateReturnsNewInstance(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'price' => 19.99],
        ]);

        $newPayload = $payload->withAddedUpdate(['id' => '2', 'stock' => 50]);

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->updates);
        $this->assertCount(2, $newPayload->updates);
        $this->assertEquals('2', $newPayload->updates[1]['id']);
    }

    public function testThrowsExceptionWhenUpdatesArrayIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one update is required in the payload.');

        new UpdateProductsPayload([]);
    }

    public function testThrowsExceptionWhenUpdateMissingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a non-empty "id" field.');

        new UpdateProductsPayload([
            ['price' => 29.99],  // Missing 'id'
        ]);
    }

    public function testThrowsExceptionWhenUpdateIdIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a non-empty "id" field.');

        new UpdateProductsPayload([
            ['id' => '', 'price' => 29.99],
        ]);
    }

    public function testThrowsExceptionWhenUpdateIdIsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a non-empty "id" field.');

        new UpdateProductsPayload([
            ['id' => null, 'price' => 29.99],
        ]);
    }

    public function testThrowsExceptionWhenUpdateIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must be an array.');

        new UpdateProductsPayload(['not-an-array']);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new UpdateProductsPayload([]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('updates', $e->argumentName);
        }
    }

    public function testAcceptsFlexibleFieldsInUpdates(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'price' => 29.99, 'custom_field' => 'value', 'nested' => ['data' => true]],
        ]);

        $this->assertCount(1, $payload->updates);
        $this->assertEquals('value', $payload->updates[0]['custom_field']);
        $this->assertEquals(['data' => true], $payload->updates[0]['nested']);
    }

    public function testOnlyIdIsRequiredOtherFieldsOptional(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123'],  // Only id, no other fields
        ]);

        $this->assertCount(1, $payload->updates);
        $this->assertEquals('123', $payload->updates[0]['id']);
    }

    public function testWithUpdatesValidatesItems(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'price' => 19.99],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one update is required in the payload.');

        $payload->withUpdates([]);
    }

    public function testWithAddedUpdateValidatesMissingId(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'price' => 19.99],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 1 must contain a non-empty "id" field.');

        $payload->withAddedUpdate(['price' => 29.99]);
    }
}
