<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Config;

use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Config\SyncConfigV2;
use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;
use BradSearch\SyncSdk\Magento\MagentoConfig;
use PHPUnit\Framework\TestCase;

class ConnectTimeoutConfigTest extends TestCase
{
    private const APP_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testSyncConfigDefaults(): void
    {
        $config = new SyncConfig('https://api.example.com', 'token');

        $this->assertSame(30, $config->timeout);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertSame(3, $config->retryPolicy->maxAttempts);
    }

    public function testSyncConfigPositionalCallersStillWork(): void
    {
        $config = new SyncConfig('https://api.example.com', 'token', 45, false);

        $this->assertSame(45, $config->timeout);
        $this->assertFalse($config->verifySSL);
        $this->assertSame(10, $config->connectTimeout);
    }

    public function testSyncConfigRejectsNonPositiveConnectTimeout(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('Connect timeout must be greater than 0');

        new SyncConfig('https://api.example.com', 'token', connectTimeout: 0);
    }

    public function testSyncConfigV2Defaults(): void
    {
        $config = new SyncConfigV2(self::APP_ID, 'https://api.example.com', 'token');

        $this->assertNull($config->targetIndex);
        $this->assertSame(30, $config->timeout);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertSame(3, $config->retryPolicy->maxAttempts);
    }

    public function testSyncConfigV2AcceptsTimeoutsAndPolicy(): void
    {
        $config = new SyncConfigV2(
            appId: self::APP_ID,
            apiUrl: 'https://api.example.com',
            token: 'token',
            targetIndex: 'idx_v2',
            timeout: 120,
            connectTimeout: 5,
            retryPolicy: RetryPolicy::none(),
        );

        $this->assertSame('idx_v2', $config->targetIndex);
        $this->assertSame(120, $config->timeout);
        $this->assertSame(5, $config->connectTimeout);
        $this->assertSame(1, $config->retryPolicy->maxAttempts);
    }

    public function testSyncConfigV2RejectsNonPositiveTimeouts(): void
    {
        $this->expectException(InvalidFieldConfigException::class);

        new SyncConfigV2(self::APP_ID, 'https://api.example.com', 'token', timeout: -1);
    }

    public function testMagentoConfigHasConnectTimeout(): void
    {
        $config = new MagentoConfig('https://shop.example.com/graphql');
        $this->assertSame(10, $config->connectTimeout);

        $custom = new MagentoConfig('https://shop.example.com/graphql', connectTimeout: 3);
        $this->assertSame(3, $custom->connectTimeout);
    }

    public function testMagentoConfigRejectsNonPositiveConnectTimeout(): void
    {
        $this->expectException(InvalidFieldConfigException::class);

        new MagentoConfig('https://shop.example.com/graphql', connectTimeout: 0);
    }
}
