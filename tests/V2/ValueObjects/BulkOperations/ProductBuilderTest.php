<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use PHPUnit\Framework\TestCase;

class ProductBuilderTest extends TestCase
{
    private const PRODUCT_ID = 'prod-123';
    private const SKU = 'SKU-12345';
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

    public function testBuildWithRequiredFields(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->build();

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals(self::PRODUCT_ID, $product->id);
        $this->assertEquals(self::SKU, $product->sku);
        $this->assertEquals(self::PRICE, $product->pricing->price);
    }

    public function testBuildWithBooleanFields(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->inStock(true)
            ->isNew(false)
            ->build();

        $this->assertTrue($product->inStock);
        $this->assertFalse($product->isNew);
    }

    public function testBuildWithCustomField(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->field('custom_field', 'custom_value')
            ->build();

        $this->assertEquals('custom_value', $product->additionalFields['custom_field']);
    }

    public function testBuildWithLocalizedName(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->name('Produkto pavadinimas', 'lt-LT')
            ->build();

        $this->assertEquals('Produkto pavadinimas', $product->additionalFields['name_lt-LT']);
    }

    public function testBuildWithLocalizedBrand(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->brand('Markė', 'lt-LT')
            ->build();

        $this->assertEquals('Markė', $product->additionalFields['brand_lt-LT']);
    }

    public function testBuildWithLocalizedDescription(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->description('Produkto aprašymas', 'lt-LT')
            ->build();

        $this->assertEquals('Produkto aprašymas', $product->additionalFields['description_lt-LT']);
    }

    public function testBuildWithLocalizedCategories(): void
    {
        $categories = ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'];
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->categories($categories, 'lt-LT')
            ->build();

        $this->assertEquals($categories, $product->additionalFields['categories_lt-LT']);
    }

    public function testBuildWithAllLocalizedFields(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->name('Darbo drabužis Premium', 'lt-LT')
            ->brand('WorkWear Pro', 'lt-LT')
            ->description('Aukštos kokybės darbo drabužis', 'lt-LT')
            ->categories(['Darbo drabužiai', 'Darbo drabužiai > Kelnės'], 'lt-LT')
            ->build();

        $this->assertEquals('Darbo drabužis Premium', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $product->additionalFields['brand_lt-LT']);
        $this->assertEquals('Aukštos kokybės darbo drabužis', $product->additionalFields['description_lt-LT']);
        $this->assertEquals(
            ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
            $product->additionalFields['categories_lt-LT']
        );
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new ProductBuilder();

        $this->assertSame($builder, $builder->id(self::PRODUCT_ID));
        $this->assertSame($builder, $builder->sku(self::SKU));
        $this->assertSame($builder, $builder->pricing($this->createPricing()));
        $this->assertSame($builder, $builder->imageUrl($this->createImageUrl()));
        $this->assertSame($builder, $builder->inStock(true));
        $this->assertSame($builder, $builder->isNew(false));
        $this->assertSame($builder, $builder->field('key', 'value'));
        $this->assertSame($builder, $builder->name('Name', 'lt-LT'));
        $this->assertSame($builder, $builder->brand('Brand', 'lt-LT'));
        $this->assertSame($builder, $builder->description('Desc', 'lt-LT'));
        $this->assertSame($builder, $builder->categories([], 'lt-LT'));
    }

    public function testThrowsExceptionForMissingId(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID is required.');

        $builder
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->build();
    }

    public function testThrowsExceptionForMissingSku(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product SKU is required.');

        $builder
            ->id(self::PRODUCT_ID)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->build();
    }

    public function testThrowsExceptionForMissingPricing(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product pricing is required.');

        $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->imageUrl($this->createImageUrl())
            ->build();
    }

    public function testThrowsExceptionForMissingImageUrl(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product image URL is required.');

        $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->build();
    }

    public function testResetClearsAllFields(): void
    {
        $builder = new ProductBuilder();
        $builder
            ->id(self::PRODUCT_ID)
            ->sku(self::SKU)
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->inStock(true)
            ->isNew(true)
            ->name('Product', 'lt-LT')
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID is required.');

        $builder->build();
    }

    public function testResetReturnsBuilder(): void
    {
        $builder = new ProductBuilder();

        $this->assertSame($builder, $builder->reset());
    }

    public function testCanReuseBuilderAfterReset(): void
    {
        $builder = new ProductBuilder();

        // Build first product
        $product1 = $builder
            ->id('product-1')
            ->sku('SKU-001')
            ->pricing($this->createPricing())
            ->imageUrl($this->createImageUrl())
            ->name('Product 1', 'lt-LT')
            ->build();

        // Reset and build second product
        $product2 = $builder
            ->reset()
            ->id('product-2')
            ->sku('SKU-002')
            ->pricing(new ProductPricing(149.99, 179.99, 123.97, 148.76))
            ->imageUrl($this->createImageUrl())
            ->name('Product 2', 'lt-LT')
            ->build();

        $this->assertEquals('product-1', $product1->id);
        $this->assertEquals('product-2', $product2->id);
        $this->assertEquals('SKU-001', $product1->sku);
        $this->assertEquals('SKU-002', $product2->sku);
        $this->assertEquals(99.99, $product1->pricing->price);
        $this->assertEquals(149.99, $product2->pricing->price);
    }

    public function testBuildDarboDrabuziaiProduct(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id('12345')
            ->sku('SKU-12345')
            ->pricing(new ProductPricing(99.99, 129.99, 82.64, 107.43))
            ->imageUrl(new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ))
            ->name('Darbo drabužis Premium', 'lt-LT')
            ->brand('WorkWear Pro', 'lt-LT')
            ->description('Aukštos kokybės darbo drabužis', 'lt-LT')
            ->categories(['Darbo drabužiai', 'Darbo drabužiai > Kelnės'], 'lt-LT')
            ->build();

        $serialized = $product->jsonSerialize();

        $this->assertEquals('12345', $serialized['id']);
        $this->assertEquals('SKU-12345', $serialized['sku']);
        $this->assertEquals(99.99, $serialized['price']);
        $this->assertEquals(129.99, $serialized['basePrice']);
        $this->assertEquals('Darbo drabužis Premium', $serialized['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $serialized['brand_lt-LT']);
        $this->assertArrayHasKey('imageUrl', $serialized);
    }
}
