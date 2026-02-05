<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\SyncV2Sdk;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\IndexProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexCreationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexInfoResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\QueryConfigurationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\SettingsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\SynonymResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Response\VersionActivateResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Synonym\SynonymConfiguration;
use PHPUnit\Framework\TestCase;

class SyncV2SdkTest extends TestCase
{
    private const APP_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const API_URL = 'https://api.bradsearch.com';
    private const TOKEN = 'test-bearer-token';

    private function createSdkWithMockedHttpClient(HttpClient $httpClientMock): SyncV2Sdk
    {
        $config = new SyncConfigV2(self::APP_ID, self::API_URL, self::TOKEN);

        return new class ($config, $httpClientMock) extends SyncV2Sdk {
            public function __construct(SyncConfigV2 $config, private HttpClient $mockedHttpClient)
            {
                parent::__construct($config);
            }

            protected function getHttpClient(): HttpClient
            {
                return $this->mockedHttpClient;
            }
        };
    }

    public function testCreateIndexSuccess(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('title', FieldType::TEXT),
                new FieldDefinition('price', FieldType::DOUBLE),
            ]
        );

        $apiResponse = [
            'status' => 'success',
            'physical_index_name' => 'app_550e8400_v1',
            'alias_name' => 'app_550e8400',
            'version' => 1,
            'fields_created' => 3,
            'message' => 'Index created successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/index',
                $request->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createIndex($request);

        $this->assertInstanceOf(IndexCreationResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(1, $result->version);
        $this->assertEquals('app_550e8400_v1', $result->physicalIndexName);
        $this->assertEquals('app_550e8400', $result->aliasName);
        $this->assertEquals(3, $result->fieldsCreated);
    }

    public function testCreateIndexWithMinimalRequest(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $apiResponse = [
            'status' => 'success',
            'physical_index_name' => 'app_550e8400_v1',
            'alias_name' => 'app_550e8400',
            'version' => 1,
            'fields_created' => 1,
            'message' => 'Index created successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/index',
                $request->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createIndex($request);

        $this->assertInstanceOf(IndexCreationResponse::class, $result);
        $this->assertEquals('success', $result->status);
    }

    public function testAppIdIncludedInUrlPath(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'physical_index_name' => 'app_550e8400_v1',
                'alias_name' => 'app_550e8400',
                'version' => 1,
                'fields_created' => 1,
                'message' => 'Index created',
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createIndex($request);
    }

    public function testRequestSerializedCorrectly(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT', 'en-US'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('name', FieldType::TEXT),
            ]
        );

        $expectedPayload = [
            'locales' => ['lt-LT', 'en-US'],
            'fields' => [
                ['name' => 'id', 'type' => 'keyword'],
                ['name' => 'name', 'type' => 'text'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $expectedPayload
            )
            ->willReturn([
                'status' => 'success',
                'physical_index_name' => 'app_550e8400_v1',
                'alias_name' => 'app_550e8400',
                'version' => 1,
                'fields_created' => 2,
                'message' => 'Index created',
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createIndex($request);
    }

    public function testGetIndexInfoSuccess(): void
    {
        $apiResponse = [
            'alias_name' => 'app_550e8400',
            'active_version' => 2,
            'active_index' => 'app_550e8400_v2',
            'all_versions' => [
                [
                    'version' => 1,
                    'index_name' => 'app_550e8400_v1',
                    'document_count' => 100,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'is_active' => false,
                ],
                [
                    'version' => 2,
                    'index_name' => 'app_550e8400_v2',
                    'document_count' => 150,
                    'created_at' => '2024-01-02T00:00:00Z',
                    'is_active' => true,
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/index/info')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getIndexInfo();

        $this->assertInstanceOf(IndexInfoResponse::class, $result);
        $this->assertEquals('app_550e8400', $result->aliasName);
        $this->assertEquals(2, $result->activeVersion);
        $this->assertEquals('app_550e8400_v2', $result->activeIndex);
        $this->assertCount(2, $result->allVersions);
    }

    public function testGetIndexInfoAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn([
                'alias_name' => 'test',
                'active_version' => 1,
                'active_index' => 'test_v1',
                'all_versions' => [
                    [
                        'version' => 1,
                        'index_name' => 'test_v1',
                        'document_count' => 0,
                        'created_at' => '2024-01-01T00:00:00Z',
                        'is_active' => true,
                    ],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getIndexInfo();
    }

    public function testGetIndexInfoUsesCorrectEndpoint(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringEndsWith('/index/info'))
            ->willReturn([
                'alias_name' => 'test',
                'active_version' => 1,
                'active_index' => 'test_v1',
                'all_versions' => [
                    [
                        'version' => 1,
                        'index_name' => 'test_v1',
                        'document_count' => 0,
                        'created_at' => '2024-01-01T00:00:00Z',
                        'is_active' => true,
                    ],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getIndexInfo();
    }

    public function testListIndexVersionsSuccess(): void
    {
        $apiResponse = [
            'alias_name' => 'app_550e8400',
            'active_version' => 2,
            'active_index' => 'app_550e8400_v2',
            'all_versions' => [
                [
                    'version' => 1,
                    'index_name' => 'app_550e8400_v1',
                    'document_count' => 100,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'is_active' => false,
                ],
                [
                    'version' => 2,
                    'index_name' => 'app_550e8400_v2',
                    'document_count' => 150,
                    'created_at' => '2024-01-02T00:00:00Z',
                    'is_active' => true,
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/index/versions')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->listIndexVersions();

        $this->assertInstanceOf(IndexInfoResponse::class, $result);
        $this->assertCount(2, $result->allVersions);
    }

    public function testListIndexVersionsAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn([
                'alias_name' => 'test',
                'active_version' => 1,
                'active_index' => 'test_v1',
                'all_versions' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->listIndexVersions();
    }

    public function testListIndexVersionsUsesCorrectEndpoint(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringEndsWith('/index/versions'))
            ->willReturn([
                'alias_name' => 'test',
                'active_version' => 1,
                'active_index' => 'test_v1',
                'all_versions' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->listIndexVersions();
    }

    public function testActivateIndexVersionSuccess(): void
    {
        $version = 2;

        $apiResponse = [
            'previous_version' => 1,
            'new_version' => 2,
            'alias_name' => 'app_550e8400',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/index/activate',
                ['version' => 'v' . $version]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->activateIndexVersion($version);

        $this->assertInstanceOf(VersionActivateResponse::class, $result);
        $this->assertEquals(1, $result->previousVersion);
        $this->assertEquals(2, $result->newVersion);
        $this->assertEquals('app_550e8400', $result->aliasName);
    }

    public function testActivateIndexVersionAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn(['previous_version' => 1, 'new_version' => 2, 'alias_name' => 'test']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->activateIndexVersion(2);
    }

    public function testActivateIndexVersionUsesCorrectEndpoint(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/index/activate'),
                $this->anything()
            )
            ->willReturn(['previous_version' => 1, 'new_version' => 2, 'alias_name' => 'test']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->activateIndexVersion(2);
    }

    public function testActivateIndexVersionSendsCorrectRequestBody(): void
    {
        $version = 5;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                ['version' => 'v' . $version]
            )
            ->willReturn(['previous_version' => 4, 'new_version' => 5, 'alias_name' => 'test']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->activateIndexVersion($version);
    }

    public function testDeleteIndexVersionSuccess(): void
    {
        $version = 1;

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Index version 1 deleted successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/applications/' . self::APP_ID . '/index/version/' . $version)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteIndexVersion($version);

        $this->assertIsArray($result);
        $this->assertEquals('deleted', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeleteIndexVersionReturnsRawApiResponse(): void
    {
        $version = 2;

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Index version 2 deleted successfully',
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteIndexVersion($version);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testDeleteIndexVersionAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteIndexVersion(1);
    }

    public function testDeleteIndexVersionUsesCorrectEndpoint(): void
    {
        $version = 3;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringEndsWith('/index/version/' . $version))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteIndexVersion($version);
    }

    public function testDeleteIndexVersionIncludesVersionInUrl(): void
    {
        $version = 5;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/applications/' . self::APP_ID . '/index/version/5')
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteIndexVersion($version);
    }

    public function testSetConfigurationSuccess(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
            new SearchFieldConfig('description', 2, MatchMode::FUZZY),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
            'search_fields' => [
                ['field' => 'title', 'position' => 1, 'match_mode' => 'fuzzy'],
                ['field' => 'description', 'position' => 2, 'match_mode' => 'fuzzy'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setConfiguration($config);

        $this->assertInstanceOf(QueryConfigurationResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals('app_550e8400', $result->indexName);
        $this->assertEquals(24, $result->cacheTtlHours);
    }

    public function testSetConfigurationWithMinimalConfig(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
            'search_fields' => [
                ['field' => 'title', 'position' => 1, 'match_mode' => 'fuzzy'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setConfiguration($config);

        $this->assertInstanceOf(QueryConfigurationResponse::class, $result);
        $this->assertEquals('success', $result->status);
    }

    public function testSetConfigurationAppIdIncludedInUrlPath(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [
                    ['field' => 'title', 'position' => 1, 'match_mode' => 'fuzzy'],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testSetConfigurationUsesCorrectEndpoint(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/configuration'),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [
                    ['field' => 'title', 'position' => 1, 'match_mode' => 'fuzzy'],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testGetConfigurationSuccess(): void
    {
        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
            'search_fields' => [
                ['field' => 'title', 'position' => 1, 'match_mode' => 'fuzzy'],
                ['field' => 'description', 'position' => 2, 'match_mode' => 'fuzzy'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/configuration')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getConfiguration();

        $this->assertInstanceOf(QueryConfigurationResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals('app_550e8400', $result->indexName);
        $this->assertEquals(24, $result->cacheTtlHours);
        $this->assertCount(2, $result->searchFields);
    }

    public function testGetConfigurationAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getConfiguration();
    }

    public function testGetConfigurationUsesCorrectEndpoint(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringEndsWith('/configuration'))
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getConfiguration();
    }

    public function testUpdateConfigurationSuccess(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 2, MatchMode::EXACT),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 12,
            'search_fields' => [
                ['field' => 'title', 'position' => 2, 'match_mode' => 'exact'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateConfiguration($config);

        $this->assertInstanceOf(QueryConfigurationResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(12, $result->cacheTtlHours);
    }

    public function testUpdateConfigurationAppIdIncludedInUrlPath(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateConfiguration($config);
    }

    public function testUpdateConfigurationUsesCorrectEndpoint(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->stringEndsWith('/configuration'),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'index_name' => 'app_550e8400',
                'cache_ttl_hours' => 24,
                'search_fields' => [],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateConfiguration($config);
    }

    public function testDeleteConfigurationSuccess(): void
    {
        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Configuration deleted successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/applications/' . self::APP_ID . '/configuration')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteConfiguration();

        $this->assertIsArray($result);
        $this->assertEquals('deleted', $result['status']);
    }

    public function testDeleteConfigurationAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteConfiguration();
    }

    public function testDeleteConfigurationUsesCorrectEndpoint(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringEndsWith('/configuration'))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteConfiguration();
    }

    public function testSetSynonymsSuccess(): void
    {
        $config = new SynonymConfiguration('en', [
            ['happy', 'joyful', 'cheerful'],
            ['sad', 'unhappy', 'sorrowful'],
        ]);

        $apiResponse = [
            'language' => 'en',
            'synonym_count' => 2,
            'requires_reindex' => true,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/synonyms',
                $config->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setSynonyms($config);

        $this->assertInstanceOf(SynonymResponse::class, $result);
        $this->assertEquals('en', $result->language);
        $this->assertEquals(2, $result->synonymCount);
        $this->assertTrue($result->requiresReindex);
    }

    public function testSetSynonymsAppIdIncludedInUrlPath(): void
    {
        $config = new SynonymConfiguration('en', [['happy', 'joyful']]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn([
                'language' => 'en',
                'synonym_count' => 1,
                'requires_reindex' => false,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms($config);
    }

    public function testSetSynonymsUsesCorrectEndpoint(): void
    {
        $config = new SynonymConfiguration('en', [['happy', 'joyful']]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/synonyms'),
                $this->anything()
            )
            ->willReturn([
                'language' => 'en',
                'synonym_count' => 1,
                'requires_reindex' => false,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms($config);
    }

    public function testGetSynonymsSuccess(): void
    {
        $language = 'en';

        $apiResponse = [
            'language' => 'en',
            'synonym_count' => 2,
            'requires_reindex' => false,
            'synonyms' => [
                ['happy', 'joyful', 'cheerful'],
                ['sad', 'unhappy', 'sorrowful'],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/synonyms?language=' . $language)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getSynonyms($language);

        $this->assertInstanceOf(SynonymResponse::class, $result);
        $this->assertEquals('en', $result->language);
        $this->assertEquals(2, $result->synonymCount);
        $this->assertFalse($result->requiresReindex);
        $this->assertCount(2, $result->synonyms);
    }

    public function testGetSynonymsAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn([
                'language' => 'en',
                'synonym_count' => 0,
                'requires_reindex' => false,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSynonyms('en');
    }

    public function testGetSynonymsIncludesLanguageInUrl(): void
    {
        $language = 'lt';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains('language=' . $language))
            ->willReturn([
                'language' => 'lt',
                'synonym_count' => 0,
                'requires_reindex' => false,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSynonyms($language);
    }

    public function testDeleteSynonymsSuccess(): void
    {
        $language = 'en';

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Synonyms deleted successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/applications/' . self::APP_ID . '/synonyms?language=' . $language)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteSynonyms($language);

        $this->assertIsArray($result);
        $this->assertEquals('deleted', $result['status']);
    }

    public function testDeleteSynonymsAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSynonyms('en');
    }

    public function testDeleteSynonymsIncludesLanguageInUrl(): void
    {
        $language = 'lt';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains('language=' . $language))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSynonyms($language);
    }

    public function testBulkOperationsSuccess(): void
    {
        $products = [
            new Product(
                id: '1',
                sku: 'SKU-001',
                pricing: new ProductPricing(10.00, 12.00, 8.00, 10.00),
                imageUrl: new ImageUrl('https://example.com/img1-small.jpg', 'https://example.com/img1-medium.jpg')
            ),
            new Product(
                id: '2',
                sku: 'SKU-002',
                pricing: new ProductPricing(20.00, 24.00, 16.00, 20.00),
                imageUrl: new ImageUrl('https://example.com/img2-small.jpg', 'https://example.com/img2-medium.jpg')
            ),
        ];
        $request = new BulkOperationsRequest([
            BulkOperation::indexProducts($products)
        ]);

        $apiResponse = [
            'status' => 'success',
            'total_operations' => 2,
            'successful_operations' => 2,
            'failed_operations' => 0,
            'results' => [
                ['operation_type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0],
                ['operation_type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/sync/bulk-operations',
                $request->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($request);

        $this->assertInstanceOf(BulkOperationsResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals(2, $result->totalOperations);
        $this->assertEquals(2, $result->successfulOperations);
        $this->assertEquals(0, $result->failedOperations);
    }

    public function testBulkOperationsAppIdIncludedInUrlPath(): void
    {
        $products = [
            new Product(
                id: '1',
                sku: 'SKU-001',
                pricing: new ProductPricing(10.00, 12.00, 8.00, 10.00),
                imageUrl: new ImageUrl('https://example.com/img1-small.jpg', 'https://example.com/img1-medium.jpg')
            ),
        ];
        $request = new BulkOperationsRequest([
            BulkOperation::indexProducts($products)
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['operation_type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($request);
    }

    public function testBulkOperationsUsesCorrectEndpoint(): void
    {
        $products = [
            new Product(
                id: '1',
                sku: 'SKU-001',
                pricing: new ProductPricing(10.00, 12.00, 8.00, 10.00),
                imageUrl: new ImageUrl('https://example.com/img1-small.jpg', 'https://example.com/img1-medium.jpg')
            ),
        ];
        $request = new BulkOperationsRequest([
            BulkOperation::indexProducts($products)
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/sync/bulk-operations'),
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['operation_type' => 'index_products', 'status' => 'success', 'items_processed' => 1, 'items_failed' => 0],
                ],
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($request);
    }

    public function testCreateSearchSettingsSuccess(): void
    {
        $settings = new SearchSettingsRequest(
            appId: self::APP_ID
        );

        $apiResponse = [
            'status' => 'created',
            'app_id' => self::APP_ID,
            'message' => 'Settings created successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/configuration',
                $settings->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createSearchSettings($settings);

        $this->assertInstanceOf(SettingsResponse::class, $result);
        $this->assertEquals('created', $result->status);
        $this->assertEquals(self::APP_ID, $result->appId);
    }

    public function testCreateSearchSettingsUsesCorrectEndpoint(): void
    {
        $settings = new SearchSettingsRequest(
            appId: self::APP_ID
        );

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/configuration',
                $this->anything()
            )
            ->willReturn([
                'status' => 'created',
                'app_id' => self::APP_ID,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createSearchSettings($settings);
    }

    public function testGetSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'status' => 'success',
            'app_id' => $appId,
            'api_key' => 'test-api-key',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/configuration/' . $appId)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getSearchSettings($appId);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals($appId, $result['app_id']);
    }

    public function testGetSearchSettingsUsesCorrectEndpoint(): void
    {
        $appId = self::APP_ID;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/configuration/' . $appId)
            ->willReturn(['status' => 'success', 'app_id' => $appId]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSearchSettings($appId);
    }

    public function testUpdateSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;
        $settings = new SearchSettingsRequest(
            appId: $appId
        );

        $apiResponse = [
            'status' => 'success',
            'app_id' => $appId,
            'message' => 'Settings updated successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/configuration/' . $appId,
                $settings->jsonSerialize()
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateSearchSettings($appId, $settings);

        $this->assertInstanceOf(SettingsResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals($appId, $result->appId);
    }

    public function testUpdateSearchSettingsUsesCorrectEndpoint(): void
    {
        $appId = self::APP_ID;
        $settings = new SearchSettingsRequest(
            appId: $appId
        );

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/configuration/' . $appId,
                $this->anything()
            )
            ->willReturn([
                'status' => 'success',
                'app_id' => $appId,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateSearchSettings($appId, $settings);
    }

    public function testDeleteSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Settings deleted successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/configuration/' . $appId)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteSearchSettings($appId);

        $this->assertIsArray($result);
        $this->assertEquals('deleted', $result['status']);
    }

    public function testDeleteSearchSettingsUsesCorrectEndpoint(): void
    {
        $appId = self::APP_ID;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/configuration/' . $appId)
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSearchSettings($appId);
    }

    public function testGetAppIdReturnsConfiguredAppId(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);

        $this->assertEquals(self::APP_ID, $sdk->getAppId());
    }

    public function testGetBaseApiPathReturnsCorrectPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);

        $this->assertEquals('api/v2/applications/' . self::APP_ID . '/', $sdk->getBaseApiPath());
    }
}
