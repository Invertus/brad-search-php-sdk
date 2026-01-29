<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Product;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ImageUrlTest extends TestCase
{
    private const SMALL_URL = 'https://example.com/small.jpg';
    private const MEDIUM_URL = 'https://example.com/medium.jpg';
    private const LARGE_URL = 'https://example.com/large.jpg';
    private const THUMBNAIL_URL = 'https://example.com/thumb.jpg';

    public function testConstructorWithRequiredValues(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->assertEquals(self::SMALL_URL, $imageUrl->small);
        $this->assertEquals(self::MEDIUM_URL, $imageUrl->medium);
        $this->assertNull($imageUrl->large);
        $this->assertNull($imageUrl->thumbnail);
    }

    public function testConstructorWithAllValues(): void
    {
        $imageUrl = new ImageUrl(
            self::SMALL_URL,
            self::MEDIUM_URL,
            self::LARGE_URL,
            self::THUMBNAIL_URL
        );

        $this->assertEquals(self::SMALL_URL, $imageUrl->small);
        $this->assertEquals(self::MEDIUM_URL, $imageUrl->medium);
        $this->assertEquals(self::LARGE_URL, $imageUrl->large);
        $this->assertEquals(self::THUMBNAIL_URL, $imageUrl->thumbnail);
    }

    public function testExtendsValueObject(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->assertInstanceOf(ValueObject::class, $imageUrl);
    }

    public function testImplementsJsonSerializable(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->assertInstanceOf(JsonSerializable::class, $imageUrl);
    }

    public function testJsonSerializeWithRequiredFieldsOnly(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $expected = [
            'small' => self::SMALL_URL,
            'medium' => self::MEDIUM_URL,
        ];

        $this->assertEquals($expected, $imageUrl->jsonSerialize());
    }

    public function testJsonSerializeWithAllFields(): void
    {
        $imageUrl = new ImageUrl(
            self::SMALL_URL,
            self::MEDIUM_URL,
            self::LARGE_URL,
            self::THUMBNAIL_URL
        );

        $expected = [
            'small' => self::SMALL_URL,
            'medium' => self::MEDIUM_URL,
            'large' => self::LARGE_URL,
            'thumbnail' => self::THUMBNAIL_URL,
        ];

        $this->assertEquals($expected, $imageUrl->jsonSerialize());
    }

    public function testJsonSerializeWithOnlyLarge(): void
    {
        $imageUrl = new ImageUrl(
            self::SMALL_URL,
            self::MEDIUM_URL,
            self::LARGE_URL,
            null
        );

        $serialized = $imageUrl->jsonSerialize();

        $this->assertArrayHasKey('large', $serialized);
        $this->assertArrayNotHasKey('thumbnail', $serialized);
    }

    public function testJsonSerializeWithOnlyThumbnail(): void
    {
        $imageUrl = new ImageUrl(
            self::SMALL_URL,
            self::MEDIUM_URL,
            null,
            self::THUMBNAIL_URL
        );

        $serialized = $imageUrl->jsonSerialize();

        $this->assertArrayNotHasKey('large', $serialized);
        $this->assertArrayHasKey('thumbnail', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->assertEquals($imageUrl->jsonSerialize(), $imageUrl->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $imageUrl = new ImageUrl(
            self::SMALL_URL,
            self::MEDIUM_URL,
            self::LARGE_URL,
            self::THUMBNAIL_URL
        );

        $json = json_encode($imageUrl);
        $decoded = json_decode($json, true);

        $this->assertEquals(self::SMALL_URL, $decoded['small']);
        $this->assertEquals(self::MEDIUM_URL, $decoded['medium']);
        $this->assertEquals(self::LARGE_URL, $decoded['large']);
        $this->assertEquals(self::THUMBNAIL_URL, $decoded['thumbnail']);
    }

    public function testWithSmallReturnsNewInstance(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);
        $newUrl = 'https://example.com/new-small.jpg';
        $newImageUrl = $imageUrl->withSmall($newUrl);

        $this->assertNotSame($imageUrl, $newImageUrl);
        $this->assertEquals(self::SMALL_URL, $imageUrl->small);
        $this->assertEquals($newUrl, $newImageUrl->small);
        $this->assertEquals($imageUrl->medium, $newImageUrl->medium);
        $this->assertEquals($imageUrl->large, $newImageUrl->large);
        $this->assertEquals($imageUrl->thumbnail, $newImageUrl->thumbnail);
    }

    public function testWithMediumReturnsNewInstance(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);
        $newUrl = 'https://example.com/new-medium.jpg';
        $newImageUrl = $imageUrl->withMedium($newUrl);

        $this->assertNotSame($imageUrl, $newImageUrl);
        $this->assertEquals(self::MEDIUM_URL, $imageUrl->medium);
        $this->assertEquals($newUrl, $newImageUrl->medium);
        $this->assertEquals($imageUrl->small, $newImageUrl->small);
        $this->assertEquals($imageUrl->large, $newImageUrl->large);
        $this->assertEquals($imageUrl->thumbnail, $newImageUrl->thumbnail);
    }

    public function testWithLargeReturnsNewInstance(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);
        $newImageUrl = $imageUrl->withLarge(self::LARGE_URL);

        $this->assertNotSame($imageUrl, $newImageUrl);
        $this->assertNull($imageUrl->large);
        $this->assertEquals(self::LARGE_URL, $newImageUrl->large);
        $this->assertEquals($imageUrl->small, $newImageUrl->small);
        $this->assertEquals($imageUrl->medium, $newImageUrl->medium);
        $this->assertEquals($imageUrl->thumbnail, $newImageUrl->thumbnail);
    }

    public function testWithLargeCanSetNull(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, self::LARGE_URL);
        $newImageUrl = $imageUrl->withLarge(null);

        $this->assertEquals(self::LARGE_URL, $imageUrl->large);
        $this->assertNull($newImageUrl->large);
    }

    public function testWithThumbnailReturnsNewInstance(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);
        $newImageUrl = $imageUrl->withThumbnail(self::THUMBNAIL_URL);

        $this->assertNotSame($imageUrl, $newImageUrl);
        $this->assertNull($imageUrl->thumbnail);
        $this->assertEquals(self::THUMBNAIL_URL, $newImageUrl->thumbnail);
        $this->assertEquals($imageUrl->small, $newImageUrl->small);
        $this->assertEquals($imageUrl->medium, $newImageUrl->medium);
        $this->assertEquals($imageUrl->large, $newImageUrl->large);
    }

    public function testWithThumbnailCanSetNull(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, null, self::THUMBNAIL_URL);
        $newImageUrl = $imageUrl->withThumbnail(null);

        $this->assertEquals(self::THUMBNAIL_URL, $imageUrl->thumbnail);
        $this->assertNull($newImageUrl->thumbnail);
    }

    public function testChainedWithMethods(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL)
            ->withLarge(self::LARGE_URL)
            ->withThumbnail(self::THUMBNAIL_URL);

        $this->assertEquals(self::SMALL_URL, $imageUrl->small);
        $this->assertEquals(self::MEDIUM_URL, $imageUrl->medium);
        $this->assertEquals(self::LARGE_URL, $imageUrl->large);
        $this->assertEquals(self::THUMBNAIL_URL, $imageUrl->thumbnail);
    }

    public function testThrowsExceptionForEmptySmallUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL cannot be empty.');

        new ImageUrl('', self::MEDIUM_URL);
    }

    public function testThrowsExceptionForWhitespaceOnlySmallUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL cannot be empty.');

        new ImageUrl('   ', self::MEDIUM_URL);
    }

    public function testThrowsExceptionForEmptyMediumUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The medium URL cannot be empty.');

        new ImageUrl(self::SMALL_URL, '');
    }

    public function testThrowsExceptionForEmptyLargeUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The large URL cannot be empty.');

        new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, '');
    }

    public function testThrowsExceptionForEmptyThumbnailUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The thumbnail URL cannot be empty.');

        new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, null, '');
    }

    public function testThrowsExceptionForInvalidSmallUrlProtocol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL must be a valid HTTP or HTTPS URL.');

        new ImageUrl('ftp://example.com/image.jpg', self::MEDIUM_URL);
    }

    public function testThrowsExceptionForInvalidMediumUrlProtocol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The medium URL must be a valid HTTP or HTTPS URL.');

        new ImageUrl(self::SMALL_URL, 'file:///path/to/image.jpg');
    }

    public function testThrowsExceptionForMalformedUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL must be a valid HTTP or HTTPS URL.');

        new ImageUrl('not-a-url', self::MEDIUM_URL);
    }

    public function testThrowsExceptionForInvalidImageExtension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL must have a valid image extension');

        new ImageUrl('https://example.com/file.pdf', self::MEDIUM_URL);
    }

    public function testExceptionContainsArgumentNameForSmall(): void
    {
        try {
            new ImageUrl('', self::MEDIUM_URL);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('small', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testExceptionContainsArgumentNameForMedium(): void
    {
        try {
            new ImageUrl(self::SMALL_URL, 'invalid');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('medium', $e->argumentName);
            $this->assertEquals('invalid', $e->invalidValue);
        }
    }

    public function testExceptionContainsArgumentNameForLarge(): void
    {
        try {
            new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, 'invalid');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('large', $e->argumentName);
            $this->assertEquals('invalid', $e->invalidValue);
        }
    }

    public function testExceptionContainsArgumentNameForThumbnail(): void
    {
        try {
            new ImageUrl(self::SMALL_URL, self::MEDIUM_URL, null, 'invalid');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('thumbnail', $e->argumentName);
            $this->assertEquals('invalid', $e->invalidValue);
        }
    }

    public function testWithSmallValidatesNewValue(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The small URL cannot be empty.');

        $imageUrl->withSmall('');
    }

    public function testWithMediumValidatesNewValue(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The medium URL must be a valid HTTP or HTTPS URL.');

        $imageUrl->withMedium('not-a-url');
    }

    public function testWithLargeValidatesNewValue(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The large URL must have a valid image extension');

        $imageUrl->withLarge('https://example.com/file.txt');
    }

    public function testWithThumbnailValidatesNewValue(): void
    {
        $imageUrl = new ImageUrl(self::SMALL_URL, self::MEDIUM_URL);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The thumbnail URL cannot be empty.');

        $imageUrl->withThumbnail('');
    }

    /**
     * @dataProvider validImageExtensionsProvider
     */
    public function testAcceptsValidImageExtensions(string $extension): void
    {
        $url = "https://example.com/image.{$extension}";
        $imageUrl = new ImageUrl($url, $url);

        $this->assertEquals($url, $imageUrl->small);
        $this->assertEquals($url, $imageUrl->medium);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validImageExtensionsProvider(): array
    {
        return [
            'jpg' => ['jpg'],
            'jpeg' => ['jpeg'],
            'png' => ['png'],
            'gif' => ['gif'],
            'webp' => ['webp'],
            'svg' => ['svg'],
            'JPG uppercase' => ['JPG'],
            'PNG uppercase' => ['PNG'],
        ];
    }

    /**
     * @dataProvider invalidImageExtensionsProvider
     */
    public function testRejectsInvalidImageExtensions(string $extension): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a valid image extension');

        new ImageUrl("https://example.com/file.{$extension}", self::MEDIUM_URL);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidImageExtensionsProvider(): array
    {
        return [
            'pdf' => ['pdf'],
            'txt' => ['txt'],
            'doc' => ['doc'],
            'html' => ['html'],
            'exe' => ['exe'],
        ];
    }

    public function testAcceptsHttpsUrls(): void
    {
        $httpsUrl = 'https://example.com/image.jpg';
        $imageUrl = new ImageUrl($httpsUrl, $httpsUrl);

        $this->assertEquals($httpsUrl, $imageUrl->small);
    }

    public function testAcceptsHttpUrls(): void
    {
        $httpUrl = 'http://example.com/image.jpg';
        $imageUrl = new ImageUrl($httpUrl, $httpUrl);

        $this->assertEquals($httpUrl, $imageUrl->small);
    }

    public function testAcceptsUrlsWithQueryParameters(): void
    {
        $url = 'https://example.com/image.jpg?size=small&quality=80';
        $imageUrl = new ImageUrl($url, $url);

        $this->assertEquals($url, $imageUrl->small);
    }

    public function testAcceptsUrlsWithFragment(): void
    {
        $url = 'https://example.com/image.jpg#section';
        $imageUrl = new ImageUrl($url, $url);

        $this->assertEquals($url, $imageUrl->small);
    }

    public function testAcceptsUrlsWithoutExtension(): void
    {
        $url = 'https://cdn.example.com/images/12345';
        $imageUrl = new ImageUrl($url, $url);

        $this->assertEquals($url, $imageUrl->small);
    }

    public function testAcceptsUrlsWithPort(): void
    {
        $url = 'https://example.com:8080/image.jpg';
        $imageUrl = new ImageUrl($url, $url);

        $this->assertEquals($url, $imageUrl->small);
    }

    public function testMatchesApiImageUrlStructure(): void
    {
        $imageUrl = new ImageUrl(
            'https://shop.com/products/small.jpg',
            'https://shop.com/products/medium.jpg'
        );

        $serialized = $imageUrl->jsonSerialize();

        $this->assertArrayHasKey('small', $serialized);
        $this->assertArrayHasKey('medium', $serialized);
        $this->assertIsString($serialized['small']);
        $this->assertIsString($serialized['medium']);
    }

    public function testMatchesApiImageUrlStructureWithOptionalFields(): void
    {
        $imageUrl = new ImageUrl(
            'https://shop.com/products/small.jpg',
            'https://shop.com/products/medium.jpg',
            'https://shop.com/products/large.jpg',
            'https://shop.com/products/thumb.jpg'
        );

        $serialized = $imageUrl->jsonSerialize();

        $this->assertArrayHasKey('small', $serialized);
        $this->assertArrayHasKey('medium', $serialized);
        $this->assertArrayHasKey('large', $serialized);
        $this->assertArrayHasKey('thumbnail', $serialized);
        $this->assertIsString($serialized['large']);
        $this->assertIsString($serialized['thumbnail']);
    }

    public function testJsonOutputMatchesExpectedApiFormat(): void
    {
        $imageUrl = new ImageUrl(
            'https://shop.example.com/images/product-123-small.jpg',
            'https://shop.example.com/images/product-123-medium.jpg'
        );

        $json = json_encode($imageUrl, JSON_PRETTY_PRINT);
        $expected = <<<JSON
{
    "small": "https:\/\/shop.example.com\/images\/product-123-small.jpg",
    "medium": "https:\/\/shop.example.com\/images\/product-123-medium.jpg"
}
JSON;

        $this->assertJsonStringEqualsJsonString($expected, $json);
    }
}
