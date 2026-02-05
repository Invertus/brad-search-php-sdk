<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexInfoResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexVersion;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class IndexInfoResponseTest extends TestCase
{
    private function createIndexVersion(int $version, bool $isActive = false): IndexVersion
    {
        return new IndexVersion(
            $version,
            "products_v{$version}",
            1000 * $version,
            '2024-01-15T10:30:00Z',
            $isActive
        );
    }

    public function testConstructorWithValidValues(): void
    {
        $versions = [
            $this->createIndexVersion(1),
            $this->createIndexVersion(2, true),
        ];

        $response = new IndexInfoResponse(
            aliasName: 'products',
            physicalIndexName: 'products-v2',
            currentVersion: 'v2',
            documentCount: 2000,
            sizeInBytes: 1024000,
            fieldCount: 25,
            allVersions: $versions
        );

        $this->assertEquals('products', $response->aliasName);
        $this->assertEquals('products-v2', $response->physicalIndexName);
        $this->assertEquals('v2', $response->currentVersion);
        $this->assertEquals(2, $response->getActiveVersionNumber());
        $this->assertEquals(2000, $response->documentCount);
        $this->assertEquals(1024000, $response->sizeInBytes);
        $this->assertEquals(25, $response->fieldCount);
        $this->assertCount(2, $response->allVersions);
    }

    public function testExtendsValueObject(): void
    {
        $response = new IndexInfoResponse(
            'test',
            'test-v1',
            'v1',
            1000,
            512000,
            10,
            [$this->createIndexVersion(1, true)]
        );

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new IndexInfoResponse(
            'test',
            'test-v1',
            'v1',
            1000,
            512000,
            10,
            [$this->createIndexVersion(1, true)]
        );

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'alias_name' => 'products',
            'physical_index_name' => 'products-v2',
            'current_version' => 'v2',
            'document_count' => 2000,
            'size_in_bytes' => 1024000,
            'field_count' => 25,
            'all_versions' => [
                [
                    'version' => 1,
                    'index_name' => 'products_v1',
                    'document_count' => 1000,
                    'created_at' => '2024-01-10T10:00:00Z',
                    'is_active' => false,
                ],
                [
                    'version' => 2,
                    'index_name' => 'products_v2',
                    'document_count' => 2000,
                    'created_at' => '2024-01-15T10:00:00Z',
                    'is_active' => true,
                ],
            ],
        ];

        $response = IndexInfoResponse::fromArray($data);

        $this->assertEquals('products', $response->aliasName);
        $this->assertEquals('products-v2', $response->physicalIndexName);
        $this->assertEquals('v2', $response->currentVersion);
        $this->assertEquals(2, $response->getActiveVersionNumber());
        $this->assertEquals(2000, $response->documentCount);
        $this->assertEquals(1024000, $response->sizeInBytes);
        $this->assertEquals(25, $response->fieldCount);
        $this->assertCount(2, $response->allVersions);
        $this->assertInstanceOf(IndexVersion::class, $response->allVersions[0]);
        $this->assertInstanceOf(IndexVersion::class, $response->allVersions[1]);
    }

    public function testFromArrayThrowsOnMissingAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: alias_name');

        IndexInfoResponse::fromArray([
            'physical_index_name' => 'test-v1',
            'current_version' => 'v1',
            'document_count' => 1000,
            'size_in_bytes' => 512000,
            'field_count' => 10,
        ]);
    }

    public function testFromArrayThrowsOnMissingActiveVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: current_version');

        IndexInfoResponse::fromArray([
            'alias_name' => 'test',
            'physical_index_name' => 'test-v1',
            'document_count' => 1000,
            'size_in_bytes' => 512000,
            'field_count' => 10,
        ]);
    }

    public function testRejectsEmptyAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alias_name cannot be empty');

        new IndexInfoResponse('', 'test-v1', 'v1', 1000, 512000, 10, []);
    }

    public function testRejectsNegativeActiveVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('document_count must be non-negative');

        new IndexInfoResponse('test', 'test-v1', 'v1', -1, 512000, 10, []);
    }

    public function testRejectsNonIndexVersionInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version at index 1 must be an instance of IndexVersion');

        new IndexInfoResponse('test', 'test-v1', 'v1', 1000, 512000, 10, [
            $this->createIndexVersion(1),
            'not an IndexVersion',
        ]);
    }

    public function testGetVersionReturnsCorrectVersion(): void
    {
        $version1 = $this->createIndexVersion(1);
        $version2 = $this->createIndexVersion(2, true);

        $response = new IndexInfoResponse('products', 'products-v2', 'v2', 2000, 1024000, 25, [$version1, $version2]);

        $this->assertSame($version1, $response->getVersion(1));
        $this->assertSame($version2, $response->getVersion(2));
        $this->assertNull($response->getVersion(3));
    }

    public function testGetActiveVersionObjectReturnsActiveVersion(): void
    {
        $version1 = $this->createIndexVersion(1);
        $version2 = $this->createIndexVersion(2, true);

        $response = new IndexInfoResponse('products', 'products-v2', 'v2', 2000, 1024000, 25, [$version1, $version2]);

        $activeVersion = $response->getActiveVersionObject();

        $this->assertSame($version2, $activeVersion);
    }

    public function testGetActiveVersionObjectReturnsNullIfNotFound(): void
    {
        $version1 = $this->createIndexVersion(1, true);

        $response = new IndexInfoResponse('products', 'products-v5', 'v5', 5000, 2048000, 30, [$version1]);

        $this->assertNull($response->getActiveVersionObject());
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $version = $this->createIndexVersion(1, true);
        $response = new IndexInfoResponse('products', 'products-v1', 'v1', 1000, 512000, 20, [$version]);

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('alias_name', $serialized);
        $this->assertArrayHasKey('physical_index_name', $serialized);
        $this->assertArrayHasKey('current_version', $serialized);
        $this->assertArrayHasKey('document_count', $serialized);
        $this->assertArrayHasKey('size_in_bytes', $serialized);
        $this->assertArrayHasKey('field_count', $serialized);
        $this->assertArrayHasKey('all_versions', $serialized);
        $this->assertEquals('products', $serialized['alias_name']);
        $this->assertEquals('products-v1', $serialized['physical_index_name']);
        $this->assertEquals('v1', $serialized['current_version']);
        $this->assertEquals(1000, $serialized['document_count']);
        $this->assertEquals(512000, $serialized['size_in_bytes']);
        $this->assertEquals(20, $serialized['field_count']);
        $this->assertCount(1, $serialized['all_versions']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new IndexInfoResponse('test', 'test-v1', 'v1', 1000, 512000, 10, [$this->createIndexVersion(1, true)]);

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of actual Go API response format.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        // This matches the actual Go API IndexInfoResponse struct
        $apiResponse = [
            'alias_name' => '76e15034-c0dc-4660-920d-a0e49e145b12',
            'physical_index_name' => '76e15034-c0dc-4660-920d-a0e49e145b12-v15',
            'current_version' => 'v15',
            'document_count' => 1500,
            'size_in_bytes' => 1024000,
            'field_count' => 25,
        ];

        $response = IndexInfoResponse::fromArray($apiResponse);

        $this->assertEquals('76e15034-c0dc-4660-920d-a0e49e145b12', $response->aliasName);
        $this->assertEquals('76e15034-c0dc-4660-920d-a0e49e145b12-v15', $response->physicalIndexName);
        $this->assertEquals('v15', $response->currentVersion);
        $this->assertEquals(15, $response->getActiveVersionNumber());
        $this->assertEquals(1500, $response->documentCount);
        $this->assertEquals(1024000, $response->sizeInBytes);
        $this->assertEquals(25, $response->fieldCount);
        $this->assertCount(0, $response->allVersions); // API doesn't return this field
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new IndexInfoResponse('test', 'test-v1', 'v1', 1000, 512000, 10, [$this->createIndexVersion(1, true)]);

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('test', $decoded['alias_name']);
        $this->assertEquals('test-v1', $decoded['physical_index_name']);
        $this->assertEquals('v1', $decoded['current_version']);
        $this->assertEquals(1000, $decoded['document_count']);
        $this->assertEquals(512000, $decoded['size_in_bytes']);
        $this->assertEquals(10, $decoded['field_count']);
        $this->assertCount(1, $decoded['all_versions']);
    }

    public function testAcceptsEmptyVersionsArray(): void
    {
        $response = new IndexInfoResponse('test', 'test-v0', 'v0', 0, 0, 0, []);

        $this->assertCount(0, $response->allVersions);
    }

    public function testGetActiveVersionNumberParsesVersionString(): void
    {
        $response = new IndexInfoResponse('test', 'test-v15', 'v15', 1000, 512000, 10, []);
        $this->assertEquals(15, $response->getActiveVersionNumber());

        $response2 = new IndexInfoResponse('test', 'test-v1', 'v1', 1000, 512000, 10, []);
        $this->assertEquals(1, $response2->getActiveVersionNumber());

        $response3 = new IndexInfoResponse('test', 'test-v123', 'v123', 1000, 512000, 10, []);
        $this->assertEquals(123, $response3->getActiveVersionNumber());
    }
}
