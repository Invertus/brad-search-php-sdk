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
            activeVersion: 2,
            activeIndex: 'products_v2',
            allVersions: $versions
        );

        $this->assertEquals('products', $response->aliasName);
        $this->assertEquals(2, $response->activeVersion);
        $this->assertEquals('products_v2', $response->activeIndex);
        $this->assertCount(2, $response->allVersions);
    }

    public function testExtendsValueObject(): void
    {
        $response = new IndexInfoResponse(
            'test',
            1,
            'test_v1',
            [$this->createIndexVersion(1, true)]
        );

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new IndexInfoResponse(
            'test',
            1,
            'test_v1',
            [$this->createIndexVersion(1, true)]
        );

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'alias_name' => 'products',
            'active_version' => 2,
            'active_index' => 'products_v2',
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
        $this->assertEquals(2, $response->activeVersion);
        $this->assertEquals('products_v2', $response->activeIndex);
        $this->assertCount(2, $response->allVersions);
        $this->assertInstanceOf(IndexVersion::class, $response->allVersions[0]);
        $this->assertInstanceOf(IndexVersion::class, $response->allVersions[1]);
    }

    public function testFromArrayThrowsOnMissingAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: alias_name');

        IndexInfoResponse::fromArray([
            'active_version' => 1,
            'active_index' => 'test_v1',
            'all_versions' => [],
        ]);
    }

    public function testFromArrayThrowsOnMissingActiveVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: active_version');

        IndexInfoResponse::fromArray([
            'alias_name' => 'test',
            'active_index' => 'test_v1',
            'all_versions' => [],
        ]);
    }

    public function testRejectsEmptyAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alias_name cannot be empty');

        new IndexInfoResponse('', 1, 'test_v1', []);
    }

    public function testRejectsNegativeActiveVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('active_version must be non-negative');

        new IndexInfoResponse('test', -1, 'test_v1', []);
    }

    public function testRejectsNonIndexVersionInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version at index 1 must be an instance of IndexVersion');

        new IndexInfoResponse('test', 1, 'test_v1', [
            $this->createIndexVersion(1),
            'not an IndexVersion',
        ]);
    }

    public function testGetVersionReturnsCorrectVersion(): void
    {
        $version1 = $this->createIndexVersion(1);
        $version2 = $this->createIndexVersion(2, true);

        $response = new IndexInfoResponse('products', 2, 'products_v2', [$version1, $version2]);

        $this->assertSame($version1, $response->getVersion(1));
        $this->assertSame($version2, $response->getVersion(2));
        $this->assertNull($response->getVersion(3));
    }

    public function testGetActiveVersionObjectReturnsActiveVersion(): void
    {
        $version1 = $this->createIndexVersion(1);
        $version2 = $this->createIndexVersion(2, true);

        $response = new IndexInfoResponse('products', 2, 'products_v2', [$version1, $version2]);

        $activeVersion = $response->getActiveVersionObject();

        $this->assertSame($version2, $activeVersion);
    }

    public function testGetActiveVersionObjectReturnsNullIfNotFound(): void
    {
        $version1 = $this->createIndexVersion(1, true);

        $response = new IndexInfoResponse('products', 5, 'products_v5', [$version1]);

        $this->assertNull($response->getActiveVersionObject());
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $version = $this->createIndexVersion(1, true);
        $response = new IndexInfoResponse('products', 1, 'products_v1', [$version]);

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('alias_name', $serialized);
        $this->assertArrayHasKey('active_version', $serialized);
        $this->assertArrayHasKey('active_index', $serialized);
        $this->assertArrayHasKey('all_versions', $serialized);
        $this->assertEquals('products', $serialized['alias_name']);
        $this->assertEquals(1, $serialized['active_version']);
        $this->assertEquals('products_v1', $serialized['active_index']);
        $this->assertCount(1, $serialized['all_versions']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new IndexInfoResponse('test', 1, 'test_v1', [$this->createIndexVersion(1, true)]);

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'alias_name' => 'app_12345_products',
            'active_version' => 2,
            'active_index' => 'app_12345_products_v2',
            'all_versions' => [
                [
                    'version' => 1,
                    'index_name' => 'app_12345_products_v1',
                    'document_count' => 10000,
                    'created_at' => '2024-01-10T10:00:00Z',
                    'is_active' => false,
                ],
                [
                    'version' => 2,
                    'index_name' => 'app_12345_products_v2',
                    'document_count' => 12000,
                    'created_at' => '2024-01-15T14:30:00Z',
                    'is_active' => true,
                ],
            ],
        ];

        $response = IndexInfoResponse::fromArray($apiResponse);

        $this->assertEquals('app_12345_products', $response->aliasName);
        $this->assertEquals(2, $response->activeVersion);
        $this->assertEquals('app_12345_products_v2', $response->activeIndex);
        $this->assertCount(2, $response->allVersions);

        $activeVersionObj = $response->getActiveVersionObject();
        $this->assertNotNull($activeVersionObj);
        $this->assertEquals(12000, $activeVersionObj->documentCount);
        $this->assertTrue($activeVersionObj->isActive);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new IndexInfoResponse('test', 1, 'test_v1', [$this->createIndexVersion(1, true)]);

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('test', $decoded['alias_name']);
        $this->assertEquals(1, $decoded['active_version']);
        $this->assertEquals('test_v1', $decoded['active_index']);
        $this->assertCount(1, $decoded['all_versions']);
    }

    public function testAcceptsEmptyVersionsArray(): void
    {
        $response = new IndexInfoResponse('test', 0, 'test_v0', []);

        $this->assertCount(0, $response->allVersions);
    }
}
