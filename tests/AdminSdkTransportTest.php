<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests;

use BradSearch\SyncSdk\AdminSdk;
use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Tests\Client\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

class AdminSdkTransportTest extends TestCase
{
    public function testAdminHeaderAndTimeoutsReachTheTransport(): void
    {
        $config = new SyncConfig(
            baseUrl: 'https://api.example.com',
            authToken: 'secret',
            timeout: 30,
            connectTimeout: 10,
        );
        $transport = new FakeTransport([new HttpResponse(200, '{"status":"ok"}')]);

        (new AdminSdk($config, $transport))->deleteIndex('app_x_v1');

        $request = $transport->requests[0];
        $this->assertSame('DELETE', $request->method);
        $this->assertSame('https://api.example.com/api/v2/admin/indices/app_x_v1', $request->url);
        $this->assertSame(30, $request->timeout);
        $this->assertSame(10, $request->connectTimeout);
        $this->assertContains('X-Admin-Action: true', $request->headers);
        $this->assertContains('Authorization: Bearer secret', $request->headers);
    }

    public function testAdminDeleteIsRetriedOn503(): void
    {
        $config = new SyncConfig(
            baseUrl: 'https://api.example.com',
            authToken: 'secret',
            retryPolicy: new RetryPolicy(maxAttempts: 2, baseDelaySeconds: 0.001, maxDelaySeconds: 0.001),
        );
        $transport = new FakeTransport([
            new HttpResponse(503, 'busy'),
            new HttpResponse(200, '{"status":"ok"}'),
        ]);

        $result = (new AdminSdk($config, $transport))->deleteIndex('app_x_v1');

        $this->assertSame(['status' => 'ok'], $result);
        $this->assertCount(2, $transport->requests);
    }
}
