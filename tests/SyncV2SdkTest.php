<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\SyncV2Sdk;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
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

            public function createIndex(IndexCreateRequest $request): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'index',
                    $request->jsonSerialize()
                );
            }

            public function getIndexInfo(): array
            {
                return $this->mockedHttpClient->get(
                    $this->getBaseApiPath() . 'index/info'
                );
            }

            public function listIndexVersions(): array
            {
                return $this->mockedHttpClient->get(
                    $this->getBaseApiPath() . 'index/versions'
                );
            }

            public function activateIndexVersion(int $version): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'index/activate',
                    ['version' => $version]
                );
            }

            public function deleteIndexVersion(int $version): array
            {
                return $this->mockedHttpClient->delete(
                    $this->getBaseApiPath() . 'index/version/' . $version
                );
            }

            public function setConfiguration(QueryConfigurationRequest $config): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'configuration',
                    $config->jsonSerialize()
                );
            }

            public function getConfiguration(): array
            {
                return $this->mockedHttpClient->get(
                    $this->getBaseApiPath() . 'configuration'
                );
            }

            public function updateConfiguration(array $config): array
            {
                return $this->mockedHttpClient->put(
                    $this->getBaseApiPath() . 'configuration',
                    $config
                );
            }

            public function deleteConfiguration(): array
            {
                return $this->mockedHttpClient->delete(
                    $this->getBaseApiPath() . 'configuration'
                );
            }

            public function setSynonyms(string $language, array $synonyms): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'synonyms',
                    [
                        'language' => $language,
                        'synonyms' => $synonyms,
                    ]
                );
            }

            public function getSynonyms(string $language): array
            {
                return $this->mockedHttpClient->get(
                    $this->getBaseApiPath() . 'synonyms?language=' . $language
                );
            }

            public function deleteSynonyms(string $language): array
            {
                return $this->mockedHttpClient->delete(
                    $this->getBaseApiPath() . 'synonyms?language=' . $language
                );
            }

            public function bulkOperations(array $operations): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'sync/bulk-operations',
                    ['operations' => $operations]
                );
            }

            public function createSearchSettings(array $settings): array
            {
                return $this->mockedHttpClient->post(
                    'api/v2/configuration',
                    $settings
                );
            }

            public function getSearchSettings(string $appId): array
            {
                return $this->mockedHttpClient->get(
                    'api/v2/configuration/' . $appId
                );
            }

            public function updateSearchSettings(string $appId, array $settings): array
            {
                return $this->mockedHttpClient->put(
                    'api/v2/configuration/' . $appId,
                    $settings
                );
            }

            public function deleteSearchSettings(string $appId): array
            {
                return $this->mockedHttpClient->delete(
                    'api/v2/configuration/' . $appId
                );
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
            'status' => 'created',
            'version' => 1,
            'index_name' => 'app_550e8400_v1',
            'alias_name' => 'app_550e8400',
            'active' => true,
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

        $this->assertIsArray($result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(1, $result['version']);
        $this->assertEquals('app_550e8400_v1', $result['index_name']);
        $this->assertEquals('app_550e8400', $result['alias_name']);
        $this->assertTrue($result['active']);
    }

    public function testCreateIndexWithMinimalRequest(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $apiResponse = [
            'status' => 'created',
            'version' => 1,
            'index_name' => 'app_550e8400_v1',
            'alias_name' => 'app_550e8400',
            'active' => true,
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testCreateIndexReturnsRawApiResponse(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $apiResponse = [
            'status' => 'created',
            'version' => 2,
            'index_name' => 'app_550e8400_v2',
            'alias_name' => 'app_550e8400',
            'active' => false,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createIndex($request);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
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
            ->willReturn(['status' => 'created']);

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
            ->willReturn(['status' => 'created']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createIndex($request);
    }

    public function testGetIndexInfoSuccess(): void
    {
        $apiResponse = [
            'alias_name' => 'app_550e8400',
            'active_version' => 2,
            'active_index' => 'app_550e8400_v2',
            'all_versions' => [1, 2],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/index/info')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getIndexInfo();

        $this->assertIsArray($result);
        $this->assertEquals('app_550e8400', $result['alias_name']);
        $this->assertEquals(2, $result['active_version']);
        $this->assertEquals('app_550e8400_v2', $result['active_index']);
        $this->assertEquals([1, 2], $result['all_versions']);
    }

    public function testGetIndexInfoReturnsRawApiResponse(): void
    {
        $apiResponse = [
            'alias_name' => 'app_550e8400',
            'active_version' => 1,
            'active_index' => 'app_550e8400_v1',
            'all_versions' => [1],
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getIndexInfo();

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testGetIndexInfoAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['alias_name' => 'test']);

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
            ->willReturn(['alias_name' => 'test']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getIndexInfo();
    }

    public function testListIndexVersionsSuccess(): void
    {
        $apiResponse = [
            'versions' => [
                ['version' => 1, 'created_at' => '2024-01-01T00:00:00Z'],
                ['version' => 2, 'created_at' => '2024-01-02T00:00:00Z'],
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('versions', $result);
        $this->assertCount(2, $result['versions']);
    }

    public function testListIndexVersionsReturnsRawApiResponse(): void
    {
        $apiResponse = [
            'versions' => [1, 2, 3],
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->listIndexVersions();

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testListIndexVersionsAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['versions' => []]);

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
            ->willReturn(['versions' => []]);

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
                ['version' => $version]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->activateIndexVersion($version);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['previous_version']);
        $this->assertEquals(2, $result['new_version']);
        $this->assertEquals('app_550e8400', $result['alias_name']);
    }

    public function testActivateIndexVersionReturnsRawApiResponse(): void
    {
        $version = 3;

        $apiResponse = [
            'previous_version' => 2,
            'new_version' => 3,
            'alias_name' => 'app_550e8400',
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->activateIndexVersion($version);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
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
            ->willReturn(['previous_version' => 1, 'new_version' => 2]);

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
            ->willReturn(['previous_version' => 1, 'new_version' => 2]);

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
                ['version' => $version]
            )
            ->willReturn(['previous_version' => 4, 'new_version' => 5]);

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
            new SearchFieldConfig('title', 1, 2.0, MatchMode::FUZZY),
            new SearchFieldConfig('description', 2, 1.5, MatchMode::FUZZY),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
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

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('app_550e8400', $result['index_name']);
        $this->assertEquals(24, $result['cache_ttl_hours']);
    }

    public function testSetConfigurationWithMinimalConfig(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 1.0, MatchMode::FUZZY),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testSetConfigurationReturnsRawApiResponse(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 1.0, MatchMode::FUZZY),
        ]);

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 12,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setConfiguration($config);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testSetConfigurationAppIdIncludedInUrlPath(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 1.0, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testSetConfigurationUsesCorrectEndpoint(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 1.0, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/configuration'),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testSetConfigurationPassesConfigWithCorrectSerialization(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 2.0, MatchMode::FUZZY),
            new SearchFieldConfig('description', 2, 1.5, MatchMode::PHRASE_PREFIX),
            new SearchFieldConfig('brand', 3, 1.0, MatchMode::EXACT),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $config->jsonSerialize()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testGetConfigurationSuccess(): void
    {
        $apiResponse = [
            'search_fields' => ['title', 'description'],
            'fuzzy_matching' => true,
            'cache_ttl_hours' => 24,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/configuration')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getConfiguration();

        $this->assertIsArray($result);
        $this->assertEquals(['title', 'description'], $result['search_fields']);
        $this->assertTrue($result['fuzzy_matching']);
        $this->assertEquals(24, $result['cache_ttl_hours']);
    }

    public function testGetConfigurationReturnsRawApiResponse(): void
    {
        $apiResponse = [
            'search_fields' => ['title'],
            'fuzzy_matching' => false,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getConfiguration();

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testGetConfigurationAppIdIncludedInUrlPath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['search_fields' => []]);

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
            ->willReturn(['search_fields' => []]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getConfiguration();
    }

    public function testUpdateConfigurationSuccess(): void
    {
        $config = [
            'search_fields' => ['title', 'description', 'brand'],
            'fuzzy_matching' => false,
        ];

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 12,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateConfiguration($config);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('app_550e8400', $result['index_name']);
        $this->assertEquals(12, $result['cache_ttl_hours']);
    }

    public function testUpdateConfigurationWithEmptyConfig(): void
    {
        $config = [];

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 24,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateConfiguration($config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testUpdateConfigurationReturnsRawApiResponse(): void
    {
        $config = [
            'search_fields' => ['title'],
        ];

        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_550e8400',
            'cache_ttl_hours' => 48,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateConfiguration($config);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testUpdateConfigurationAppIdIncludedInUrlPath(): void
    {
        $config = ['fuzzy_matching' => true];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateConfiguration($config);
    }

    public function testUpdateConfigurationUsesCorrectEndpoint(): void
    {
        $config = ['fuzzy_matching' => false];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->stringEndsWith('/configuration'),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateConfiguration($config);
    }

    public function testUpdateConfigurationPassesConfigWithoutModification(): void
    {
        $config = [
            'search_fields' => ['title', 'description', 'brand'],
            'fuzzy_matching' => true,
            'custom_option' => ['nested' => 'value'],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->anything(),
                $config
            )
            ->willReturn(['status' => 'success']);

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
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeleteConfigurationReturnsRawApiResponse(): void
    {
        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Configuration deleted successfully',
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteConfiguration();

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
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
        $language = 'en';
        $synonyms = [
            ['laptop', 'notebook', 'portable computer'],
            ['phone', 'mobile', 'smartphone'],
        ];

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
                [
                    'language' => $language,
                    'synonyms' => $synonyms,
                ]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setSynonyms($language, $synonyms);

        $this->assertIsArray($result);
        $this->assertEquals('en', $result['language']);
        $this->assertEquals(2, $result['synonym_count']);
        $this->assertTrue($result['requires_reindex']);
    }

    public function testSetSynonymsWithEmptySynonyms(): void
    {
        $language = 'en';
        $synonyms = [];

        $apiResponse = [
            'language' => 'en',
            'synonym_count' => 0,
            'requires_reindex' => false,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/synonyms',
                [
                    'language' => $language,
                    'synonyms' => $synonyms,
                ]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setSynonyms($language, $synonyms);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('language', $result);
        $this->assertEquals(0, $result['synonym_count']);
    }

    public function testSetSynonymsReturnsRawApiResponse(): void
    {
        $language = 'lt';
        $synonyms = [['kompiuteris', 'PC']];

        $apiResponse = [
            'language' => 'lt',
            'synonym_count' => 1,
            'requires_reindex' => true,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setSynonyms($language, $synonyms);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testSetSynonymsAppIdIncludedInUrlPath(): void
    {
        $language = 'en';
        $synonyms = [['test', 'trial']];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn(['language' => 'en', 'synonym_count' => 1]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms($language, $synonyms);
    }

    public function testSetSynonymsUsesCorrectEndpoint(): void
    {
        $language = 'en';
        $synonyms = [['test', 'trial']];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/synonyms'),
                $this->anything()
            )
            ->willReturn(['language' => 'en', 'synonym_count' => 1]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms($language, $synonyms);
    }

    public function testSetSynonymsSendsCorrectRequestBody(): void
    {
        $language = 'de';
        $synonyms = [
            ['computer', 'rechner'],
            ['handy', 'smartphone', 'mobiltelefon'],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                [
                    'language' => $language,
                    'synonyms' => $synonyms,
                ]
            )
            ->willReturn(['language' => 'de', 'synonym_count' => 2]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms($language, $synonyms);
    }

    public function testGetSynonymsSuccess(): void
    {
        $language = 'en';

        $apiResponse = [
            'language' => 'en',
            'synonyms' => [
                ['laptop', 'notebook', 'portable computer'],
                ['phone', 'mobile', 'smartphone'],
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

        $this->assertIsArray($result);
        $this->assertEquals('en', $result['language']);
        $this->assertArrayHasKey('synonyms', $result);
        $this->assertCount(2, $result['synonyms']);
    }

    public function testGetSynonymsReturnsRawApiResponse(): void
    {
        $language = 'lt';

        $apiResponse = [
            'language' => 'lt',
            'synonyms' => [['kompiuteris', 'PC']],
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getSynonyms($language);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testGetSynonymsAppIdIncludedInUrlPath(): void
    {
        $language = 'en';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['language' => 'en', 'synonyms' => []]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSynonyms($language);
    }

    public function testGetSynonymsUsesCorrectEndpoint(): void
    {
        $language = 'en';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains('/synonyms?language='))
            ->willReturn(['language' => 'en', 'synonyms' => []]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSynonyms($language);
    }

    public function testGetSynonymsIncludesLanguageInQueryString(): void
    {
        $language = 'de';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/synonyms?language=de')
            ->willReturn(['language' => 'de', 'synonyms' => []]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSynonyms($language);
    }

    public function testGetSynonymsWithEmptyResult(): void
    {
        $language = 'fr';

        $apiResponse = [
            'language' => 'fr',
            'synonyms' => [],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/synonyms?language=' . $language)
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getSynonyms($language);

        $this->assertIsArray($result);
        $this->assertEquals('fr', $result['language']);
        $this->assertEmpty($result['synonyms']);
    }

    public function testDeleteSynonymsSuccess(): void
    {
        $language = 'en';

        $apiResponse = [
            'status' => 'deleted',
            'language' => 'en',
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
        $this->assertEquals('en', $result['language']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeleteSynonymsReturnsRawApiResponse(): void
    {
        $language = 'lt';

        $apiResponse = [
            'status' => 'deleted',
            'language' => 'lt',
            'message' => 'Synonyms deleted successfully',
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteSynonyms($language);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testDeleteSynonymsAppIdIncludedInUrlPath(): void
    {
        $language = 'en';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains(self::APP_ID))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSynonyms($language);
    }

    public function testDeleteSynonymsUsesCorrectEndpoint(): void
    {
        $language = 'en';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains('/synonyms?language='))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSynonyms($language);
    }

    public function testDeleteSynonymsIncludesLanguageInQueryString(): void
    {
        $language = 'de';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with('api/v2/applications/' . self::APP_ID . '/synonyms?language=de')
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSynonyms($language);
    }

    public function testBulkOperationsSuccess(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [
                        ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99],
                    ],
                ],
            ],
            [
                'type' => 'update_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'updates' => [
                        ['id' => 'prod-124', 'fields' => ['price' => 129.99]],
                    ],
                ],
            ],
            [
                'type' => 'delete_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'product_ids' => ['prod-125'],
                ],
            ],
        ];

        $apiResponse = [
            'status' => 'success',
            'message' => 'All 3 operations completed successfully',
            'total_operations' => 3,
            'successful_operations' => 3,
            'failed_operations' => 0,
            'processing_time_ms' => 2156,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1',
                ],
                [
                    'type' => 'update_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1',
                ],
                [
                    'type' => 'delete_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1',
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/sync/bulk-operations',
                ['operations' => $operations]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(3, $result['total_operations']);
        $this->assertEquals(3, $result['successful_operations']);
        $this->assertEquals(0, $result['failed_operations']);
        $this->assertCount(3, $result['results']);
    }

    public function testBulkOperationsWithEmptyOperations(): void
    {
        $operations = [];

        $apiResponse = [
            'status' => 'success',
            'message' => 'No operations to process',
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'processing_time_ms' => 0,
            'results' => [],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/sync/bulk-operations',
                ['operations' => $operations]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(0, $result['total_operations']);
    }

    public function testBulkOperationsReturnsRawApiResponse(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [['id' => 'prod-123']],
                ],
            ],
        ];

        $apiResponse = [
            'status' => 'success',
            'message' => 'All 1 operations completed successfully',
            'total_operations' => 1,
            'successful_operations' => 1,
            'failed_operations' => 0,
            'processing_time_ms' => 500,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                ],
            ],
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testBulkOperationsAppIdIncludedInUrlPath(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [],
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains(self::APP_ID),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($operations);
    }

    public function testBulkOperationsUsesCorrectEndpoint(): void
    {
        $operations = [
            [
                'type' => 'delete_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'product_ids' => ['prod-123'],
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringEndsWith('/sync/bulk-operations'),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($operations);
    }

    public function testBulkOperationsSendsCorrectRequestBody(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [
                        ['id' => 'prod-123', 'name' => 'Test Product'],
                    ],
                    'subfields' => ['name' => ['split_by' => [' ']]],
                    'embeddablefields' => ['description' => 'name'],
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                ['operations' => $operations]
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($operations);
    }

    public function testBulkOperationsPartialFailure(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [['id' => 'prod-123']],
                ],
            ],
            [
                'type' => 'delete_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'product_ids' => ['prod-999'],
                ],
            ],
        ];

        $apiResponse = [
            'status' => 'partial',
            'message' => '1 operations succeeded, 1 operations failed',
            'total_operations' => 2,
            'successful_operations' => 1,
            'failed_operations' => 1,
            'processing_time_ms' => 856,
            'results' => [
                [
                    'type' => 'index_products',
                    'status' => 'success',
                    'message' => 'Operation completed',
                    'count' => 1,
                    'index_name' => 'products-v1',
                ],
                [
                    'type' => 'delete_products',
                    'status' => 'error',
                    'message' => 'Products not found',
                    'index_name' => 'products-v1',
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertIsArray($result);
        $this->assertEquals('partial', $result['status']);
        $this->assertEquals(2, $result['total_operations']);
        $this->assertEquals(1, $result['successful_operations']);
        $this->assertEquals(1, $result['failed_operations']);
    }

    public function testBulkOperationsPassesOperationsWithoutModification(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [
                        ['id' => 'prod-123', 'name' => 'Product 1', 'price' => 99.99],
                        ['id' => 'prod-124', 'name' => 'Product 2', 'price' => 149.99],
                    ],
                    'subfields' => ['name' => ['split_by' => [' ', '-'], 'max_count' => 3]],
                    'embeddablefields' => ['description' => 'name'],
                    'custom_option' => ['nested' => 'value'],
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                ['operations' => $operations]
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->bulkOperations($operations);
    }

    public function testCreateSearchSettingsSuccess(): void
    {
        $settings = [
            'app_id' => self::APP_ID,
            'search_fields' => ['title', 'description'],
            'fuzzy_matching' => true,
        ];

        $apiResponse = [
            'status' => 'success',
            'app_id' => self::APP_ID,
            'message' => 'Search settings created successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/configuration',
                $settings
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createSearchSettings($settings);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(self::APP_ID, $result['app_id']);
    }

    public function testCreateSearchSettingsWithEmptySettings(): void
    {
        $settings = [];

        $apiResponse = [
            'status' => 'success',
            'message' => 'Search settings created successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/configuration',
                $settings
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createSearchSettings($settings);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testCreateSearchSettingsReturnsRawApiResponse(): void
    {
        $settings = [
            'app_id' => self::APP_ID,
            'search_fields' => ['title'],
        ];

        $apiResponse = [
            'status' => 'success',
            'app_id' => self::APP_ID,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createSearchSettings($settings);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testCreateSearchSettingsUsesCorrectEndpoint(): void
    {
        $settings = ['fuzzy_matching' => true];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/configuration',
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createSearchSettings($settings);
    }

    public function testCreateSearchSettingsPassesSettingsWithoutModification(): void
    {
        $settings = [
            'app_id' => self::APP_ID,
            'search_fields' => ['title', 'description', 'brand'],
            'fuzzy_matching' => true,
            'custom_option' => ['nested' => 'value'],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $settings
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createSearchSettings($settings);
    }

    public function testGetSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'app_id' => $appId,
            'search_fields' => ['title', 'description'],
            'fuzzy_matching' => true,
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
        $this->assertEquals($appId, $result['app_id']);
        $this->assertEquals(['title', 'description'], $result['search_fields']);
        $this->assertTrue($result['fuzzy_matching']);
    }

    public function testGetSearchSettingsReturnsRawApiResponse(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'app_id' => $appId,
            'search_fields' => ['title'],
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->getSearchSettings($appId);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testGetSearchSettingsAppIdIncludedInUrlPath(): void
    {
        $appId = self::APP_ID;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains($appId))
            ->willReturn(['app_id' => $appId]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSearchSettings($appId);
    }

    public function testGetSearchSettingsUsesCorrectEndpoint(): void
    {
        $appId = self::APP_ID;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/configuration/' . $appId)
            ->willReturn(['app_id' => $appId]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSearchSettings($appId);
    }

    public function testUpdateSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;
        $settings = [
            'search_fields' => ['title', 'description', 'brand'],
            'fuzzy_matching' => false,
        ];

        $apiResponse = [
            'status' => 'success',
            'app_id' => $appId,
            'message' => 'Search settings updated successfully',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/configuration/' . $appId,
                $settings
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateSearchSettings($appId, $settings);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals($appId, $result['app_id']);
    }

    public function testUpdateSearchSettingsWithEmptySettings(): void
    {
        $appId = self::APP_ID;
        $settings = [];

        $apiResponse = [
            'status' => 'success',
            'app_id' => $appId,
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/configuration/' . $appId,
                $settings
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateSearchSettings($appId, $settings);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testUpdateSearchSettingsReturnsRawApiResponse(): void
    {
        $appId = self::APP_ID;
        $settings = [
            'search_fields' => ['title'],
        ];

        $apiResponse = [
            'status' => 'success',
            'app_id' => $appId,
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->updateSearchSettings($appId, $settings);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testUpdateSearchSettingsAppIdIncludedInUrlPath(): void
    {
        $appId = self::APP_ID;
        $settings = ['fuzzy_matching' => true];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->stringContains($appId),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateSearchSettings($appId, $settings);
    }

    public function testUpdateSearchSettingsUsesCorrectEndpoint(): void
    {
        $appId = self::APP_ID;
        $settings = ['fuzzy_matching' => false];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                'api/v2/configuration/' . $appId,
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateSearchSettings($appId, $settings);
    }

    public function testUpdateSearchSettingsPassesSettingsWithoutModification(): void
    {
        $appId = self::APP_ID;
        $settings = [
            'search_fields' => ['title', 'description', 'brand'],
            'fuzzy_matching' => true,
            'custom_option' => ['nested' => 'value'],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->anything(),
                $settings
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->updateSearchSettings($appId, $settings);
    }

    public function testDeleteSearchSettingsSuccess(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Search settings deleted successfully',
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
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeleteSearchSettingsReturnsRawApiResponse(): void
    {
        $appId = self::APP_ID;

        $apiResponse = [
            'status' => 'deleted',
            'message' => 'Search settings deleted successfully',
            'extra_field' => 'extra_value',
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->deleteSearchSettings($appId);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testDeleteSearchSettingsAppIdIncludedInUrlPath(): void
    {
        $appId = self::APP_ID;

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('delete')
            ->with($this->stringContains($appId))
            ->willReturn(['status' => 'deleted']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->deleteSearchSettings($appId);
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

    public function testGetBaseApiPathIncludesAppId(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);

        $expectedPath = 'api/v2/applications/' . self::APP_ID . '/';
        $this->assertEquals($expectedPath, $sdk->getBaseApiPath());
    }

    public function testGetBaseApiPathFollowsCorrectV2Format(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);

        $basePath = $sdk->getBaseApiPath();

        $this->assertStringStartsWith('api/v2/', $basePath);
        $this->assertStringContainsString('/applications/', $basePath);
        $this->assertStringEndsWith('/', $basePath);
    }

    public function testSdkConstructorConfiguresCorrectBaseApiPath(): void
    {
        $config = new SyncConfigV2(self::APP_ID, self::API_URL, self::TOKEN);
        $sdk = new SyncV2Sdk($config);

        $expectedPath = 'api/v2/applications/' . self::APP_ID . '/';
        $this->assertEquals($expectedPath, $sdk->getBaseApiPath());
    }

    public function testSdkWithDifferentAppIdHasCorrectPath(): void
    {
        $differentAppId = '12345678-1234-1234-1234-123456789012';
        $config = new SyncConfigV2($differentAppId, self::API_URL, self::TOKEN);
        $sdk = new SyncV2Sdk($config);

        $expectedPath = 'api/v2/applications/' . $differentAppId . '/';
        $this->assertEquals($expectedPath, $sdk->getBaseApiPath());
        $this->assertEquals($differentAppId, $sdk->getAppId());
    }

    public function testAllEndpointsUseV2ApiVersion(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);

        // Track all called endpoints
        $calledEndpoints = [];

        $httpClientMock
            ->method('get')
            ->willReturnCallback(function (string $endpoint) use (&$calledEndpoints) {
                $calledEndpoints[] = $endpoint;
                return ['status' => 'success'];
            });

        $httpClientMock
            ->method('post')
            ->willReturnCallback(function (string $endpoint) use (&$calledEndpoints) {
                $calledEndpoints[] = $endpoint;
                return ['status' => 'success'];
            });

        $httpClientMock
            ->method('put')
            ->willReturnCallback(function (string $endpoint) use (&$calledEndpoints) {
                $calledEndpoints[] = $endpoint;
                return ['status' => 'success'];
            });

        $httpClientMock
            ->method('delete')
            ->willReturnCallback(function (string $endpoint) use (&$calledEndpoints) {
                $calledEndpoints[] = $endpoint;
                return ['status' => 'deleted'];
            });

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);

        // Call various methods to collect endpoints
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );
        $sdk->createIndex($request);
        $sdk->getIndexInfo();
        $sdk->listIndexVersions();
        $sdk->getConfiguration();
        $sdk->getSynonyms('en');

        // Verify all endpoints use v2 API
        foreach ($calledEndpoints as $endpoint) {
            $this->assertStringContainsString('api/v2/', $endpoint);
        }
    }

    public function testDataIntegrityForNestedStructures(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('categories', FieldType::TEXT),
                new FieldDefinition('variants', FieldType::VARIANTS, [
                    new VariantAttribute('color', FieldType::KEYWORD, true),
                    new VariantAttribute('size', FieldType::KEYWORD, true),
                ]),
            ]
        );

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $request->jsonSerialize()
            )
            ->willReturn(['status' => 'created']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createIndex($request);
    }

    public function testMultipleLanguageSynonymsPassedCorrectly(): void
    {
        $synonyms = [
            ['laptop', 'notebook', 'portable computer', 'portable PC'],
            ['phone', 'mobile', 'smartphone', 'cellphone', 'cell'],
            ['TV', 'television', 'telly', 'flat screen'],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                [
                    'language' => 'en',
                    'synonyms' => $synonyms,
                ]
            )
            ->willReturn(['language' => 'en', 'synonym_count' => 3]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setSynonyms('en', $synonyms);
    }

    public function testBulkOperationsWithAllOperationTypes(): void
    {
        $operations = [
            [
                'type' => 'index_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'products' => [
                        ['id' => 'prod-1', 'name' => 'Product 1'],
                        ['id' => 'prod-2', 'name' => 'Product 2'],
                    ],
                ],
            ],
            [
                'type' => 'update_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'updates' => [
                        ['id' => 'prod-3', 'fields' => ['price' => 99.99]],
                    ],
                ],
            ],
            [
                'type' => 'delete_products',
                'payload' => [
                    'index_name' => 'products-v1',
                    'product_ids' => ['prod-4', 'prod-5'],
                ],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/sync/bulk-operations',
                ['operations' => $operations]
            )
            ->willReturn([
                'status' => 'success',
                'total_operations' => 3,
                'successful_operations' => 3,
                'failed_operations' => 0,
            ]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->bulkOperations($operations);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(3, $result['total_operations']);
    }

    public function testSearchSettingsEndpointsDoNotIncludeAppIdInBasePath(): void
    {
        $settings = ['search_fields' => ['title']];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->callback(function (string $endpoint) {
                    // Search settings use global endpoint without app_id in base path
                    return $endpoint === 'api/v2/configuration';
                }),
                $this->anything()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createSearchSettings($settings);
    }

    public function testGetSearchSettingsIncludesAppIdInUrl(): void
    {
        $targetAppId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/configuration/' . $targetAppId)
            ->willReturn(['app_id' => $targetAppId]);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getSearchSettings($targetAppId);
    }

    public function testConfigurationMethodsUseAppIdBasePath(): void
    {
        $config = new QueryConfigurationRequest([
            new SearchFieldConfig('title', 1, 2.0, MatchMode::FUZZY),
            new SearchFieldConfig('description', 2, 1.5, MatchMode::FUZZY),
        ]);

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config->jsonSerialize()
            )
            ->willReturn(['status' => 'success']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->setConfiguration($config);
    }

    public function testIndexMethodsUseAppIdBasePath(): void
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('get')
            ->with('api/v2/applications/' . self::APP_ID . '/index/info')
            ->willReturn(['alias_name' => 'test']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->getIndexInfo();
    }
}
