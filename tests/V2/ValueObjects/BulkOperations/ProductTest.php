<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    private const PRODUCT_ID = 'prod-123';
    private const PRICE = 99.99;
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
    }

    private function createProduct(): Product
    {
        return new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl()
        );
    }

    private function createVariant(): ProductVariant
    {
        return new ProductVariant(
            'variant-1',
            'SKU-001',
            99.99,
            129.99,
            82.64,
            107.43,
            'https://shop.example.com/variant-1',
            $this->createImageUrl(),
            ['size' => 'M']
        );
    }

    public function testConstructorWithRequiredValues(): void
    {
        $imageUrl = $this->createImageUrl();
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $imageUrl
        );

        $this->assertEquals(self::PRODUCT_ID, $product->id);
        $this->assertEquals(self::PRICE, $product->price);
        $this->assertSame($imageUrl, $product->imageUrl);
        $this->assertEquals([], $product->variants);
        $this->assertEquals([], $product->additionalFields);
    }

    public function testConstructorWithVariants(): void
    {
        $variant = $this->createVariant();
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [$variant]
        );

        $this->assertCount(1, $product->variants);
        $this->assertSame($variant, $product->variants[0]);
    }

    public function testConstructorWithAdditionalFields(): void
    {
        $fields = [
            'name_lt-LT' => 'Produkto pavadinimas',
            'brand_lt-LT' => 'Markė',
            'sku' => 'SKU-123',
        ];
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [],
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
            'price' => self::PRICE,
            'imageUrl' => [
                'small' => self::SMALL_IMAGE,
                'medium' => self::MEDIUM_IMAGE,
            ],
        ];

        $this->assertEquals($expected, $product->jsonSerialize());
    }

    public function testJsonSerializeWithAdditionalFields(): void
    {
        $fields = [
            'name_lt-LT' => 'Produktas',
            'sku' => 'SKU-123',
        ];
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [],
            $fields
        );

        $serialized = $product->jsonSerialize();

        $this->assertEquals(self::PRODUCT_ID, $serialized['id']);
        $this->assertEquals(self::PRICE, $serialized['price']);
        $this->assertEquals('Produktas', $serialized['name_lt-LT']);
        $this->assertEquals('SKU-123', $serialized['sku']);
        $this->assertArrayNotHasKey('variants', $serialized);
    }

    public function testJsonSerializeWithVariants(): void
    {
        $variant = $this->createVariant();
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [$variant]
        );

        $serialized = $product->jsonSerialize();

        $this->assertArrayHasKey('variants', $serialized);
        $this->assertCount(1, $serialized['variants']);
        $this->assertEquals('variant-1', $serialized['variants'][0]['id']);
    }

    public function testJsonSerializeOmitsEmptyVariants(): void
    {
        $product = $this->createProduct();

        $serialized = $product->jsonSerialize();

        $this->assertArrayNotHasKey('variants', $serialized);
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

    public function testWithPriceReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $newPrice = 149.99;
        $newProduct = $product->withPrice($newPrice);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals(self::PRICE, $product->price);
        $this->assertEquals($newPrice, $newProduct->price);
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

    public function testWithVariantsReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $variants = [$this->createVariant()];
        $newProduct = $product->withVariants($variants);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals([], $product->variants);
        $this->assertCount(1, $newProduct->variants);
    }

    public function testWithAddedVariantReturnsNewInstance(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant();
        $newProduct = $product->withAddedVariant($variant);

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals([], $product->variants);
        $this->assertCount(1, $newProduct->variants);
        $this->assertSame($variant, $newProduct->variants[0]);
    }

    public function testWithAddedVariantAddsToExistingVariants(): void
    {
        $variant1 = $this->createVariant();
        $variant2 = new ProductVariant(
            'variant-2',
            'SKU-002',
            149.99,
            179.99,
            123.97,
            148.76,
            'https://shop.example.com/variant-2',
            $this->createImageUrl(),
            ['size' => 'L']
        );

        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [$variant1]
        );
        $newProduct = $product->withAddedVariant($variant2);

        $this->assertCount(1, $product->variants);
        $this->assertCount(2, $newProduct->variants);
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
        $newProduct = $product->withAddedField('sku', 'SKU-123');

        $this->assertNotSame($product, $newProduct);
        $this->assertEquals([], $product->additionalFields);
        $this->assertEquals(['sku' => 'SKU-123'], $newProduct->additionalFields);
    }

    public function testWithAddedFieldAddsToExistingFields(): void
    {
        $product = new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            [],
            ['name_lt-LT' => 'Produktas']
        );
        $newProduct = $product->withAddedField('sku', 'SKU-123');

        $this->assertEquals(['name_lt-LT' => 'Produktas'], $product->additionalFields);
        $this->assertEquals(
            ['name_lt-LT' => 'Produktas', 'sku' => 'SKU-123'],
            $newProduct->additionalFields
        );
    }

    public function testChainedWithMethods(): void
    {
        $variant = $this->createVariant();
        $product = $this->createProduct()
            ->withPrice(199.99)
            ->withAddedVariant($variant)
            ->withAddedField('name_lt-LT', 'Produktas')
            ->withAddedField('sku', 'SKU-123');

        $this->assertEquals(199.99, $product->price);
        $this->assertCount(1, $product->variants);
        $this->assertEquals('Produktas', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('SKU-123', $product->additionalFields['sku']);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product ID cannot be empty.');

        new Product(
            '',
            self::PRICE,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForWhitespaceOnlyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product ID cannot be empty.');

        new Product(
            '   ',
            self::PRICE,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForNegativePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product price cannot be negative.');

        new Product(
            self::PRODUCT_ID,
            -10.00,
            $this->createImageUrl()
        );
    }

    public function testThrowsExceptionForInvalidVariantType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant at index 0 must be an instance of ProductVariant.');

        new Product(
            self::PRODUCT_ID,
            self::PRICE,
            $this->createImageUrl(),
            ['not-a-variant']
        );
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new Product(
                '',
                self::PRICE,
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

    public function testWithPriceValidatesNewValue(): void
    {
        $product = $this->createProduct();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product price cannot be negative.');

        $product->withPrice(-1.00);
    }

    public function testWithVariantsValidatesItems(): void
    {
        $product = $this->createProduct();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant at index 0 must be an instance of ProductVariant.');

        $product->withVariants(['invalid']);
    }

    public function testAcceptsZeroPrice(): void
    {
        $product = new Product(
            self::PRODUCT_ID,
            0.0,
            $this->createImageUrl()
        );

        $this->assertEquals(0.0, $product->price);
    }

    public function testJsonSerializeMatchesDarboDrabuziaiExample(): void
    {
        $variant = new ProductVariant(
            '12345-M-RED',
            'SKU-12345-M-RED',
            99.99,
            129.99,
            82.64,
            107.43,
            'https://shop.lt/produktas-12345?size=M&color=RED',
            new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ),
            ['size' => 'M', 'color' => 'RED']
        );

        $product = new Product(
            '12345',
            99.99,
            new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ),
            [$variant],
            [
                'name_lt-LT' => 'Darbo drabužis Premium',
                'brand_lt-LT' => 'WorkWear Pro',
                'sku' => 'SKU-12345',
                'description_lt-LT' => 'Aukštos kokybės darbo drabužis',
                'categories_lt-LT' => ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
            ]
        );

        $serialized = $product->jsonSerialize();

        $this->assertEquals('12345', $serialized['id']);
        $this->assertEquals(99.99, $serialized['price']);
        $this->assertEquals('Darbo drabužis Premium', $serialized['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $serialized['brand_lt-LT']);
        $this->assertEquals('SKU-12345', $serialized['sku']);
        $this->assertArrayHasKey('imageUrl', $serialized);
        $this->assertArrayHasKey('variants', $serialized);
        $this->assertCount(1, $serialized['variants']);
        $this->assertEquals('12345-M-RED', $serialized['variants'][0]['id']);
        $this->assertEquals(['size' => 'M', 'color' => 'RED'], $serialized['variants'][0]['attrs']);
    }
}
