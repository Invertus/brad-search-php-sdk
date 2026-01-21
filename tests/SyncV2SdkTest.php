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
}
