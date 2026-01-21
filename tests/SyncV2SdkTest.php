<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\SyncV2Sdk;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\Client\HttpClient;
use PHPUnit\Framework\TestCase;

class SyncV2SdkTest extends TestCase
{
    private const APP_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const API_URL = 'https://api.bradsearch.com';
    private const TOKEN = 'test-bearer-token';

    private function createSdkWithMockedHttpClient(HttpClient $httpClientMock): SyncV2Sdk
    {
        $config = new SyncConfigV2(self::APP_ID, self::API_URL, self::TOKEN);

        return new class($config, $httpClientMock) extends SyncV2Sdk {
            public function __construct(SyncConfigV2 $config, private HttpClient $mockedHttpClient)
            {
                parent::__construct($config);
            }

            protected function getHttpClient(): HttpClient
            {
                return $this->mockedHttpClient;
            }

            public function createIndex(array $fields): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'index',
                    ['fields' => $fields]
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

            public function setConfiguration(array $config): array
            {
                return $this->mockedHttpClient->post(
                    $this->getBaseApiPath() . 'configuration',
                    $config
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
        };
    }

    public function testCreateIndexSuccess(): void
    {
        $fields = [
            [
                'name' => 'id',
                'type' => 'keyword',
            ],
            [
                'name' => 'title',
                'type' => 'text_keyword',
            ],
            [
                'name' => 'price',
                'type' => 'float',
            ],
        ];

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
                ['fields' => $fields]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createIndex($fields);

        $this->assertIsArray($result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(1, $result['version']);
        $this->assertEquals('app_550e8400_v1', $result['index_name']);
        $this->assertEquals('app_550e8400', $result['alias_name']);
        $this->assertTrue($result['active']);
    }

    public function testCreateIndexWithEmptyFields(): void
    {
        $fields = [];

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
                ['fields' => $fields]
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->createIndex($fields);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testCreateIndexReturnsRawApiResponse(): void
    {
        $fields = [
            ['name' => 'id', 'type' => 'keyword'],
        ];

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
        $result = $sdk->createIndex($fields);

        $this->assertEquals($apiResponse, $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertEquals('extra_value', $result['extra_field']);
    }

    public function testAppIdIncludedInUrlPath(): void
    {
        $fields = [['name' => 'id', 'type' => 'keyword']];

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
        $sdk->createIndex($fields);
    }

    public function testFieldsPassedThroughWithoutModification(): void
    {
        $fields = [
            [
                'name' => 'categories',
                'type' => 'hierarchy',
                'custom_setting' => true,
                'nested' => ['a' => 1, 'b' => 2],
            ],
        ];

        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                ['fields' => $fields]
            )
            ->willReturn(['status' => 'created']);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $sdk->createIndex($fields);
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
        $config = [
            'search_fields' => ['title', 'description'],
            'fuzzy_matching' => true,
        ];

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
                $config
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setConfiguration($config);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('app_550e8400', $result['index_name']);
        $this->assertEquals(24, $result['cache_ttl_hours']);
    }

    public function testSetConfigurationWithEmptyConfig(): void
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
            ->method('post')
            ->with(
                'api/v2/applications/' . self::APP_ID . '/configuration',
                $config
            )
            ->willReturn($apiResponse);

        $sdk = $this->createSdkWithMockedHttpClient($httpClientMock);
        $result = $sdk->setConfiguration($config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testSetConfigurationReturnsRawApiResponse(): void
    {
        $config = [
            'search_fields' => ['title'],
        ];

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
        $config = ['fuzzy_matching' => true];

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
        $config = ['fuzzy_matching' => false];

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

    public function testSetConfigurationPassesConfigWithoutModification(): void
    {
        $config = [
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
                $config
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
}
