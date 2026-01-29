<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexVersion;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class IndexVersionTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $response = new IndexVersion(
            version: 1,
            indexName: 'products_v1',
            documentCount: 1000,
            createdAt: '2024-01-15T10:30:00Z',
            isActive: true
        );

        $this->assertEquals(1, $response->version);
        $this->assertEquals('products_v1', $response->indexName);
        $this->assertEquals(1000, $response->documentCount);
        $this->assertEquals('2024-01-15T10:30:00Z', $response->createdAt);
        $this->assertTrue($response->isActive);
    }

    public function testExtendsValueObject(): void
    {
        $response = new IndexVersion(1, 'test_v1', 100, '2024-01-15T10:30:00Z', false);

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new IndexVersion(1, 'test_v1', 100, '2024-01-15T10:30:00Z', false);

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'version' => 2,
            'index_name' => 'app_products_v2',
            'document_count' => 5000,
            'created_at' => '2024-02-20T14:00:00Z',
            'is_active' => true,
        ];

        $response = IndexVersion::fromArray($data);

        $this->assertEquals(2, $response->version);
        $this->assertEquals('app_products_v2', $response->indexName);
        $this->assertEquals(5000, $response->documentCount);
        $this->assertEquals('2024-02-20T14:00:00Z', $response->createdAt);
        $this->assertTrue($response->isActive);
    }

    public function testFromArrayThrowsOnMissingVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: version');

        IndexVersion::fromArray([
            'index_name' => 'test_v1',
            'document_count' => 100,
            'created_at' => '2024-01-15T10:30:00Z',
            'is_active' => false,
        ]);
    }

    public function testFromArrayThrowsOnMissingIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: index_name');

        IndexVersion::fromArray([
            'version' => 1,
            'document_count' => 100,
            'created_at' => '2024-01-15T10:30:00Z',
            'is_active' => false,
        ]);
    }

    public function testRejectsEmptyIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('index_name cannot be empty');

        new IndexVersion(1, '', 100, '2024-01-15T10:30:00Z', false);
    }

    public function testRejectsNegativeVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('version must be non-negative');

        new IndexVersion(-1, 'test_v1', 100, '2024-01-15T10:30:00Z', false);
    }

    public function testRejectsNegativeDocumentCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('document_count must be non-negative');

        new IndexVersion(1, 'test_v1', -100, '2024-01-15T10:30:00Z', false);
    }

    public function testRejectsEmptyCreatedAt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('created_at cannot be empty');

        new IndexVersion(1, 'test_v1', 100, '', false);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new IndexVersion(1, 'products_v1', 1000, '2024-01-15T10:30:00Z', true);

        $expected = [
            'version' => 1,
            'index_name' => 'products_v1',
            'document_count' => 1000,
            'created_at' => '2024-01-15T10:30:00Z',
            'is_active' => true,
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new IndexVersion(1, 'test_v1', 100, '2024-01-15T10:30:00Z', false);

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new IndexVersion(1, 'test_v1', 100, '2024-01-15T10:30:00Z', true);

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals(1, $decoded['version']);
        $this->assertEquals('test_v1', $decoded['index_name']);
        $this->assertEquals(100, $decoded['document_count']);
        $this->assertEquals('2024-01-15T10:30:00Z', $decoded['created_at']);
        $this->assertTrue($decoded['is_active']);
    }

    public function testAcceptsVersionZero(): void
    {
        $response = new IndexVersion(0, 'test_v0', 100, '2024-01-15T10:30:00Z', false);

        $this->assertEquals(0, $response->version);
    }

    public function testAcceptsZeroDocumentCount(): void
    {
        $response = new IndexVersion(1, 'test_v1', 0, '2024-01-15T10:30:00Z', false);

        $this->assertEquals(0, $response->documentCount);
    }

    public function testInactiveVersion(): void
    {
        $response = new IndexVersion(1, 'test_v1', 100, '2024-01-15T10:30:00Z', false);

        $this->assertFalse($response->isActive);
    }
}
