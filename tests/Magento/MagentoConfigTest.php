<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Magento;

use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;
use BradSearch\SyncSdk\Magento\MagentoConfig;
use PHPUnit\Framework\TestCase;

class MagentoConfigTest extends TestCase
{
    public function testValidConfig(): void
    {
        $config = new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            bearerToken: 'test-token',
            timeout: 60,
            verifySSL: false,
            defaultPageSize: 50
        );

        $this->assertSame('https://example.com/graphql', $config->graphqlUrl);
        $this->assertSame('test-token', $config->bearerToken);
        $this->assertSame(60, $config->timeout);
        $this->assertFalse($config->verifySSL);
        $this->assertSame(50, $config->defaultPageSize);
    }

    public function testDefaultValues(): void
    {
        $config = new MagentoConfig(graphqlUrl: 'https://example.com/graphql');

        $this->assertNull($config->bearerToken);
        $this->assertSame(30, $config->timeout);
        $this->assertTrue($config->verifySSL);
        $this->assertSame(100, $config->defaultPageSize);
    }

    public function testEmptyGraphqlUrlThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('GraphQL URL cannot be empty');

        new MagentoConfig(graphqlUrl: '');
    }

    public function testInvalidGraphqlUrlThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('GraphQL URL must be a valid URL');

        new MagentoConfig(graphqlUrl: 'not-a-url');
    }

    public function testZeroTimeoutThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            timeout: 0
        );
    }

    public function testNegativeTimeoutThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            timeout: -1
        );
    }

    public function testZeroPageSizeThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('Default page size must be greater than 0');

        new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            defaultPageSize: 0
        );
    }

    public function testPageSizeOverLimitThrows(): void
    {
        $this->expectException(InvalidFieldConfigException::class);
        $this->expectExceptionMessage('Default page size cannot exceed 300');

        new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            defaultPageSize: 301
        );
    }

    public function testMaxPageSizeAllowed(): void
    {
        $config = new MagentoConfig(
            graphqlUrl: 'https://example.com/graphql',
            defaultPageSize: 300
        );

        $this->assertSame(300, $config->defaultPageSize);
    }
}
