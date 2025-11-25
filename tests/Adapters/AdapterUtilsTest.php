<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\AdapterUtils;
use PHPUnit\Framework\TestCase;

class AdapterUtilsTest extends TestCase
{
    public function testBuildImageUrlWithBothUrls(): void
    {
        $result = AdapterUtils::buildImageUrl(
            'https://example.com/small.jpg',
            'https://example.com/medium.jpg'
        );

        $this->assertSame([
            'small' => 'https://example.com/small.jpg',
            'medium' => 'https://example.com/medium.jpg',
        ], $result);
    }

    public function testBuildImageUrlWithOnlySmall(): void
    {
        $result = AdapterUtils::buildImageUrl('https://example.com/small.jpg', null);

        $this->assertSame(['small' => 'https://example.com/small.jpg'], $result);
    }

    public function testBuildImageUrlWithOnlyMedium(): void
    {
        $result = AdapterUtils::buildImageUrl(null, 'https://example.com/medium.jpg');

        $this->assertSame(['medium' => 'https://example.com/medium.jpg'], $result);
    }

    public function testBuildImageUrlWithNoUrls(): void
    {
        $result = AdapterUtils::buildImageUrl(null, null);

        $this->assertSame([], $result);
    }

    public function testBuildImageUrlIgnoresEmptyStrings(): void
    {
        $result = AdapterUtils::buildImageUrl('', '');

        $this->assertSame([], $result);
    }

    public function testExtractNestedImageUrl(): void
    {
        $data = [
            'image' => ['url' => 'https://example.com/image.jpg', 'label' => 'Test'],
        ];

        $result = AdapterUtils::extractNestedImageUrl($data, 'image');

        $this->assertSame('https://example.com/image.jpg', $result);
    }

    public function testExtractNestedImageUrlReturnsNullWhenMissing(): void
    {
        $data = ['other' => 'value'];

        $result = AdapterUtils::extractNestedImageUrl($data, 'image');

        $this->assertNull($result);
    }

    public function testExtractNestedImageUrlReturnsNullWhenUrlEmpty(): void
    {
        $data = ['image' => ['url' => '', 'label' => 'Test']];

        $result = AdapterUtils::extractNestedImageUrl($data, 'image');

        $this->assertNull($result);
    }

    public function testExtractNestedImageUrlReturnsNullWhenUrlNotString(): void
    {
        $data = ['image' => ['url' => 123, 'label' => 'Test']];

        $result = AdapterUtils::extractNestedImageUrl($data, 'image');

        $this->assertNull($result);
    }

    public function testExtractDirectUrl(): void
    {
        $data = ['productUrl' => 'https://example.com/product'];

        $result = AdapterUtils::extractDirectUrl($data, 'productUrl');

        $this->assertSame('https://example.com/product', $result);
    }

    public function testExtractDirectUrlReturnsNullWhenMissing(): void
    {
        $data = ['other' => 'value'];

        $result = AdapterUtils::extractDirectUrl($data, 'productUrl');

        $this->assertNull($result);
    }

    public function testGetNestedValue(): void
    {
        $data = [
            'price_range' => [
                'minimum_price' => [
                    'final_price' => ['value' => 99.99]
                ]
            ]
        ];

        $result = AdapterUtils::getNestedValue(
            $data,
            ['price_range', 'minimum_price', 'final_price', 'value']
        );

        $this->assertSame(99.99, $result);
    }

    public function testGetNestedValueReturnsDefaultWhenMissing(): void
    {
        $data = ['other' => 'value'];

        $result = AdapterUtils::getNestedValue(
            $data,
            ['price_range', 'minimum_price'],
            'default'
        );

        $this->assertSame('default', $result);
    }

    public function testGetNestedValueReturnsNullByDefault(): void
    {
        $data = [];

        $result = AdapterUtils::getNestedValue($data, ['missing', 'path']);

        $this->assertNull($result);
    }

    public function testToStringWithScalar(): void
    {
        $this->assertSame('123', AdapterUtils::toString(123));
        $this->assertSame('45.67', AdapterUtils::toString(45.67));
        $this->assertSame('hello', AdapterUtils::toString('hello'));
        $this->assertSame('1', AdapterUtils::toString(true));
        $this->assertSame('', AdapterUtils::toString(false));
    }

    public function testToStringWithNull(): void
    {
        $this->assertSame('', AdapterUtils::toString(null));
    }

    public function testToStringWithArray(): void
    {
        $this->assertSame('', AdapterUtils::toString(['array']));
    }

    public function testBuildError(): void
    {
        $result = AdapterUtils::buildError(
            'transformation_error',
            5,
            'prod-123',
            'Something went wrong',
            'ValidationException'
        );

        $expected = [
            'type' => 'transformation_error',
            'product_index' => 5,
            'product_id' => 'prod-123',
            'message' => 'Something went wrong',
            'exception' => 'ValidationException',
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildErrorWithNullException(): void
    {
        $result = AdapterUtils::buildError(
            'invalid_structure',
            0,
            '',
            'Invalid item'
        );

        $this->assertSame('invalid_structure', $result['type']);
        $this->assertNull($result['exception']);
    }
}
