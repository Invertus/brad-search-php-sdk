<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use PHPUnit\Framework\TestCase;

class ProductBuilderTest extends TestCase
{
    private const PRODUCT_ID = 'prod-123';
    private const PRICE = 99.99;
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
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

    public function testBuildWithRequiredFields(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->build();

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals(self::PRODUCT_ID, $product->id);
        $this->assertEquals(self::PRICE, $product->price);
    }

    public function testBuildWithVariants(): void
    {
        $variant = $this->createVariant();
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->addVariant($variant)
            ->build();

        $this->assertCount(1, $product->variants);
        $this->assertSame($variant, $product->variants[0]);
    }

    public function testBuildWithMultipleVariants(): void
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

        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->addVariant($variant1)
            ->addVariant($variant2)
            ->build();

        $this->assertCount(2, $product->variants);
    }

    public function testBuildWithVariantsArray(): void
    {
        $variants = [
            $this->createVariant(),
            new ProductVariant(
                'variant-2',
                'SKU-002',
                149.99,
                179.99,
                123.97,
                148.76,
                'https://shop.example.com/variant-2',
                $this->createImageUrl(),
                ['size' => 'L']
            ),
        ];

        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->variants($variants)
            ->build();

        $this->assertCount(2, $product->variants);
    }

    public function testBuildWithCustomField(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
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
            ->price(self::PRICE)
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
            ->price(self::PRICE)
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
            ->price(self::PRICE)
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
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->categories($categories, 'lt-LT')
            ->build();

        $this->assertEquals($categories, $product->additionalFields['categories_lt-LT']);
    }

    public function testBuildWithSku(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->sku('SKU-12345')
            ->build();

        $this->assertEquals('SKU-12345', $product->additionalFields['sku']);
    }

    public function testBuildWithAllLocalizedFields(): void
    {
        $builder = new ProductBuilder();
        $product = $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->name('Darbo drabužis Premium', 'lt-LT')
            ->brand('WorkWear Pro', 'lt-LT')
            ->description('Aukštos kokybės darbo drabužis', 'lt-LT')
            ->categories(['Darbo drabužiai', 'Darbo drabužiai > Kelnės'], 'lt-LT')
            ->sku('SKU-12345')
            ->build();

        $this->assertEquals('Darbo drabužis Premium', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $product->additionalFields['brand_lt-LT']);
        $this->assertEquals('Aukštos kokybės darbo drabužis', $product->additionalFields['description_lt-LT']);
        $this->assertEquals(
            ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
            $product->additionalFields['categories_lt-LT']
        );
        $this->assertEquals('SKU-12345', $product->additionalFields['sku']);
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new ProductBuilder();

        $this->assertSame($builder, $builder->id(self::PRODUCT_ID));
        $this->assertSame($builder, $builder->price(self::PRICE));
        $this->assertSame($builder, $builder->imageUrl($this->createImageUrl()));
        $this->assertSame($builder, $builder->addVariant($this->createVariant()));
        $this->assertSame($builder, $builder->field('key', 'value'));
        $this->assertSame($builder, $builder->name('Name', 'lt-LT'));
        $this->assertSame($builder, $builder->brand('Brand', 'lt-LT'));
        $this->assertSame($builder, $builder->description('Desc', 'lt-LT'));
        $this->assertSame($builder, $builder->categories([], 'lt-LT'));
        $this->assertSame($builder, $builder->sku('SKU'));
    }

    public function testThrowsExceptionForMissingId(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID is required.');

        $builder
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->build();
    }

    public function testThrowsExceptionForMissingPrice(): void
    {
        $builder = new ProductBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product price is required.');

        $builder
            ->id(self::PRODUCT_ID)
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
            ->price(self::PRICE)
            ->build();
    }

    public function testResetClearsAllFields(): void
    {
        $builder = new ProductBuilder();
        $builder
            ->id(self::PRODUCT_ID)
            ->price(self::PRICE)
            ->imageUrl($this->createImageUrl())
            ->addVariant($this->createVariant())
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
            ->price(99.99)
            ->imageUrl($this->createImageUrl())
            ->name('Product 1', 'lt-LT')
            ->build();

        // Reset and build second product
        $product2 = $builder
            ->reset()
            ->id('product-2')
            ->price(149.99)
            ->imageUrl($this->createImageUrl())
            ->name('Product 2', 'lt-LT')
            ->build();

        $this->assertEquals('product-1', $product1->id);
        $this->assertEquals('product-2', $product2->id);
        $this->assertEquals(99.99, $product1->price);
        $this->assertEquals(149.99, $product2->price);
    }

    public function testBuildDarboDrabuziaiProduct(): void
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

        $builder = new ProductBuilder();
        $product = $builder
            ->id('12345')
            ->price(99.99)
            ->imageUrl(new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ))
            ->name('Darbo drabužis Premium', 'lt-LT')
            ->brand('WorkWear Pro', 'lt-LT')
            ->sku('SKU-12345')
            ->description('Aukštos kokybės darbo drabužis', 'lt-LT')
            ->categories(['Darbo drabužiai', 'Darbo drabužiai > Kelnės'], 'lt-LT')
            ->addVariant($variant)
            ->build();

        $serialized = $product->jsonSerialize();

        $this->assertEquals('12345', $serialized['id']);
        $this->assertEquals(99.99, $serialized['price']);
        $this->assertEquals('Darbo drabužis Premium', $serialized['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $serialized['brand_lt-LT']);
        $this->assertEquals('SKU-12345', $serialized['sku']);
        $this->assertArrayHasKey('variants', $serialized);
    }
}
