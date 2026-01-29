<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ProductVariantTest extends TestCase
{
    private const VARIANT_ID = 'variant-123';
    private const SKU = 'SKU-ABC-001';
    private const PRICE = 99.99;
    private const BASE_PRICE = 129.99;
    private const PRICE_TAX_EXCLUDED = 82.64;
    private const BASE_PRICE_TAX_EXCLUDED = 107.43;
    private const PRODUCT_URL = 'https://shop.example.com/products/variant-123';
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
    }

    private function createVariant(): ProductVariant
    {
        return new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testConstructorWithRequiredValues(): void
    {
        $imageUrl = $this->createImageUrl();
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $imageUrl
        );

        $this->assertEquals(self::VARIANT_ID, $variant->id);
        $this->assertEquals(self::SKU, $variant->sku);
        $this->assertEquals(self::PRICE, $variant->price);
        $this->assertEquals(self::BASE_PRICE, $variant->basePrice);
        $this->assertEquals(self::PRICE_TAX_EXCLUDED, $variant->priceTaxExcluded);
        $this->assertEquals(self::BASE_PRICE_TAX_EXCLUDED, $variant->basePriceTaxExcluded);
        $this->assertEquals(self::PRODUCT_URL, $variant->productUrl);
        $this->assertSame($imageUrl, $variant->imageUrl);
        $this->assertEquals([], $variant->attrs);
    }

    public function testConstructorWithAttributes(): void
    {
        $attrs = ['size' => 'L', 'color' => 'Red'];
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl(),
            $attrs
        );

        $this->assertEquals($attrs, $variant->attrs);
    }

    public function testExtendsValueObject(): void
    {
        $variant = $this->createVariant();

        $this->assertInstanceOf(ValueObject::class, $variant);
    }

    public function testImplementsJsonSerializable(): void
    {
        $variant = $this->createVariant();

        $this->assertInstanceOf(JsonSerializable::class, $variant);
    }

    public function testJsonSerialize(): void
    {
        $attrs = ['size' => 'M', 'color' => 'Blue'];
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl(),
            $attrs
        );

        $expected = [
            'id' => self::VARIANT_ID,
            'sku' => self::SKU,
            'price' => self::PRICE,
            'basePrice' => self::BASE_PRICE,
            'priceTaxExcluded' => self::PRICE_TAX_EXCLUDED,
            'basePriceTaxExcluded' => self::BASE_PRICE_TAX_EXCLUDED,
            'productUrl' => self::PRODUCT_URL,
            'imageUrl' => [
                'small' => self::SMALL_IMAGE,
                'medium' => self::MEDIUM_IMAGE,
            ],
            'attrs' => $attrs,
        ];

        $this->assertEquals($expected, $variant->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $variant = $this->createVariant();

        $this->assertEquals($variant->jsonSerialize(), $variant->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $variant = $this->createVariant();

        $json = json_encode($variant);
        $decoded = json_decode($json, true);

        $this->assertEquals(self::VARIANT_ID, $decoded['id']);
        $this->assertEquals(self::SKU, $decoded['sku']);
        $this->assertEquals(self::PRICE, $decoded['price']);
    }

    public function testWithIdReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newId = 'new-variant-id';
        $newVariant = $variant->withId($newId);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::VARIANT_ID, $variant->id);
        $this->assertEquals($newId, $newVariant->id);
        $this->assertEquals($variant->sku, $newVariant->sku);
    }

    public function testWithSkuReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newSku = 'NEW-SKU-001';
        $newVariant = $variant->withSku($newSku);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::SKU, $variant->sku);
        $this->assertEquals($newSku, $newVariant->sku);
    }

    public function testWithPriceReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newPrice = 149.99;
        $newVariant = $variant->withPrice($newPrice);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::PRICE, $variant->price);
        $this->assertEquals($newPrice, $newVariant->price);
    }

    public function testWithBasePriceReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newBasePrice = 199.99;
        $newVariant = $variant->withBasePrice($newBasePrice);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::BASE_PRICE, $variant->basePrice);
        $this->assertEquals($newBasePrice, $newVariant->basePrice);
    }

    public function testWithPriceTaxExcludedReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newPriceTaxExcluded = 123.97;
        $newVariant = $variant->withPriceTaxExcluded($newPriceTaxExcluded);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::PRICE_TAX_EXCLUDED, $variant->priceTaxExcluded);
        $this->assertEquals($newPriceTaxExcluded, $newVariant->priceTaxExcluded);
    }

    public function testWithBasePriceTaxExcludedReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newBasePriceTaxExcluded = 165.29;
        $newVariant = $variant->withBasePriceTaxExcluded($newBasePriceTaxExcluded);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::BASE_PRICE_TAX_EXCLUDED, $variant->basePriceTaxExcluded);
        $this->assertEquals($newBasePriceTaxExcluded, $newVariant->basePriceTaxExcluded);
    }

    public function testWithProductUrlReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newUrl = 'https://shop.example.com/products/new-variant';
        $newVariant = $variant->withProductUrl($newUrl);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals(self::PRODUCT_URL, $variant->productUrl);
        $this->assertEquals($newUrl, $newVariant->productUrl);
    }

    public function testWithImageUrlReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newImageUrl = new ImageUrl(
            'https://cdn.example.com/new-small.jpg',
            'https://cdn.example.com/new-medium.jpg'
        );
        $newVariant = $variant->withImageUrl($newImageUrl);

        $this->assertNotSame($variant, $newVariant);
        $this->assertNotSame($variant->imageUrl, $newVariant->imageUrl);
        $this->assertSame($newImageUrl, $newVariant->imageUrl);
    }

    public function testWithAttrsReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newAttrs = ['size' => 'XL', 'color' => 'Green'];
        $newVariant = $variant->withAttrs($newAttrs);

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals([], $variant->attrs);
        $this->assertEquals($newAttrs, $newVariant->attrs);
    }

    public function testWithAddedAttrReturnsNewInstance(): void
    {
        $variant = $this->createVariant();
        $newVariant = $variant->withAddedAttr('size', 'L');

        $this->assertNotSame($variant, $newVariant);
        $this->assertEquals([], $variant->attrs);
        $this->assertEquals(['size' => 'L'], $newVariant->attrs);
    }

    public function testWithAddedAttrAddsToExistingAttrs(): void
    {
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl(),
            ['size' => 'M']
        );
        $newVariant = $variant->withAddedAttr('color', 'Blue');

        $this->assertEquals(['size' => 'M'], $variant->attrs);
        $this->assertEquals(['size' => 'M', 'color' => 'Blue'], $newVariant->attrs);
    }

    public function testChainedWithMethods(): void
    {
        $variant = $this->createVariant()
            ->withPrice(199.99)
            ->withAddedAttr('size', 'L')
            ->withAddedAttr('color', 'Red');

        $this->assertEquals(199.99, $variant->price);
        $this->assertEquals(['size' => 'L', 'color' => 'Red'], $variant->attrs);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The variant ID cannot be empty.');

        new ProductVariant(
            '',
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForWhitespaceOnlyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The variant ID cannot be empty.');

        new ProductVariant(
            '   ',
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForEmptySku(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The variant SKU cannot be empty.');

        new ProductVariant(
            self::VARIANT_ID,
            '',
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForNegativePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The price cannot be negative.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            -10.00,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForNegativeBasePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The basePrice cannot be negative.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            -10.00,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForNegativePriceTaxExcluded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The priceTaxExcluded cannot be negative.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            -10.00,
            self::BASE_PRICE_TAX_EXCLUDED,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForNegativeBasePriceTaxExcluded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The basePriceTaxExcluded cannot be negative.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            -10.00,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForEmptyProductUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product URL cannot be empty.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            '',
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForInvalidProductUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product URL must be a valid HTTP or HTTPS URL.');

        new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            'not-a-url',
            $this->createImageUrl()
        );
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new ProductVariant(
                '',
                self::SKU,
                self::PRICE,
                self::BASE_PRICE,
                self::PRICE_TAX_EXCLUDED,
                self::BASE_PRICE_TAX_EXCLUDED,
                self::PRODUCT_URL,
                $this->createImageUrl()
            );
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('id', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testWithIdValidatesNewValue(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The variant ID cannot be empty.');

        $variant->withId('');
    }

    public function testWithPriceValidatesNewValue(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The price cannot be negative.');

        $variant->withPrice(-1.00);
    }

    public function testWithProductUrlValidatesNewValue(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product URL must be a valid HTTP or HTTPS URL.');

        $variant->withProductUrl('invalid-url');
    }

    public function testAcceptsZeroPrice(): void
    {
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            0.0,
            0.0,
            0.0,
            0.0,
            self::PRODUCT_URL,
            $this->createImageUrl()
        );

        $this->assertEquals(0.0, $variant->price);
        $this->assertEquals(0.0, $variant->basePrice);
    }

    public function testAcceptsHttpUrl(): void
    {
        $variant = new ProductVariant(
            self::VARIANT_ID,
            self::SKU,
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED,
            'http://shop.example.com/product',
            $this->createImageUrl()
        );

        $this->assertEquals('http://shop.example.com/product', $variant->productUrl);
    }
}
