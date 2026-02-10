<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexCreationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class IndexCreationResponseTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $response = new IndexCreationResponse(
            status: 'success',
            physicalIndexName: 'test_index_v1',
            aliasName: 'test_index',
            version: 1,
            fieldsCreated: 10,
            message: 'Index created successfully'
        );

        $this->assertEquals('success', $response->status);
        $this->assertEquals('test_index_v1', $response->physicalIndexName);
        $this->assertEquals('test_index', $response->aliasName);
        $this->assertEquals(1, $response->version);
        $this->assertEquals(10, $response->fieldsCreated);
        $this->assertEquals('Index created successfully', $response->message);
    }

    public function testExtendsValueObject(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_index_v1',
            'test_index',
            1,
            10,
            'Index created successfully'
        );

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_index_v1',
            'test_index',
            1,
            10,
            'Index created successfully'
        );

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'status' => 'success',
            'physical_index_name' => 'products_v1',
            'alias_name' => 'products',
            'version' => 1,
            'fields_created' => 15,
            'message' => 'Index created with 15 fields',
        ];

        $response = IndexCreationResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertEquals('products_v1', $response->physicalIndexName);
        $this->assertEquals('products', $response->aliasName);
        $this->assertEquals(1, $response->version);
        $this->assertEquals(15, $response->fieldsCreated);
        $this->assertEquals('Index created with 15 fields', $response->message);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        IndexCreationResponse::fromArray([
            'physical_index_name' => 'test_v1',
            'alias_name' => 'test',
            'version' => 1,
            'fields_created' => 10,
            'message' => 'Test',
        ]);
    }

    public function testFromArrayThrowsOnMissingPhysicalIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: physical_index_name');

        IndexCreationResponse::fromArray([
            'status' => 'success',
            'alias_name' => 'test',
            'version' => 1,
            'fields_created' => 10,
            'message' => 'Test',
        ]);
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new IndexCreationResponse(
            '',
            'test_v1',
            'test',
            1,
            10,
            'Test message'
        );
    }

    public function testRejectsEmptyPhysicalIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('physical_index_name cannot be empty');

        new IndexCreationResponse(
            'success',
            '',
            'test',
            1,
            10,
            'Test message'
        );
    }

    public function testRejectsNegativeVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('version must be non-negative');

        new IndexCreationResponse(
            'success',
            'test_v1',
            'test',
            -1,
            10,
            'Test message'
        );
    }

    public function testRejectsNegativeFieldsCreated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fields_created must be non-negative');

        new IndexCreationResponse(
            'success',
            'test_v1',
            'test',
            1,
            -5,
            'Test message'
        );
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'products_v1',
            'products',
            1,
            15,
            'Index created'
        );

        $expected = [
            'status' => 'success',
            'physical_index_name' => 'products_v1',
            'alias_name' => 'products',
            'version' => 1,
            'fields_created' => 15,
            'message' => 'Index created',
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_v1',
            'test',
            1,
            10,
            'Test'
        );

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'status' => 'success',
            'physical_index_name' => 'app_12345_products_v1',
            'alias_name' => 'app_12345_products',
            'version' => 1,
            'fields_created' => 25,
            'message' => 'Index version 1 created successfully with 25 fields',
        ];

        $response = IndexCreationResponse::fromArray($apiResponse);

        $this->assertEquals('success', $response->status);
        $this->assertEquals('app_12345_products_v1', $response->physicalIndexName);
        $this->assertEquals('app_12345_products', $response->aliasName);
        $this->assertEquals(1, $response->version);
        $this->assertEquals(25, $response->fieldsCreated);
        $this->assertStringContainsString('25 fields', $response->message);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_v1',
            'test',
            1,
            10,
            'Test message'
        );

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals('test_v1', $decoded['physical_index_name']);
        $this->assertEquals('test', $decoded['alias_name']);
        $this->assertEquals(1, $decoded['version']);
        $this->assertEquals(10, $decoded['fields_created']);
        $this->assertEquals('Test message', $decoded['message']);
    }

    public function testAcceptsVersionZero(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_v0',
            'test',
            0,
            10,
            'Test'
        );

        $this->assertEquals(0, $response->version);
    }

    public function testAcceptsZeroFieldsCreated(): void
    {
        $response = new IndexCreationResponse(
            'success',
            'test_v1',
            'test',
            1,
            0,
            'Empty index'
        );

        $this->assertEquals(0, $response->fieldsCreated);
    }
}
