<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\SyncV2Sdk;
use BradSearch\SyncSdk\Tests\Client\Support\FakeTransport;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use PHPUnit\Framework\TestCase;

/**
 * Drives SyncV2Sdk through a fake transport to prove config timeouts reach the
 * wire and that only bulk-style POSTs are retried.
 *
 * Retries in these tests hit the real sleeper, so every retried response uses a
 * tiny retry policy delay via the config to keep the suite fast.
 */
class SyncV2SdkTransportTest extends TestCase
{
    private const APP_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testTimeoutsAndAuthReachTheTransport(): void
    {
        $config = new SyncConfigV2(
            appId: self::APP_ID,
            apiUrl: 'https://api.example.com',
            token: 'secret',
            timeout: 120,
            connectTimeout: 10,
        );
        $transport = new FakeTransport([new HttpResponse(200, '{"settings":[]}')]);

        (new SyncV2Sdk($config, $transport))->getSearchSettings();

        $request = $transport->requests[0];
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://api.example.com/api/v2/applications/' . self::APP_ID . '/configuration', $request->url);
        $this->assertSame(120, $request->timeout);
        $this->assertSame(10, $request->connectTimeout);
        $this->assertContains('Authorization: Bearer secret', $request->headers);
    }

    public function testDefaultsAreThirtySecondReadAndTenSecondConnect(): void
    {
        $config = new SyncConfigV2(self::APP_ID, 'https://api.example.com', 'secret');
        $transport = new FakeTransport([new HttpResponse(200, '{}')]);

        (new SyncV2Sdk($config, $transport))->getSearchSettings();

        $this->assertSame(30, $transport->requests[0]->timeout);
        $this->assertSame(10, $transport->requests[0]->connectTimeout);
    }

    public function testBulkOperationsIsRetriedOn503(): void
    {
        $config = $this->fastRetryConfig();
        $transport = new FakeTransport([
            new HttpResponse(503, 'unavailable'),
            new HttpResponse(200, json_encode([
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                'results' => [
                    ['id' => '1', 'operation' => 'index_products', 'status' => 'created'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = (new SyncV2Sdk($config, $transport))->bulkOperations($this->bulkRequest());

        $this->assertSame(1, $response->successfulOperations);
        $this->assertCount(2, $transport->requests);
        $this->assertSame('POST', $transport->requests[1]->method);
    }

    public function testConfigurationRefreshIsNotRetriedOn503(): void
    {
        $config = $this->fastRetryConfig();
        $transport = new FakeTransport([new HttpResponse(503, 'unavailable')]);

        try {
            (new SyncV2Sdk($config, $transport))->refreshConfiguration();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->statusCode);
            $this->assertSame('unavailable', $e->responseBody);
        }

        $this->assertCount(1, $transport->requests);
    }

    private function fastRetryConfig(): SyncConfigV2
    {
        return new SyncConfigV2(
            appId: self::APP_ID,
            apiUrl: 'https://api.example.com',
            token: 'secret',
            retryPolicy: new RetryPolicy(
                maxAttempts: 3,
                baseDelaySeconds: 0.001,
                maxDelaySeconds: 0.001,
            ),
        );
    }

    private function bulkRequest(): BulkOperationsRequest
    {
        return new BulkOperationsRequest([
            BulkOperation::indexProducts([
                new Product(
                    id: '1',
                    sku: 'SKU-001',
                    pricing: new ProductPricing(10.00, 12.00, 8.00, 10.00),
                    imageUrl: new ImageUrl('https://example.com/s.jpg', 'https://example.com/m.jpg')
                ),
            ]),
        ]);
    }
}
