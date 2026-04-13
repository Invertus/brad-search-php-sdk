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
    public function testConstructorWithStructuredUpdates(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'fields' => ['price' => 29.99]],
            ['id' => '456', 'fields' => ['name' => 'Updated Product']],
        ]);

        $this->assertCount(2, $payload->updates);
        $this->assertEquals('123', $payload->updates[0]['id']);
        $this->assertEquals(['price' => 29.99], $payload->updates[0]['fields']);
    }

    public function testConstructorWithUpsertData(): void
    {
        $payload = new UpdateProductsPayload([
            [
                'id' => '123',
                'fields' => ['price' => 29.99],
                'upsert' => ['id' => '123', 'name' => 'Full Product', 'price' => 29.99],
            ],
        ]);

        $this->assertCount(1, $payload->updates);
        $this->assertEquals('Full Product', $payload->updates[0]['upsert']['name']);
    }

    public function testExtendsValueObject(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'fields' => ['price' => 29.99]],
        ]);

        $this->assertInstanceOf(ValueObject::class, $payload);
    }

    public function testImplementsJsonSerializable(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'fields' => ['price' => 29.99]],
        ]);

        $this->assertInstanceOf(JsonSerializable::class, $payload);
    }

    public function testJsonSerialize(): void
    {
        $updates = [
            ['id' => '123', 'fields' => ['price' => 29.99]],
            ['id' => '456', 'fields' => ['stock' => 100]],
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
            ['id' => '123', 'fields' => ['price' => 29.99]],
        ]);

        $this->assertEquals($payload->jsonSerialize(), $payload->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '123', 'fields' => ['price' => 29.99]],
            ['id' => '456', 'fields' => ['stock' => 50], 'upsert' => ['id' => '456', 'name' => 'Product']],
        ]);

        $json = json_encode($payload);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('updates', $decoded);
        $this->assertCount(2, $decoded['updates']);
        $this->assertArrayHasKey('fields', $decoded['updates'][0]);
        $this->assertArrayHasKey('upsert', $decoded['updates'][1]);
    }

    public function testWithUpdatesReturnsNewInstance(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'fields' => ['price' => 19.99]],
        ]);

        $newPayload = $payload->withUpdates([
            ['id' => '2', 'fields' => ['price' => 29.99]],
            ['id' => '3', 'fields' => ['price' => 39.99]],
        ]);

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->updates);
        $this->assertCount(2, $newPayload->updates);
    }

    public function testWithAddedUpdateReturnsNewInstance(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'fields' => ['price' => 19.99]],
        ]);

        $newPayload = $payload->withAddedUpdate(['id' => '2', 'fields' => ['stock' => 50]]);

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
            ['fields' => ['price' => 29.99]],
        ]);
    }

    public function testThrowsExceptionWhenUpdateIdIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a non-empty "id" field.');

        new UpdateProductsPayload([
            ['id' => '', 'fields' => ['price' => 29.99]],
        ]);
    }

    public function testThrowsExceptionWhenUpdateIdIsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a non-empty "id" field.');

        new UpdateProductsPayload([
            ['id' => null, 'fields' => ['price' => 29.99]],
        ]);
    }

    public function testThrowsExceptionWhenUpdateIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must be an array.');

        new UpdateProductsPayload(['not-an-array']);
    }

    public function testThrowsExceptionWhenFieldsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a "fields" array');

        new UpdateProductsPayload([
            ['id' => '123'],
        ]);
    }

    public function testThrowsExceptionWhenFieldsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 0 must contain a "fields" array');

        new UpdateProductsPayload([
            ['id' => '123', 'fields' => 'not-an-array'],
        ]);
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

    public function testWithUpdatesValidatesItems(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'fields' => ['price' => 19.99]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one update is required in the payload.');

        $payload->withUpdates([]);
    }

    public function testWithAddedUpdateValidatesMissingId(): void
    {
        $payload = new UpdateProductsPayload([
            ['id' => '1', 'fields' => ['price' => 19.99]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update at index 1 must contain a non-empty "id" field.');

        $payload->withAddedUpdate(['fields' => ['price' => 29.99]]);
    }
}
