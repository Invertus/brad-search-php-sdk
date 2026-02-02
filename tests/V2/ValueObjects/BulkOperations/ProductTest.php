<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    private const PRODUCT_ID = 'prod-123';
    private const SKU = 'SKU-123';
    private const PRICE = 99.99;
    private const BASE_PRICE = 129.99;
    private const PRICE_TAX_EXCLUDED = 82.64;
    private const BASE_PRICE_TAX_EXCLUDED = 107.43;
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
    }

    private function createPricing(): ProductPricing
    {
        return new ProductPricing(
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED
        );
    }

    private function createProduct(): Product
    {
        return new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    public function testConstructorWithRequiredValues(): void
    {
        $imageUrl = $this->createImageUrl();
        $pricing = $this->createPricing();
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $pricing,
            $imageUrl
        );

        $this->assertEquals(self::PRODUCT_ID, $product->id);
        $this->assertEquals(self::SKU, $product->sku);
        $this->assertSame($pricing, $product->pricing);
        $this->assertSame($imageUrl, $product->imageUrl);
        $this->assertNull($product->inStock);
        $this->assertNull($product->isNew);
        $this->assertEquals([], $product->additionalFields);
    }

    public function testConstructorWithBooleanFields(): void
    {
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl(),
            true,
            false
        );

        $this->assertTrue($product->inStock);
        $this->assertFalse($product->isNew);
    }

    public function testConstructorWithAdditionalFields(): void
    {
        $fields = [
            'name_lt-LT' => 'Produkto pavadinimas',
            'brand_lt-LT' => 'Markė',
        ];
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl(),
            null,
            null,
            $fields
        );

        $this->assertEquals($fields, $product->additionalFields);
    }

    public function testExtendsValueObject(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(ValueObject::class, $product);
    }

    public function testImplementsJsonSerializable(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(JsonSerializable::class, $product);
    }

    public function testJsonSerializeWithRequiredFieldsOnly(): void
    {
        $product = $this->createProduct();

        $expected = [
            'id' => self::PRODUCT_ID,
            'sku' => self::SKU,
            'price' => self::PRICE,
            'basePrice' => self::BASE_PRICE,
            'priceTaxExcluded' => self::PRICE_TAX_EXCLUDED,
            'basePriceTaxExcluded' => self::BASE_PRICE_TAX_EXCLUDED,
            'imageUrl' => [
                'small' => self::SMALL_IMAGE,
                'medium' => self::MEDIUM_IMAGE,
            ],
        ];

        $this->assertEquals($expected, $product->jsonSerialize());
    }

    public function testJsonSerializeWithBooleanFields(): void
    {
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl(),
            true,
            false
        );

        $serialized = $product->jsonSerialize();

        $this->assertTrue($serialized['inStock']);
        $this->assertFalse($serialized['isNew']);
    }

    public function testJsonSerializeOmitsNullBooleanFields(): void
    {
        $product = $this->createProduct();

        $serialized = $product->jsonSerialize();

        $this->assertArrayNotHasKey('inStock', $serialized);
        $this->assertArrayNotHasKey('isNew', $serialized);
    }

    public function testJsonSerializeWithAdditionalFields(): void
    {
        $fields = [
            'name_lt-LT' => 'Produktas',
        ];
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl(),
            null,
            null,
            $fields
        );

        $serialized = $product->jsonSerialize();

        $this->assertEquals(self::PRODUCT_ID, $serialized['id']);
        $this->assertEquals(self::SKU, $serialized['sku']);
        $this->assertEquals(self::PRICE, $serialized['price']);
        $this->assertEquals('Produktas', $serialized['name_lt-LT']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $product = $this->createProduct();

        $this->assertEquals($product->jsonSerialize(), $product->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $product = $this->createProduct();

        $json = json_encode($product);
        $decoded = json_decode($json, true);

        $this->assertEquals(self::PRODUCT_ID, $decoded['id']);
        $this->assertEquals(self::SKU, $decoded['sku']);
        $this->assertEquals(self::PRICE, $decoded['price']);
    }

    public function testWithIdReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newId = 'new-product-id';
        $newProduct = $product->withId($newId);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals(self::PRODUCT_ID, $product->id);
        $this->assertEquals($newId, $newProduct->id);
    }

    public function testWithSkuReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newSku = 'NEW-SKU-456';
        $newProduct = $product->withSku($newSku);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals(self::SKU, $product->sku);
        $this->assertEquals($newSku, $newProduct->sku);
    }

    public function testWithPricingReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newPricing = new ProductPricing(149.99, 179.99, 123.97, 148.76);
        $newProduct = $product->withPricing($newPricing);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals(self::PRICE, $product->pricing->price);
        $this->assertEquals(149.99, $newProduct->pricing->price);
    }

    public function testWithImageUrlReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newImageUrl = new ImageUrl(
            'https://cdn.example.com/new-small.jpg',
            'https://cdn.example.com/new-medium.jpg'
        );
        $newProduct = $product->withImageUrl($newImageUrl);

        $this->assertNotSame($product, $newProduct);
        $this->assertSame($newImageUrl, $newProduct->imageUrl);
    }

    public function testWithInStockReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newProduct = $product->withInStock(true);

        $this->assertNotSame($product, $newProduct);
        $this->assertNull($product->inStock);
        $this->assertTrue($newProduct->inStock);
    }

    public function testWithIsNewReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newProduct = $product->withIsNew(true);

        $this->assertNotSame($product, $newProduct);
        $this->assertNull($product->isNew);
        $this->assertTrue($newProduct->isNew);
    }

    public function testWithAdditionalFieldsReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $fields = ['name_lt-LT' => 'Produktas'];
        $newProduct = $product->withAdditionalFields($fields);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals([], $product->additionalFields);
        $this->assertEquals($fields, $newProduct->additionalFields);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newProduct = $product->withAddedField('name_lt-LT', 'Produktas');

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals([], $product->additionalFields);
        $this->assertEquals(['name_lt-LT' => 'Produktas'], $newProduct->additionalFields);
    }

    public function testWithAddedFieldAddsToExistingFields(): void
    {
        $product = new Product(
            self::PRODUCT_ID,
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl(),
            null,
            null,
            ['name_lt-LT' => 'Produktas']
        );
        $newProduct = $product->withAddedField('brand_lt-LT', 'Markė');

        $this->assertEquals(['name_lt-LT' => 'Produktas'], $product->additionalFields);
        $this->assertEquals(
            ['name_lt-LT' => 'Produktas', 'brand_lt-LT' => 'Markė'],
            $newProduct->additionalFields
        );
    }

    public function testChainedWithMethods(): void
    {
        $product = $this->createProduct()
            ->withPricing(new ProductPricing(199.99, 249.99, 165.29, 206.61))
            ->withInStock(true)
            ->withIsNew(false)
            ->withAddedField('name_lt-LT', 'Produktas')
            ->withAddedField('brand_lt-LT', 'Markė');

        $this->assertEquals(199.99, $product->pricing->price);
        $this->assertTrue($product->inStock);
        $this->assertFalse($product->isNew);
        $this->assertEquals('Produktas', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('Markė', $product->additionalFields['brand_lt-LT']);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product ID cannot be empty.');

        new Product(
            '',
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForWhitespaceOnlyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product ID cannot be empty.');

        new Product(
            '   ',
            self::SKU,
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForEmptySku(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product SKU cannot be empty.');

        new Product(
            self::PRODUCT_ID,
            '',
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForWhitespaceOnlySku(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product SKU cannot be empty.');

        new Product(
            self::PRODUCT_ID,
            '   ',
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new Product(
                '',
                self::SKU,
                $this->createPricing(),
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
        $product = $this->createProduct();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product ID cannot be empty.');

        $product->withId('');
    }

    public function testWithSkuValidatesNewValue(): void
    {
        $product = $this->createProduct();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product SKU cannot be empty.');

        $product->withSku('');
    }

    public function testJsonSerializeMatchesDarboDrabuziaiExample(): void
    {
        $product = new Product(
            '12345',
            'SKU-12345',
            new ProductPricing(99.99, 129.99, 82.64, 107.43),
            new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ),
            null,
            null,
            [
                'name_lt-LT' => 'Darbo drabužis Premium',
                'brand_lt-LT' => 'WorkWear Pro',
                'description_lt-LT' => 'Aukštos kokybės darbo drabužis',
                'categories_lt-LT' => ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
            ]
        );

        $serialized = $product->jsonSerialize();

        $this->assertEquals('12345', $serialized['id']);
        $this->assertEquals('SKU-12345', $serialized['sku']);
        $this->assertEquals(99.99, $serialized['price']);
        $this->assertEquals(129.99, $serialized['basePrice']);
        $this->assertEquals(82.64, $serialized['priceTaxExcluded']);
        $this->assertEquals(107.43, $serialized['basePriceTaxExcluded']);
        $this->assertEquals('Darbo drabužis Premium', $serialized['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $serialized['brand_lt-LT']);
        $this->assertArrayHasKey('imageUrl', $serialized);
    }
}
