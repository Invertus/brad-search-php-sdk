<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\PrestaShopAdapterV2;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use PHPUnit\Framework\TestCase;

class PrestaShopAdapterV2Test extends TestCase
{
    private PrestaShopAdapterV2 $adapter;

    protected function setUp(): void
    {
        $this->adapter = new PrestaShopAdapterV2();
    }

    public function testTransformWithInvalidData(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid PrestaShop data: missing products array');
        $this->adapter->transform([]);
    }

    public function testTransformWithMissingProductsArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid PrestaShop data: missing products array');
        $this->adapter->transform(['not_products' => []]);
    }

    public function testTransformReturnsBulkOperationsRequest(): void
    {
        $prestaShopData = $this->getMinimalValidProduct();

        $result = $this->adapter->transform($prestaShopData);

        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertInstanceOf(BulkOperationsRequest::class, $result['request']);
        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);
    }

    public function testTransformReturnsNullRequestWhenNoProducts(): void
    {
        $prestaShopData = [
            'products' => [
                ['invalid' => 'product'], // Will fail validation
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertNull($result['request']);
        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
    }

    public function testTransformSimpleProduct(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '109.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '90.90',
                    'localizedNames' => [
                        'en-US' => 'Sneakers "101H" Springa multi',
                    ],
                    'brand' => [
                        'localizedNames' => [
                            'en-US' => 'Springa',
                        ],
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/5309-small_default/sneakers.jpg',
                        'medium' => 'http://prestashop/5309-medium_default/sneakers.jpg',
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/sneakers/1807-sneakers.html',
                    ],
                    'categories' => [
                        'lvl2' => [
                            [
                                'remoteId' => '162',
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Men',
                                    ],
                                ],
                            ],
                        ],
                        'lvl3' => [
                            [
                                'remoteId' => '163',
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Men > Shoes',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result['products']);
        $product = $result['products'][0];

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('1807', $product->id);
        $this->assertEquals('M0E20000000EAAK', $product->sku);
        $this->assertInstanceOf(ProductPricing::class, $product->pricing);
        $this->assertEquals(99.99, $product->pricing->price);
        $this->assertEquals(109.99, $product->pricing->basePrice);
        $this->assertEquals(82.64, $product->pricing->priceTaxExcluded);
        $this->assertEquals(90.90, $product->pricing->basePriceTaxExcluded);
        $this->assertInstanceOf(ImageUrl::class, $product->imageUrl);
        $this->assertEquals('http://prestashop/5309-small_default/sneakers.jpg', $product->imageUrl->small);
        $this->assertEquals('http://prestashop/5309-medium_default/sneakers.jpg', $product->imageUrl->medium);

        // Check additional fields (en-US always suffixed)
        $this->assertEquals('Sneakers "101H" Springa multi', $product->additionalFields['name_en-US']);
        $this->assertEquals('Springa', $product->additionalFields['brand_en-US']);
        $this->assertEquals('http://prestashop/sneakers/1807-sneakers.html', $product->additionalFields['productUrl_en-US']);
        $this->assertEquals(['Men', 'Men > Shoes'], $product->additionalFields['categories_en-US']);
        $this->assertEquals(['Men', 'Shoes'], $product->additionalFields['categoriesFlat_en-US']);
    }

    public function testTransformAddsFlatCategoryLevelsPerLocale(): void
    {
        $product = $this->getMinimalProductData('1807', 'SKU-123');
        $product['categories'] = [
            'lvl2' => [
                [
                    'remoteId' => '148',
                    'localizedValues' => [
                        'path' => [
                            'en-US' => 'Store > Summer > Men > T-Shirts',
                            'lt-LT' => 'Parduotuvė > Vasara > Vyrai > Marškinėliai',
                        ],
                    ],
                ],
            ],
            'lvl3' => [
                [
                    'remoteId' => '163',
                    'localizedValues' => [
                        'path' => [
                            'en-US' => 'Store > Spring > Men > Shirtlings',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$product]]);
        $fields = $result['products'][0]->additionalFields;

        $this->assertSame(
            ['Store > Summer > Men > T-Shirts', 'Store > Spring > Men > Shirtlings'],
            $fields['categories_en-US']
        );
        $this->assertSame(
            ['Store', 'Summer', 'Men', 'T-Shirts', 'Spring', 'Shirtlings'],
            $fields['categoriesFlat_en-US']
        );
        $this->assertSame(
            ['Parduotuvė', 'Vasara', 'Vyrai', 'Marškinėliai'],
            $fields['categoriesFlat_lt-LT']
        );
    }

    public function testNoFlatCategoriesWithoutCategories(): void
    {
        $result = $this->adapter->transform($this->getMinimalValidProduct());
        $fields = $result['products'][0]->additionalFields;

        $this->assertArrayNotHasKey('categoriesFlat_en-US', $fields);
    }

    public function testTransformProductWithMultipleLocales(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '109.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '90.90',
                    'localizedNames' => [
                        'en-US' => 'Sneakers Multi',
                        'lt-LT' => 'Sportiniai batai Multi',
                    ],
                    'brand' => [
                        'localizedNames' => [
                            'en-US' => 'Springa',
                            'lt-LT' => 'Springa LT',
                        ],
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/5309-small_default/sneakers.jpg',
                        'medium' => 'http://prestashop/5309-medium_default/sneakers.jpg',
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/en/sneakers.html',
                        'lt-LT' => 'http://prestashop/lt/sportiniai-batai.html',
                    ],
                    'categories' => [
                        'lvl2' => [
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Shoes',
                                        'lt-LT' => 'Batai',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        // en-US locale fields (always suffixed)
        $this->assertEquals('Sneakers Multi', $product->additionalFields['name_en-US']);
        $this->assertEquals('Springa', $product->additionalFields['brand_en-US']);
        $this->assertEquals('http://prestashop/en/sneakers.html', $product->additionalFields['productUrl_en-US']);
        $this->assertEquals(['Shoes'], $product->additionalFields['categories_en-US']);

        // Additional locale fields (with suffix)
        $this->assertEquals('Sportiniai batai Multi', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('Springa LT', $product->additionalFields['brand_lt-LT']);
        $this->assertEquals('http://prestashop/lt/sportiniai-batai.html', $product->additionalFields['productUrl_lt-LT']);
        $this->assertEquals(['Batai'], $product->additionalFields['categories_lt-LT']);
    }

    public function testTransformProductWithVariants(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Sneakers Multi',
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/5309-small.jpg',
                        'medium' => 'http://prestashop/5309-medium.jpg',
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            'remoteId' => '26911',
                            'sku' => 'M0E20000000EAAK-34',
                            'price' => 99.99,
                            'basePrice' => 99.99,
                            'priceTaxExcluded' => 82.64,
                            'basePriceTaxExcluded' => 82.64,
                            'attributes' => [
                                'Size' => [
                                    'remoteId' => '201',
                                    'localizedNames' => ['en-US' => 'Size'],
                                    'localizedValues' => [
                                        'en-US' => '34',
                                    ],
                                ],
                                'Color' => [
                                    'remoteId' => '202',
                                    'localizedNames' => ['en-US' => 'Color'],
                                    'localizedValues' => [
                                        'en-US' => 'multi',
                                    ],
                                ],
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/sneakers/1807-26911-sneakers.html',
                                ],
                            ],
                            'imageUrl' => [
                                'small' => 'http://prestashop/5309-var1-small.jpg',
                                'medium' => 'http://prestashop/5309-var1-medium.jpg',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayHasKey('variants', $product->additionalFields);
        $variants = $product->additionalFields['variants'];

        $this->assertCount(1, $variants);
        $variant = $variants[0];

        $this->assertEquals('26911', $variant['id']);
        $this->assertEquals('M0E20000000EAAK-34', $variant['sku']);
        $this->assertEquals('http://prestashop/sneakers/1807-26911-sneakers.html', $variant['productUrl']);
        $this->assertEquals(99.99, $variant['price']);
        $this->assertEquals(99.99, $variant['basePrice']);
        $this->assertEquals(82.64, $variant['priceTaxExcluded']);
        $this->assertEquals(82.64, $variant['basePriceTaxExcluded']);
        // attrs uses remoteId as keys with locale values
        $this->assertEquals(['201' => ['en-US' => '34'], '202' => ['en-US' => 'multi']], $variant['attrs']);
        $this->assertArrayHasKey('imageUrl', $variant);
        $this->assertEquals('http://prestashop/5309-var1-small.jpg', $variant['imageUrl']['small']);
    }

    public function testTransformProductWithMultiLocaleVariants(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Sneakers',
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/small.jpg',
                        'medium' => 'http://prestashop/medium.jpg',
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            'remoteId' => '26911',
                            'sku' => 'VARIANT-SKU',
                            'attributes' => [
                                'Color' => [
                                    'remoteId' => '301',
                                    'localizedNames' => [
                                        'en-US' => 'Color',
                                        'lt-LT' => 'Spalva',
                                    ],
                                    'localizedValues' => [
                                        'en-US' => 'Red',
                                        'lt-LT' => 'Raudona',
                                    ],
                                ],
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/en/variant.html',
                                    'lt-LT' => 'http://prestashop/lt/variantas.html',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        // All locales are now in a single 'variants' array with attrs containing all locale values
        $this->assertArrayHasKey('variants', $product->additionalFields);
        $this->assertCount(1, $product->additionalFields['variants']);

        // attrs now contains all locales for the attribute
        $variant = $product->additionalFields['variants'][0];
        $this->assertEquals('26911', $variant['id']);
        $this->assertEquals('VARIANT-SKU', $variant['sku']);
        $this->assertEquals([
            '301' => [
                'en-US' => 'Red',
                'lt-LT' => 'Raudona',
            ],
        ], $variant['attrs']);
    }

    public function testTransformProductMethod(): void
    {
        $product = [
            'remoteId' => '1807',
            'sku' => 'TEST-SKU',
            'price' => '49.99',
            'basePrice' => '59.99',
            'priceTaxExcluded' => '41.32',
            'basePriceTaxExcluded' => '49.58',
            'localizedNames' => [
                'en-US' => 'Test Product',
            ],
            'imageUrl' => [
                'small' => 'http://example.com/small.jpg',
                'medium' => 'http://example.com/medium.jpg',
            ],
            'categories' => [],
            'variants' => [],
        ];

        $result = $this->adapter->transformProduct($product);

        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('1807', $result->id);
        $this->assertEquals('TEST-SKU', $result->sku);
        $this->assertEquals(49.99, $result->pricing->price);
    }

    public function testTransformVariantMethod(): void
    {
        $variant = [
            'remoteId' => '12345',
            'sku' => 'VARIANT-SKU-001',
            'price' => 29.99,
            'basePrice' => 39.99,
            'priceTaxExcluded' => 24.79,
            'basePriceTaxExcluded' => 33.05,
            'productUrl' => [
                'localizedValues' => [
                    'en-US' => 'http://example.com/product/variant',
                ],
            ],
            'imageUrl' => [
                'small' => 'http://example.com/variant-small.jpg',
                'medium' => 'http://example.com/variant-medium.jpg',
            ],
            'attributes' => [
                'Size' => [
                    'localizedValues' => [
                        'en-US' => 'Large',
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transformVariant($variant, 'en-US');

        $this->assertInstanceOf(ProductVariant::class, $result);
        $this->assertEquals('12345', $result->id);
        $this->assertEquals('VARIANT-SKU-001', $result->sku);
        $this->assertInstanceOf(ProductPricing::class, $result->pricing);
        $this->assertEquals(29.99, $result->pricing->price);
        $this->assertEquals(39.99, $result->pricing->basePrice);
        $this->assertEquals(24.79, $result->pricing->priceTaxExcluded);
        $this->assertEquals(33.05, $result->pricing->basePriceTaxExcluded);
        $this->assertEquals('http://example.com/product/variant', $result->productUrl);
        $this->assertInstanceOf(ImageUrl::class, $result->imageUrl);
        // transformVariant still uses named attrs for locale-specific extraction
        $this->assertEquals(['size' => 'Large'], $result->attrs);
    }

    public function testTransformVariantMissingRemoteId(): void
    {
        $variant = [
            'sku' => 'VARIANT-SKU',
            'price' => 29.99,
            'basePrice' => 39.99,
            'priceTaxExcluded' => 24.79,
            'basePriceTaxExcluded' => 33.05,
            'productUrl' => [
                'localizedValues' => [
                    'en-US' => 'http://example.com/variant',
                ],
            ],
            'imageUrl' => [
                'small' => 'http://example.com/small.jpg',
                'medium' => 'http://example.com/medium.jpg',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Variant 'remoteId' is required");
        $this->adapter->transformVariant($variant, 'en-US');
    }

    public function testTransformProductWithNullSku(): void
    {
        $product = $this->getMinimalProductData('1807', 'SKU-123');
        $product['sku'] = null;

        $result = $this->adapter->transformProduct($product);

        $this->assertEquals('1807', $result->id);
        $this->assertSame('', $result->sku);
        $this->assertSame('', $result->jsonSerialize()['sku']);
    }

    public function testTransformProductWithEmptySku(): void
    {
        $product = $this->getMinimalProductData('1807', '');

        $result = $this->adapter->transformProduct($product);

        $this->assertEquals('1807', $result->id);
        $this->assertSame('', $result->sku);
        $this->assertSame('', $result->jsonSerialize()['sku']);
    }

    public function testTransformProductWithMissingSku(): void
    {
        $product = $this->getMinimalProductData('1807', 'SKU-123');
        unset($product['sku']);

        $result = $this->adapter->transform(['products' => [$product]]);

        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['products']);
        $this->assertSame('', $result['products'][0]->sku);
    }

    public function testTransformVariantWithEmptySku(): void
    {
        $variant = $this->getMinimalVariantData();
        $variant['sku'] = '';

        $result = $this->adapter->transformVariant($variant, 'en-US');

        $this->assertEquals('12345', $result->id);
        $this->assertSame('', $result->sku);
    }

    public function testTransformVariantWithMissingSku(): void
    {
        $variant = $this->getMinimalVariantData();
        unset($variant['sku']);

        $result = $this->adapter->transformVariant($variant, 'en-US');

        $this->assertSame('', $result->sku);
        $this->assertSame('', $result->jsonSerialize()['sku']);
    }

    public function testTransformProductWithMissingRequiredFields(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('transformation_error', $result['errors'][0]['type']);
        $this->assertStringContainsString("'remoteId'", $result['errors'][0]['message']);
    }

    public function testTransformProductWithMissingImageUrl(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    // Missing imageUrl
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('ImageUrl', $result['errors'][0]['message']);
    }

    public function testTransformProductWithFeatures(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'features' => [
                        [
                            'remoteId' => '5',
                            'localizedValues' => [
                                'en-US' => 'Cotton',
                            ],
                        ],
                        [
                            'remoteId' => '12',
                            'localizedValues' => [
                                'en-US' => '200g',
                                'lt-LT' => '200g',
                            ],
                        ],
                    ],
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        // Features as flat fields
        $this->assertArrayHasKey('feature_5_en-US', $product->additionalFields);
        $this->assertEquals('Cotton', $product->additionalFields['feature_5_en-US']);

        $this->assertArrayHasKey('feature_12_en-US', $product->additionalFields);
        $this->assertEquals('200g', $product->additionalFields['feature_12_en-US']);

        $this->assertArrayHasKey('feature_12_lt-LT', $product->additionalFields);
        $this->assertEquals('200g', $product->additionalFields['feature_12_lt-LT']);

        // Old format should not exist
        $this->assertArrayNotHasKey('features', $product->additionalFields);
        $this->assertArrayNotHasKey('features_lt-LT', $product->additionalFields);
    }

    public function testTransformProductWithTags(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'tags' => [
                        'en-US' => ['summer', 'sale', 'new'],
                        'lt-LT' => ['vasara', 'akcija'],
                    ],
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayHasKey('tags_en-US', $product->additionalFields);
        $this->assertEquals(['summer', 'sale', 'new'], $product->additionalFields['tags_en-US']);

        $this->assertArrayHasKey('tags_lt-LT', $product->additionalFields);
        $this->assertEquals(['vasara', 'akcija'], $product->additionalFields['tags_lt-LT']);
    }

    public function testTransformProductWithBooleanFields(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'inStock' => true,
                    'isNew' => false,
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertTrue($product->inStock);
        $this->assertFalse($product->isNew);
    }

    public function testTransformProductWithOptionalIdentifiers(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'ean13' => '1234567890123',
                    'mpn' => 'MPN-12345',
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayHasKey('ean13', $product->additionalFields);
        $this->assertEquals('1234567890123', $product->additionalFields['ean13']);
        $this->assertArrayHasKey('mpn', $product->additionalFields);
        $this->assertEquals('MPN-12345', $product->additionalFields['mpn']);
    }

    public function testTransformProductWithDescription(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'description' => [
                        'en-US' => '<p>This is a <strong>test</strong> product.</p>',
                    ],
                    'descriptionShort' => [
                        'en-US' => '<p>Short description</p>',
                    ],
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        // HTML tags should be stripped (en-US always suffixed)
        $this->assertArrayHasKey('description_en-US', $product->additionalFields);
        $this->assertEquals('This is a test product.', $product->additionalFields['description_en-US']);
        $this->assertArrayHasKey('descriptionShort_en-US', $product->additionalFields);
        $this->assertEquals('Short description', $product->additionalFields['descriptionShort_en-US']);
    }

    public function testTransformProductWithCategoryDefault(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                    ],
                    'categoryDefault' => [
                        'localizedValues' => [
                            'path' => [
                                'en-US' => 'Men > Shoes > Sneakers',
                            ],
                        ],
                    ],
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayHasKey('categoryDefault_en-US', $product->additionalFields);
        $this->assertEquals('Men > Shoes > Sneakers', $product->additionalFields['categoryDefault_en-US']);
    }

    public function testBulkOperationsRequestStructure(): void
    {
        $prestaShopData = $this->getMinimalValidProduct();

        $result = $this->adapter->transform($prestaShopData);
        $request = $result['request'];

        $this->assertInstanceOf(BulkOperationsRequest::class, $request);

        $json = $request->jsonSerialize();

        $this->assertArrayHasKey('operations', $json);
        $this->assertCount(1, $json['operations']);
        $this->assertEquals('index_products', $json['operations'][0]['type']);
        $this->assertArrayHasKey('products', $json['operations'][0]['payload']);
    }

    public function testTransformMultipleProducts(): void
    {
        $prestaShopData = [
            'products' => [
                $this->getMinimalProductData('1001', 'SKU-001'),
                $this->getMinimalProductData('1002', 'SKU-002'),
                $this->getMinimalProductData('1003', 'SKU-003'),
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(3, $result['products']);
        $this->assertCount(0, $result['errors']);
        $this->assertInstanceOf(BulkOperationsRequest::class, $result['request']);
    }

    public function testTransformMixedValidAndInvalidProducts(): void
    {
        $prestaShopData = [
            'products' => [
                $this->getMinimalProductData('1001', 'SKU-001'),
                ['invalid' => 'product'], // Missing required fields
                $this->getMinimalProductData('1003', 'SKU-003'),
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(2, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(1, $result['errors'][0]['product_index']);
    }

    public function testTransformWithNonArrayProducts(): void
    {
        $prestaShopData = [
            'products' => [
                'invalid-string-product',
                123,
                null,
                $this->getMinimalProductData('1001', 'SKU-001'),
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']); // Non-array items are skipped, not errors
    }

    public function testTransformVariantWithoutProductUrl(): void
    {
        $variant = [
            'remoteId' => '12345',
            'sku' => 'VARIANT-SKU',
            'price' => 29.99,
            'basePrice' => 39.99,
            'priceTaxExcluded' => 24.79,
            'basePriceTaxExcluded' => 33.05,
            'productUrl' => [
                'localizedValues' => [],
            ],
            'imageUrl' => [
                'small' => 'http://example.com/small.jpg',
                'medium' => 'http://example.com/medium.jpg',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Variant 'productUrl' is required");
        $this->adapter->transformVariant($variant, 'en-US');
    }

    public function testImageUrlWithLargeAndThumbnail(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'SKU-123',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'localizedNames' => [
                        'en-US' => 'Test Product',
                    ],
                    'imageUrl' => [
                        'small' => 'http://example.com/small.jpg',
                        'medium' => 'http://example.com/medium.jpg',
                        'large' => 'http://example.com/large.jpg',
                        'thumbnail' => 'http://example.com/thumbnail.jpg',
                    ],
                    'categories' => [],
                    'variants' => [],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertEquals('http://example.com/small.jpg', $product->imageUrl->small);
        $this->assertEquals('http://example.com/medium.jpg', $product->imageUrl->medium);
        $this->assertEquals('http://example.com/large.jpg', $product->imageUrl->large);
        $this->assertEquals('http://example.com/thumbnail.jpg', $product->imageUrl->thumbnail);
    }

    public function testDarboDrabuziaiClientMapping(): void
    {
        // Input: PrestaShop data matching real DarboDrabuziai client format (lt-LT locale)
        // This test verifies the exact JSON structure that will be sent to the sync API
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => 'prod-123',
                    'sku' => 'MAIN-SKU',
                    'price' => 9.99,
                    'basePrice' => 12.99,
                    'priceTaxExcluded' => 8.26,
                    'basePriceTaxExcluded' => 10.74,
                    'localizedNames' => [
                        'lt-LT' => 'Darbo pirštinės',
                    ],
                    'brand' => [
                        'localizedNames' => [
                            'lt-LT' => 'SafetyFirst',
                        ],
                    ],
                    'imageUrl' => [
                        'small' => 'https://www.darbodrabuziai.lt/img/main-s.jpg',
                        'medium' => 'https://www.darbodrabuziai.lt/img/main.jpg',
                    ],
                    'productUrl' => [
                        'lt-LT' => 'https://www.darbodrabuziai.lt/produktai/pirstines',
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            'remoteId' => '4107',
                            'sku' => 'GLOVES-4107',
                            'price' => 1.64,
                            'basePrice' => 2.05,
                            'priceTaxExcluded' => 1.36,
                            'basePriceTaxExcluded' => 1.69,
                            'productUrl' => [
                                'localizedValues' => [
                                    'lt-LT' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4107',
                                ],
                            ],
                            'imageUrl' => [
                                'small' => 'https://www.darbodrabuziai.lt/img/4107-s.jpg',
                                'medium' => 'https://www.darbodrabuziai.lt/img/4107.jpg',
                            ],
                            'attributes' => [
                                'Dydis' => [
                                    'remoteId' => '101',
                                    'localizedNames' => ['lt-LT' => 'Dydis'],
                                    'localizedValues' => [
                                        'lt-LT' => '8',
                                    ],
                                ],
                                'Spalva' => [
                                    'remoteId' => '102',
                                    'localizedNames' => ['lt-LT' => 'Spalva'],
                                    'localizedValues' => [
                                        'lt-LT' => 'Juoda',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'remoteId' => '4108',
                            'sku' => 'GLOVES-4108',
                            'price' => 1.64,
                            'basePrice' => 2.05,
                            'priceTaxExcluded' => 1.36,
                            'basePriceTaxExcluded' => 1.69,
                            'productUrl' => [
                                'localizedValues' => [
                                    'lt-LT' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4108',
                                ],
                            ],
                            'imageUrl' => [
                                'small' => 'https://www.darbodrabuziai.lt/img/4108-s.jpg',
                                'medium' => 'https://www.darbodrabuziai.lt/img/4108.jpg',
                            ],
                            'attributes' => [
                                'Dydis' => [
                                    'remoteId' => '101',
                                    'localizedNames' => ['lt-LT' => 'Dydis'],
                                    'localizedValues' => [
                                        'lt-LT' => '9',
                                    ],
                                ],
                                'Spalva' => [
                                    'remoteId' => '102',
                                    'localizedNames' => ['lt-LT' => 'Spalva'],
                                    'localizedValues' => [
                                        'lt-LT' => 'Juoda',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);

        $product = $result['products'][0];

        // Verify product fields
        $this->assertEquals('prod-123', $product->id);
        $this->assertEquals('MAIN-SKU', $product->sku);
        $this->assertEquals(9.99, $product->pricing->price);
        $this->assertEquals(12.99, $product->pricing->basePrice);
        $this->assertEquals(8.26, $product->pricing->priceTaxExcluded);
        $this->assertEquals(10.74, $product->pricing->basePriceTaxExcluded);

        // Verify localized fields
        $this->assertEquals('Darbo pirštinės', $product->additionalFields['name_lt-LT']);
        $this->assertEquals('SafetyFirst', $product->additionalFields['brand_lt-LT']);
        $this->assertEquals('https://www.darbodrabuziai.lt/produktai/pirstines', $product->additionalFields['productUrl_lt-LT']);

        // Verify variants structure - now just 'variants', not 'variants_lt-LT'
        $this->assertArrayHasKey('variants', $product->additionalFields);
        $variants = $product->additionalFields['variants'];
        $this->assertCount(2, $variants);

        // Verify first variant
        $variant1 = $variants[0];
        $this->assertEquals('4107', $variant1['id']);
        $this->assertEquals('GLOVES-4107', $variant1['sku']);
        $this->assertEquals(1.64, $variant1['price']);
        $this->assertEquals(2.05, $variant1['basePrice']);
        $this->assertEquals(1.36, $variant1['priceTaxExcluded']);
        $this->assertEquals(1.69, $variant1['basePriceTaxExcluded']);
        $this->assertEquals('https://www.darbodrabuziai.lt/produktai/pirstines/4107', $variant1['productUrl']);
        $this->assertEquals('https://www.darbodrabuziai.lt/img/4107-s.jpg', $variant1['imageUrl']['small']);
        $this->assertEquals('https://www.darbodrabuziai.lt/img/4107.jpg', $variant1['imageUrl']['medium']);

        // Verify attrs with remoteId as keys and locale values
        $this->assertArrayHasKey('attrs', $variant1);
        $this->assertEquals(['lt-LT' => '8'], $variant1['attrs']['101']);
        $this->assertEquals(['lt-LT' => 'Juoda'], $variant1['attrs']['102']);

        // Verify second variant
        $variant2 = $variants[1];
        $this->assertEquals('4108', $variant2['id']);
        $this->assertEquals('GLOVES-4108', $variant2['sku']);
        $this->assertEquals(['lt-LT' => '9'], $variant2['attrs']['101']);
        $this->assertEquals(['lt-LT' => 'Juoda'], $variant2['attrs']['102']);

        // Verify full JSON serialization matches expected API format
        $request = $result['request'];
        $json = $request->jsonSerialize();

        $productPayload = $json['operations'][0]['payload']['products'][0];

        // Expected JSON structure (matching user-provided format)
        $expectedProduct = [
            'id' => 'prod-123',
            'sku' => 'MAIN-SKU',
            'price' => 9.99,
            'basePrice' => 12.99,
            'priceTaxExcluded' => 8.26,
            'basePriceTaxExcluded' => 10.74,
            'imageUrl' => [
                'small' => 'https://www.darbodrabuziai.lt/img/main-s.jpg',
                'medium' => 'https://www.darbodrabuziai.lt/img/main.jpg',
            ],
            'name_lt-LT' => 'Darbo pirštinės',
            'brand_lt-LT' => 'SafetyFirst',
            'productUrl_lt-LT' => 'https://www.darbodrabuziai.lt/produktai/pirstines',
            'variants' => [
                [
                    'id' => '4107',
                    'sku' => 'GLOVES-4107',
                    'price' => 1.64,
                    'basePrice' => 2.05,
                    'priceTaxExcluded' => 1.36,
                    'basePriceTaxExcluded' => 1.69,
                    'productUrl' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4107',
                    'imageUrl' => [
                        'small' => 'https://www.darbodrabuziai.lt/img/4107-s.jpg',
                        'medium' => 'https://www.darbodrabuziai.lt/img/4107.jpg',
                    ],
                    'attrs' => [
                        '101' => ['lt-LT' => '8'],
                        '102' => ['lt-LT' => 'Juoda'],
                    ],
                ],
                [
                    'id' => '4108',
                    'sku' => 'GLOVES-4108',
                    'price' => 1.64,
                    'basePrice' => 2.05,
                    'priceTaxExcluded' => 1.36,
                    'basePriceTaxExcluded' => 1.69,
                    'productUrl' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4108',
                    'imageUrl' => [
                        'small' => 'https://www.darbodrabuziai.lt/img/4108-s.jpg',
                        'medium' => 'https://www.darbodrabuziai.lt/img/4108.jpg',
                    ],
                    'attrs' => [
                        '101' => ['lt-LT' => '9'],
                        '102' => ['lt-LT' => 'Juoda'],
                    ],
                ],
            ],
        ];

        // Verify key fields match expected structure
        $this->assertEquals($expectedProduct['id'], $productPayload['id']);
        $this->assertEquals($expectedProduct['sku'], $productPayload['sku']);
        $this->assertEquals($expectedProduct['price'], $productPayload['price']);
        $this->assertEquals($expectedProduct['basePrice'], $productPayload['basePrice']);
        $this->assertEquals($expectedProduct['priceTaxExcluded'], $productPayload['priceTaxExcluded']);
        $this->assertEquals($expectedProduct['basePriceTaxExcluded'], $productPayload['basePriceTaxExcluded']);
        $this->assertEquals($expectedProduct['imageUrl'], $productPayload['imageUrl']);
        $this->assertEquals($expectedProduct['name_lt-LT'], $productPayload['name_lt-LT']);
        $this->assertEquals($expectedProduct['brand_lt-LT'], $productPayload['brand_lt-LT']);
        $this->assertEquals($expectedProduct['productUrl_lt-LT'], $productPayload['productUrl_lt-LT']);
        $this->assertEquals($expectedProduct['variants'], $productPayload['variants']);
    }

    public function testTransformProductWithTimestamps(): void
    {
        $prestaShopData = [
            'products' => [
                array_merge($this->getMinimalProductData('1807', 'SKU-123'), [
                    'createdAt' => '2025-05-16 18:28:58',
                    'updatedAt' => '2025-12-16 15:17:11',
                ]),
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertEquals('2025-05-16 18:28:58', $product->additionalFields['createdAt']);
        $this->assertEquals('2025-12-16 15:17:11', $product->additionalFields['updatedAt']);
    }

    public function testTransformProductWithoutTimestamps(): void
    {
        $prestaShopData = $this->getMinimalValidProduct();

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('createdAt', $product->additionalFields);
        $this->assertArrayNotHasKey('updatedAt', $product->additionalFields);
    }

    public function testTransformProductWithEmptyTimestamps(): void
    {
        $prestaShopData = [
            'products' => [
                array_merge($this->getMinimalProductData('1807', 'SKU-123'), [
                    'createdAt' => '',
                    'updatedAt' => null,
                ]),
            ],
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('createdAt', $product->additionalFields);
        $this->assertArrayNotHasKey('updatedAt', $product->additionalFields);
    }

    public function testTransformLocalizedCustomFieldSuffixesEveryLocale(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => 'Cotton shirt', 'lt-LT' => 'Medvilniniai'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $product = $result['products'][0];

        $this->assertSame('Cotton shirt', $product->additionalFields['custom_internal_name_en-US']);
        $this->assertSame('Medvilniniai', $product->additionalFields['custom_internal_name_lt-LT']);
        $this->assertArrayNotHasKey('custom_internal_name', $product->additionalFields);
    }

    public function testTransformNonLocalizedCustomFieldHasNoLocaleSuffix(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'A-12'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame('A-12', $result['products'][0]->additionalFields['custom_warehouse_slot']);
    }

    public function testCustomFieldPrefixPreventsCollisionWithCoreFields(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'price', 'type' => 'double', 'value' => '0.01'],
            ['name' => 'id', 'type' => 'integer', 'value' => '999999'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $product = $result['products'][0];
        $serialized = $product->jsonSerialize();

        $this->assertSame('0.01', $product->additionalFields['custom_price']);
        $this->assertSame('999999', $product->additionalFields['custom_id']);
        $this->assertSame('1807', $serialized['id']);
        $this->assertSame(99.99, $serialized['price']);
    }

    public function testTransformLocalizedCustomFieldStripsHtml(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => '<b>ALPHA</b>-7741'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $product = $result['products'][0];

        $this->assertSame('ALPHA-7741', $product->additionalFields['custom_internal_name_en-US']);
    }

    public function testTransformNonLocalizedCustomFieldStripsHtml(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => '<b>ALPHA</b>-7741'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame('ALPHA-7741', $result['products'][0]->additionalFields['custom_warehouse_slot']);
    }

    public function testMissingCustomFieldsKeyIsHarmless(): void
    {
        $result = $this->adapter->transform($this->getMinimalValidProduct());

        $this->assertCount(0, $result['errors']);
        $this->assertSame([], array_filter(
            array_keys($result['products'][0]->additionalFields),
            static fn (string $key): bool => str_starts_with($key, 'custom_')
        ));
    }

    public function testMalformedCustomFieldEntriesAreSkippedNotFatal(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            'not-an-array',
            ['type' => 'text', 'value' => 'no name key'],
            ['name' => '', 'type' => 'text', 'value' => 'empty name'],
            ['name' => 'internal_name', 'type' => 'text', 'localizedValues' => ['en-US' => '']],
            ['name' => 'good', 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertSame('kept', $additional['custom_good']);
        $this->assertArrayNotHasKey('custom_internal_name_en-US', $additional);
    }

    public function testNonScalarCustomFieldValueIsSkippedNotStringified(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'broken', 'type' => 'text', 'value' => ['a', 'b']],
            ['name' => 'nested', 'type' => 'text', 'value' => ['k' => 'v']],
            ['name' => 'good', 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey('custom_broken', $additional);
        $this->assertArrayNotHasKey('custom_nested', $additional);
        $this->assertSame('kept', $additional['custom_good']);
    }

    public function testNonScalarLocalizedCustomFieldValueIsSkippedNotStringified(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => ['a', 'b'], 'lt-LT' => 'kept'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey('custom_internal_name_en-US', $additional);
        $this->assertSame('kept', $additional['custom_internal_name_lt-LT']);
    }

    public function testStringableValueOnCoreLocalizedFieldIsAccepted(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['localizedNames'] = ['en-US' => new StringableFieldValue('Test Product')];
        $data['description'] = ['en-US' => new StringableFieldValue('<p>Cotton shirt</p>')];
        $data['descriptionShort'] = ['en-US' => new StringableFieldValue('Cotton')];
        $data['brand'] = ['localizedNames' => ['en-US' => new StringableFieldValue('Acme')]];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertSame('Test Product', $additional['name_en-US']);
        $this->assertSame('Cotton shirt', $additional['description_en-US']);
        $this->assertSame('Cotton', $additional['descriptionShort_en-US']);
        $this->assertSame('Acme', $additional['brand_en-US']);
    }

    public function testStringableValueOnLocalizedCustomFieldIsAccepted(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => new StringableFieldValue('ALPHA-7741')],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame(
            'ALPHA-7741',
            $result['products'][0]->additionalFields['custom_internal_name_en-US']
        );
    }

    public function testNonStringableValuesOnCoreLocalizedFieldAreRejected(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['localizedNames'] = ['en-US' => 'Test Product', 'lt-LT' => ['nested', 'array']];
        $data['description'] = ['en-US' => new \stdClass()];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertSame('Test Product', $additional['name_en-US']);
        $this->assertArrayNotHasKey('name_lt-LT', $additional);
        $this->assertArrayNotHasKey('description_en-US', $additional);
    }

    public function testTextCustomFieldValueWithBareLessThanSurvivesIntact(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'size_range', 'type' => 'text', 'value' => '30<x<40'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame('30<x<40', $result['products'][0]->additionalFields['custom_size_range']);
    }

    public function testLocalizedTextCustomFieldValueWithBareLessThanSurvivesIntact(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            [
                'name' => 'size_range',
                'type' => 'text',
                'localizedValues' => ['en-US' => 'S<M<L', 'lt-LT' => '30<x<40'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertSame('S<M<L', $additional['custom_size_range_en-US']);
        $this->assertSame('30<x<40', $additional['custom_size_range_lt-LT']);
    }

    public function testNonTextCustomFieldValuesAreNotHtmlStripped(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'stock_note', 'type' => 'integer', 'value' => '5<3'],
            ['name' => 'weight_note', 'type' => 'double', 'value' => '0.5<1.5'],
            ['name' => 'flag_note', 'type' => 'boolean', 'value' => 'false<true'],
            [
                'name' => 'localized_stock_note',
                'type' => 'integer',
                'localizedValues' => ['en-US' => '5<3'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertSame('5<3', $additional['custom_stock_note']);
        $this->assertSame('0.5<1.5', $additional['custom_weight_note']);
        $this->assertSame('false<true', $additional['custom_flag_note']);
        $this->assertSame('5<3', $additional['custom_localized_stock_note_en-US']);
    }

    public function testTextCustomFieldValueThatBecomesEmptyAfterStrippingIsSkipped(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => '<n/a>'],
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => '<n/a>', 'lt-LT' => 'kept'],
            ],
            ['name' => 'good', 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey('custom_warehouse_slot', $additional);
        $this->assertArrayNotHasKey('custom_internal_name_en-US', $additional);
        $this->assertSame('kept', $additional['custom_internal_name_lt-LT']);
        $this->assertSame('kept', $additional['custom_good']);
    }

    public function testCustomFieldValuesAreTrimmed(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'A-12                            '],
            ['name' => 'stock_count', 'type' => 'integer', 'value' => ' 42 '],
            ['name' => 'blank_slot', 'type' => 'text', 'value' => '     '],
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => '  ALPHA-7741  ', 'lt-LT' => '   '],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertSame('A-12', $additional['custom_warehouse_slot']);
        $this->assertSame('42', $additional['custom_stock_count']);
        $this->assertArrayNotHasKey('custom_blank_slot', $additional);
        $this->assertSame('ALPHA-7741', $additional['custom_internal_name_en-US']);
        $this->assertArrayNotHasKey('custom_internal_name_lt-LT', $additional);
    }

    public function testDateCustomFieldWithMysqlZeroDateIsSkipped(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'available_from', 'type' => 'date', 'value' => '0000-00-00 00:00:00'],
            ['name' => 'discontinued_on', 'type' => 'date', 'value' => '0000-00-00'],
            ['name' => 'restocked_at', 'type' => 'date', 'value' => '2026-01-05 10:00:00'],
            [
                'name' => 'localized_date',
                'type' => 'date',
                'localizedValues' => ['en-US' => '0000-00-00 00:00:00', 'lt-LT' => '2026-01-05'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey('custom_available_from', $additional);
        $this->assertArrayNotHasKey('custom_discontinued_on', $additional);
        $this->assertSame('2026-01-05 10:00:00', $additional['custom_restocked_at']);
        $this->assertArrayNotHasKey('custom_localized_date_en-US', $additional);
        $this->assertSame('2026-01-05', $additional['custom_localized_date_lt-LT']);
    }

    public function testTextCustomFieldHoldingMysqlZeroDateIsKept(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'legacy_note', 'type' => 'text', 'value' => '0000-00-00 is the import placeholder'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame(
            '0000-00-00 is the import placeholder',
            $result['products'][0]->additionalFields['custom_legacy_note']
        );
    }

    public function testCustomFieldWithUnsafeNameIsSkipped(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'nested.path', 'type' => 'text', 'value' => 'dotted'],
            ['name' => str_repeat('a', 65), 'type' => 'text', 'value' => 'too long'],
            ['name' => 'spaced name', 'type' => 'text', 'value' => 'spaced'],
            ['name' => 'weird-name', 'type' => 'text', 'value' => 'hyphen'],
            [
                'name' => 'localized.path',
                'type' => 'text',
                'localizedValues' => ['en-US' => 'dotted localized'],
            ],
            ['name' => 'good', 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey('custom_nested.path', $additional);
        $this->assertArrayNotHasKey('custom_' . str_repeat('a', 65), $additional);
        $this->assertArrayNotHasKey('custom_spaced name', $additional);
        $this->assertArrayNotHasKey('custom_weird-name', $additional);
        $this->assertArrayNotHasKey('custom_localized.path_en-US', $additional);
        $this->assertSame('kept', $additional['custom_good']);
    }

    public function testBooleanCustomFieldValuesAreNormalisedToTrueFalseStrings(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'is_featured', 'type' => 'boolean', 'value' => true],
            ['name' => 'is_hidden', 'type' => 'boolean', 'value' => false],
            [
                'name' => 'is_local',
                'type' => 'boolean',
                'localizedValues' => ['en-US' => true, 'lt-LT' => false],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertSame('true', $additional['custom_is_featured']);
        $this->assertSame('false', $additional['custom_is_hidden']);
        $this->assertSame('true', $additional['custom_is_local_en-US']);
        $this->assertSame('false', $additional['custom_is_local_lt-LT']);
    }

    public function testCustomFieldNameWithTrailingNewlineIsSkipped(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => "good\n", 'type' => 'text', 'value' => 'newline'],
            ['name' => 'good', 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertArrayNotHasKey("custom_good\n", $additional);
        $this->assertSame('kept', $additional['custom_good']);
    }

    public function testDuplicateCustomFieldNameLastEntryWins(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'first'],
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'second'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame('second', $result['products'][0]->additionalFields['custom_warehouse_slot']);
    }

    public function testCustomFieldsGivenAsAssociativeMapIsHandledWithoutErrors(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            'warehouse_slot' => ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'A-12'],
            'stock_count' => '42',
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertCount(0, $result['errors']);
        $this->assertSame('A-12', $additional['custom_warehouse_slot']);
        $this->assertArrayNotHasKey('custom_stock_count', $additional);
    }

    public function testCustomFieldNameAtMaximumLengthIsKept(): void
    {
        $name = str_repeat('a', 64);
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => $name, 'type' => 'text', 'value' => 'kept'],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);

        $this->assertSame('kept', $result['products'][0]->additionalFields['custom_' . $name]);
    }

    public function testCustomFieldPrefixConstMatchesTheEmittedFieldNames(): void
    {
        $data = $this->getMinimalProductData('1807', 'SKU-123');
        $data['customFields'] = [
            ['name' => 'warehouse_slot', 'type' => 'text', 'value' => 'A-12'],
            [
                'name' => 'internal_name',
                'type' => 'text',
                'localizedValues' => ['en-US' => 'ALPHA-7741'],
            ],
        ];

        $result = $this->adapter->transform(['products' => [$data]]);
        $additional = $result['products'][0]->additionalFields;

        $this->assertSame('custom_', PrestaShopAdapterV2::CUSTOM_FIELD_PREFIX);
        $this->assertSame('A-12', $additional[PrestaShopAdapterV2::CUSTOM_FIELD_PREFIX . 'warehouse_slot']);
        $this->assertSame(
            'ALPHA-7741',
            $additional[PrestaShopAdapterV2::CUSTOM_FIELD_PREFIX . 'internal_name_en-US']
        );
    }

    /**
     * Helper method to get minimal valid product data.
     *
     * @return array<string, mixed>
     */
    private function getMinimalValidProduct(): array
    {
        return [
            'products' => [
                $this->getMinimalProductData('1807', 'SKU-123'),
            ],
        ];
    }

    /**
     * Helper method to get minimal variant data.
     *
     * @return array<string, mixed>
     */
    private function getMinimalVariantData(): array
    {
        return [
            'remoteId' => '12345',
            'sku' => 'VARIANT-SKU',
            'price' => 29.99,
            'basePrice' => 39.99,
            'priceTaxExcluded' => 24.79,
            'basePriceTaxExcluded' => 33.05,
            'productUrl' => [
                'localizedValues' => [
                    'en-US' => 'http://example.com/variant',
                ],
            ],
            'imageUrl' => [
                'small' => 'http://example.com/small.jpg',
                'medium' => 'http://example.com/medium.jpg',
            ],
        ];
    }

    /**
     * Helper method to get minimal product data.
     *
     * @param string $id
     * @param string $sku
     * @return array<string, mixed>
     */
    private function getMinimalProductData(string $id, string $sku): array
    {
        return [
            'remoteId' => $id,
            'sku' => $sku,
            'price' => '99.99',
            'basePrice' => '99.99',
            'priceTaxExcluded' => '82.64',
            'basePriceTaxExcluded' => '82.64',
            'localizedNames' => [
                'en-US' => 'Test Product',
            ],
            'imageUrl' => [
                'small' => 'http://example.com/small.jpg',
                'medium' => 'http://example.com/medium.jpg',
            ],
            'categories' => [],
            'variants' => [],
        ];
    }
}

class StringableFieldValue implements \Stringable
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
