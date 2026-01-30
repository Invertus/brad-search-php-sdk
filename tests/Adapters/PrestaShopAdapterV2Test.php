<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\PrestaShopAdapterV2;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
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
        $this->assertEquals(99.99, $product->price);
        $this->assertInstanceOf(ImageUrl::class, $product->imageUrl);
        $this->assertEquals('http://prestashop/5309-small_default/sneakers.jpg', $product->imageUrl->small);
        $this->assertEquals('http://prestashop/5309-medium_default/sneakers.jpg', $product->imageUrl->medium);

        // Check additional fields
        $this->assertEquals('M0E20000000EAAK', $product->additionalFields['sku']);
        $this->assertEquals(109.99, $product->additionalFields['basePrice']);
        $this->assertEquals(82.64, $product->additionalFields['priceTaxExcluded']);
        $this->assertEquals(90.90, $product->additionalFields['basePriceTaxExcluded']);
        $this->assertEquals('Sneakers "101H" Springa multi', $product->additionalFields['name']);
        $this->assertEquals('Springa', $product->additionalFields['brand']);
        $this->assertEquals('http://prestashop/sneakers/1807-sneakers.html', $product->additionalFields['productUrl']);
        $this->assertEquals(['Men', 'Men > Shoes'], $product->additionalFields['categories']);
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

        // Default locale fields (en-US without suffix)
        $this->assertEquals('Sneakers Multi', $product->additionalFields['name']);
        $this->assertEquals('Springa', $product->additionalFields['brand']);
        $this->assertEquals('http://prestashop/en/sneakers.html', $product->additionalFields['productUrl']);
        $this->assertEquals(['Shoes'], $product->additionalFields['categories']);

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
                                    'localizedValues' => [
                                        'en-US' => '34',
                                    ],
                                ],
                                'Color' => [
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
        $this->assertEquals(['size' => '34', 'color' => 'multi'], $variant['attributes']);
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

        // en-US variant
        $this->assertArrayHasKey('variants', $product->additionalFields);
        $this->assertCount(1, $product->additionalFields['variants']);
        $this->assertEquals('Red', $product->additionalFields['variants'][0]['attributes']['color']);

        // lt-LT variant
        $this->assertArrayHasKey('variants_lt-LT', $product->additionalFields);
        $this->assertCount(1, $product->additionalFields['variants_lt-LT']);
        $this->assertEquals('Raudona', $product->additionalFields['variants_lt-LT'][0]['attributes']['color']);
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
        $this->assertEquals(49.99, $result->price);
        $this->assertEquals('TEST-SKU', $result->additionalFields['sku']);
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
        $this->assertEquals(29.99, $result->price);
        $this->assertEquals(39.99, $result->basePrice);
        $this->assertEquals(24.79, $result->priceTaxExcluded);
        $this->assertEquals(33.05, $result->basePriceTaxExcluded);
        $this->assertEquals('http://example.com/product/variant', $result->productUrl);
        $this->assertInstanceOf(ImageUrl::class, $result->imageUrl);
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
                            'localizedNames' => [
                                'en-US' => 'Material',
                            ],
                            'localizedValues' => [
                                'en-US' => 'Cotton',
                            ],
                        ],
                        [
                            'localizedNames' => [
                                'en-US' => 'Weight',
                                'lt-LT' => 'Svoris',
                            ],
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

        $this->assertArrayHasKey('features', $product->additionalFields);
        $this->assertCount(2, $product->additionalFields['features']);
        $this->assertEquals('Material', $product->additionalFields['features'][0]['name']);
        $this->assertEquals('Cotton', $product->additionalFields['features'][0]['value']);

        $this->assertArrayHasKey('features_lt-LT', $product->additionalFields);
        $this->assertCount(1, $product->additionalFields['features_lt-LT']);
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

        $this->assertArrayHasKey('tags', $product->additionalFields);
        $this->assertEquals(['summer', 'sale', 'new'], $product->additionalFields['tags']);

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

        $this->assertArrayHasKey('inStock', $product->additionalFields);
        $this->assertTrue($product->additionalFields['inStock']);
        $this->assertArrayHasKey('isNew', $product->additionalFields);
        $this->assertFalse($product->additionalFields['isNew']);
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

        // HTML tags should be stripped
        $this->assertArrayHasKey('description', $product->additionalFields);
        $this->assertEquals('This is a test product.', $product->additionalFields['description']);
        $this->assertArrayHasKey('descriptionShort', $product->additionalFields);
        $this->assertEquals('Short description', $product->additionalFields['descriptionShort']);
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

        $this->assertArrayHasKey('categoryDefault', $product->additionalFields);
        $this->assertEquals('Men > Shoes > Sneakers', $product->additionalFields['categoryDefault']);
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
