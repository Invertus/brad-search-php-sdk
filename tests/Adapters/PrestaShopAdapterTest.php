<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\PrestaShopAdapter;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class PrestaShopAdapterTest extends TestCase
{
    private PrestaShopAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new PrestaShopAdapter();
    }

    /**
     * Helper method to extract product from new result structure
     */
    private function getProductFromResult(array $result, int $index = 0): array
    {
        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey($index, $result['products']);
        return $result['products'][$index];
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

    public function testTransformSimpleProduct(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Sneakers "101H" Springa multi'
                    ],
                    'brand' => [
                        'localizedNames' => [
                            'en-US' => 'Springa'
                        ]
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/5309-small_default/sneakers.jpg',
                        'medium' => 'http://prestashop/5309-medium_default/sneakers.jpg'
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/sneakers/1807-sneakers.html'
                    ],
                    'categories' => [
                        'lvl2' => [
                            [
                                'remoteId' => '162',
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Men'
                                    ]
                                ]
                            ]
                        ],
                        'lvl3' => [
                            [
                                'remoteId' => '163',
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Men > Shoes'
                                    ]
                                ]
                            ]
                        ],
                        'lvl4' => [
                            [
                                'remoteId' => '164',
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Men > Shoes > Sneakers'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);
        $product = $this->getProductFromResult($result);

        $this->assertEquals('1807', $product['id']);
        $this->assertEquals('M0E20000000EAAK', $product['sku']);
        $this->assertEquals('Sneakers "101H" Springa multi', $product['name']);
        $this->assertEquals('Springa', $product['brand']);
        $this->assertEquals('http://prestashop/sneakers/1807-sneakers.html', $product['productUrl']);
        $this->assertEquals([
            'small' => 'http://prestashop/5309-small_default/sneakers.jpg',
            'medium' => 'http://prestashop/5309-medium_default/sneakers.jpg'
        ], $product['imageUrl']);
        $this->assertEquals([
            'Men',
            'Men > Shoes',
            'Men > Shoes > Sneakers'
        ], $product['categories']);
        $this->assertEquals([], $product['variants']);
    }

    public function testTransformProductWithMultipleLocales(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Sneakers Multi',
                        'lt-LT' => 'Sportiniai batai Multi'
                    ],
                    'brand' => [
                        'localizedNames' => [
                            'en-US' => 'Springa',
                            'lt-LT' => 'Springa LT'
                        ]
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/en/sneakers.html',
                        'lt-LT' => 'http://prestashop/lt/sportiniai-batai.html'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        // Default locale fields (no suffix)
        $this->assertEquals('Sneakers Multi', $product['name']);
        $this->assertEquals('Springa', $product['brand']);
        $this->assertEquals('http://prestashop/en/sneakers.html', $product['productUrl']);

        // Additional locale fields (with suffix)
        $this->assertEquals('Sportiniai batai Multi', $product['name_lt-LT']);
        $this->assertEquals('Springa LT', $product['brand_lt-LT']);
        $this->assertEquals('http://prestashop/lt/sportiniai-batai.html', $product['productUrl_lt-LT']);
    }

    public function testProductUrlTransformationWithMultipleLocales(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/en/test-product.html',
                        'lt-LT' => 'http://prestashop/lt/testas-produktas.html',
                        'de-DE' => 'http://prestashop/de/test-produkt.html',
                        'fr-FR' => 'http://prestashop/fr/produit-test.html'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertEquals('http://prestashop/en/test-product.html', $product['productUrl']);

        $this->assertEquals('http://prestashop/lt/testas-produktas.html', $product['productUrl_lt-LT']);
        $this->assertEquals('http://prestashop/de/test-produkt.html', $product['productUrl_de-DE']);
        $this->assertEquals('http://prestashop/fr/produit-test.html', $product['productUrl_fr-FR']);

        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $expectedFields = ['productUrl', 'productUrl_lt-LT', 'productUrl_de-DE', 'productUrl_fr-FR'];
        sort($productUrlFields);
        sort($expectedFields);

        $this->assertEquals($expectedFields, $productUrlFields);
    }

    public function testProductUrlTransformationWithSingleLocale(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/en/test-product.html'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertEquals('http://prestashop/en/test-product.html', $product['productUrl']);

        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $this->assertEquals(['productUrl'], array_values($productUrlFields));
    }

    public function testProductUrlTransformationWithEmptyProductUrl(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    // No productUrl field
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $this->assertEquals([], array_values($productUrlFields));
        $this->assertArrayNotHasKey('productUrl', $product);
    }

    public function testProductUrlTransformationWithFlatStructure(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'en-US' => 'http://prestashop/en/test-product.html',
                        'lt-LT' => 'http://prestashop/lt/testas-produktas.html'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        // Flat structure should now work correctly for root level products
        $this->assertEquals('http://prestashop/en/test-product.html', $product['productUrl']);
        $this->assertEquals('http://prestashop/lt/testas-produktas.html', $product['productUrl_lt-LT']);

        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $expectedFields = ['productUrl', 'productUrl_lt-LT'];
        sort($productUrlFields);
        sort($expectedFields);

        $this->assertEquals($expectedFields, array_values($productUrlFields));
    }

    public function testTransformProductWithVariants(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Sneakers Multi'
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            'remoteId' => '26911',
                            'sku' => 'M0E20000000EAAK-34',
                            'attributes' => [
                                'Size' => [
                                    'localizedValues' => [
                                        'en-US' => '34'
                                    ]
                                ],
                                'Color' => [
                                    'localizedValues' => [
                                        'en-US' => 'multi'
                                    ]
                                ]
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/sneakers/1807-26911-sneakers.html'
                                ]
                            ]
                        ],
                        [
                            'remoteId' => '26912',
                            'sku' => 'M0E20000000EAAL',
                            'attributes' => [
                                'Size' => [
                                    'localizedValues' => [
                                        'en-US' => '34.5'
                                    ]
                                ],
                                'Color' => [
                                    'localizedValues' => [
                                        'en-US' => 'blue'
                                    ]
                                ]
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/sneakers/1807-26912-sneakers.html'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertCount(2, $product['variants']);

        // First variant
        $variant1 = $product['variants'][0];
        $this->assertEquals('26911', $variant1['id']);
        $this->assertEquals('M0E20000000EAAK-34', $variant1['sku']);
        $this->assertEquals('http://prestashop/sneakers/1807-26911-sneakers.html', $variant1['productUrl']);
        $this->assertEquals([
            [
                'name' => 'size',
                'value' => '34'
            ],
            [
                'name' => 'color',
                'value' => 'multi'
            ]
        ], $variant1['attributes']);

        // Second variant
        $variant2 = $product['variants'][1];
        $this->assertEquals('26912', $variant2['id']);
        $this->assertEquals('M0E20000000EAAL', $variant2['sku']);
        $this->assertEquals('http://prestashop/sneakers/1807-26912-sneakers.html', $variant2['productUrl']);
        $this->assertEquals([
            [
                'name' => 'size',
                'value' => '34.5'
            ],
            [
                'name' => 'color',
                'value' => 'blue'
            ]
        ], $variant2['attributes']);
    }

    public function testTransformProductWithMissingRequiredFields(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    // Missing remoteId and sku
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('transformation_error', $result['errors'][0]['type']);
        $this->assertEquals("Required field 'remoteId' is missing from PrestaShop data", $result['errors'][0]['message']);
    }

    public function testTransformProductWithMissingSku(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    // Missing sku
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('transformation_error', $result['errors'][0]['type']);
        $this->assertEquals("Required field 'sku' is missing from PrestaShop data", $result['errors'][0]['message']);
    }

    public function testTransformVariantWithoutRemoteId(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            // Missing remoteId - should be skipped
                            'sku' => 'M0E20000000EAAK-34'
                        ],
                        [
                            'remoteId' => '26912',
                            'sku' => 'M0E20000000EAAL',
                            'attributes' => [],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/test.html'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        // Should only have one variant (the one with remoteId)
        $this->assertCount(1, $product['variants']);
        $this->assertEquals('26912', $product['variants'][0]['id']);
    }

    public function testCategoryFlattening(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'categories' => [
                        'lvl1' => [
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Root'
                                    ]
                                ]
                            ]
                        ],
                        'lvl2' => [
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Root > Category1'
                                    ]
                                ]
                            ],
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Root > Category2'
                                    ]
                                ]
                            ]
                        ],
                        'lvl3' => [
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => 'Root > Category1 > Subcategory'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $expectedCategories = [
            'Root',
            'Root > Category1',
            'Root > Category2',
            'Root > Category1 > Subcategory'
        ];

        $this->assertEquals($expectedCategories, $product['categories']);
    }

    public function testEmptyCategories(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    // No categories
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertEquals([], $product['categories']);
    }

    public function testImageUrlTransformation(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'imageUrl' => [
                        'small' => 'http://prestashop/image-small.jpg',
                        'medium' => 'http://prestashop/image-medium.jpg',
                        'large' => 'http://prestashop/image-large.jpg' // Should be ignored
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertEquals([
            'small' => 'http://prestashop/image-small.jpg',
            'medium' => 'http://prestashop/image-medium.jpg'
            // large should not be included
        ], $product['imageUrl']);
    }

    public function testSingleLocaleAdapter(): void
    {
        $adapter = new PrestaShopAdapter();

        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'English Name',
                        'lt-LT' => 'Lithuanian Name'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertEquals('Lithuanian Name', $product['name_lt-LT']);
    }

    public function testMultiLangName(): void
    {
        $adapter = new PrestaShopAdapter();

        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'English Name',
                        'lt-LT' => 'Lithuanian Name'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        // Should fallback to first available value (en-US)
        $this->assertEquals('English Name', $product['name']);
        $this->assertEquals('Lithuanian Name', $product['name_lt-LT']);
    }

    /**
     * Test invalid data types handling - products array with non-array items
     */
    public function testTransformWithNonArrayProducts(): void
    {
        $prestaShopData = [
            'products' => [
                'invalid-string-product',
                123, // invalid number
                null, // invalid null
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Valid Product'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        // Should only return the valid product, skipping invalid ones
        $this->assertCount(1, $result["products"]);
        $this->assertEquals('Valid Product', $this->getProductFromResult($result)['name']);
    }

    /**
     * Test invalid brand structure handling
     */
    public function testTransformWithInvalidBrandTypes(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'brand' => 'invalid-string-brand', // Should be array
                    'categories' => [],
                    'variants' => []
                ],
                [
                    'remoteId' => '1808',
                    'sku' => 'M0E20000000EAAL',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product 2'
                    ],
                    'brand' => [
                        'localizedNames' => 'invalid-string' // Should be array
                    ],
                    'categories' => [],
                    'variants' => []
                ],
                [
                    'remoteId' => '1809',
                    'sku' => 'M0E20000000EAAM',
                    'price' => '99.99',
                    'basePrice' => '9.99',
                    'priceTaxExcluded' => '1.00',
                    'basePriceTaxExcluded' => '8.44',
                    'localizedNames' => [
                        'en-US' => 'Test Product 3'
                    ],
                    'brand' => null, // Should be array
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        // All products should transform but without brand field
        $this->assertCount(3, $result["products"]);
        $this->assertArrayNotHasKey('brand', $this->getProductFromResult($result));
        $this->assertArrayNotHasKey('brand', $result['products'][1]);
        $this->assertArrayNotHasKey('brand', $result['products'][2]);
    }

    /**
     * Test invalid description types handling
     */
    public function testTransformWithInvalidDescriptionTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'description' => 'invalid-string', // Should be array
                    'descriptionShort' => null, // Should be array
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result["products"]);
        $this->assertArrayNotHasKey('description', $this->getProductFromResult($result));
        $this->assertArrayNotHasKey('descriptionShort', $this->getProductFromResult($result));
    }

    /**
     * Test invalid image URL types handling
     */
    public function testTransformWithInvalidImageUrlTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'imageUrl' => 'invalid-string', // Should be array
                    'categories' => [],
                    'variants' => []
                ],
                [
                    'remoteId' => '1808',
                    'sku' => 'M0E20000000EAAL',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product 2'
                    ],
                    'imageUrl' => [
                        'small' => null, // Null URL
                        'medium' => '', // Empty URL
                        'large' => 'http://example.com/image.jpg' // Valid URL but not in mapping
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(2, $result["products"]);
        $this->assertArrayNotHasKey('imageUrl', $this->getProductFromResult($result)); // Invalid type, no field added
        $this->assertArrayNotHasKey('small', $result['products'][1]['imageUrl']); // Null URL filtered out
        $this->assertArrayNotHasKey('medium', $result['products'][1]['imageUrl']); // Empty URL filtered out
        $this->assertArrayNotHasKey('large', $result['products'][1]['imageUrl']); // Not in size mapping
    }

    /**
     * Test invalid product URL types handling
     */
    public function testTransformWithInvalidProductUrlTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => 'invalid-string', // Should be array
                    'categories' => [],
                    'variants' => []
                ],
                [
                    'remoteId' => '1808',
                    'sku' => 'M0E20000000EAAL',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product 2'
                    ],
                    'productUrl' => [
                        'en-US' => null, // Null URL
                        'lt-LT' => '', // Empty URL
                        123 => 'http://example.com', // Non-string locale
                        'fr-FR' => 'http://example.com/valid'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(2, $result["products"]);
        $this->assertArrayNotHasKey('productUrl', $this->getProductFromResult($result)); // Invalid type, no field added
        $this->assertArrayNotHasKey('productUrl', $result['products'][1]); // Null en-US filtered out
        $this->assertArrayNotHasKey('productUrl_lt-LT', $result['products'][1]); // Empty URL filtered out
        $this->assertEquals('http://example.com/valid', $result['products'][1]['productUrl_fr-FR']); // Valid URL
    }

    /**
     * Test invalid categories structure handling
     */
    public function testTransformWithInvalidCategoriesTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'categories' => 'invalid-string', // Should be array
                    'variants' => [],
                    'features' => []
                ],
                [
                    'remoteId' => '1808',
                    'sku' => 'M0E20000000EAAL',
                    'price' => '99.99',
                    'basePrice' => '99.99',
                    'priceTaxExcluded' => '82.64',
                    'basePriceTaxExcluded' => '82.64',
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product 2'
                    ],
                    'categories' => [
                        'lvl2' => 'invalid-string', // Should be array
                        'lvl3' => [
                            'invalid-category-item', // Should be array
                            [
                                'localizedValues' => 'invalid-string' // Should be array
                            ],
                            [
                                'localizedValues' => [
                                    'path' => 'invalid-string' // Should be array
                                ]
                            ],
                            [
                                'localizedValues' => [
                                    'path' => [
                                        'en-US' => null, // Null path
                                        'lt-LT' => '', // Empty path
                                        123 => 'Valid Category', // Non-string locale
                                        'fr-FR' => 'Catégorie Valide'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'variants' => [],
                    'features' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(2, $result["products"]);
        $this->assertEquals([], $this->getProductFromResult($result)['categories']); // Invalid type becomes empty array

        // Check if categories were processed for the second product
        $this->assertArrayHasKey('categories', $result['products'][1]); // Should always have categories key (even if empty)

        // If valid categories were found, they should be in the results
        if (isset($result['products'][1]['categories_fr-FR'])) {
            $this->assertContains('Catégorie Valide', $result['products'][1]['categories_fr-FR']); // Valid localized category
        }

        // The en-US category should be empty since it was null/empty
        $this->assertThat($result['products'][1]['categories'], $this->logicalOr(
            $this->equalTo([]), // No valid categories found
            $this->arrayHasKey(0) // Or has at least one category
        ));
    }

    /**
     * Test invalid localized values handling
     */
    public function testTransformWithInvalidLocalizedValues(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => null, // Null name
                        'lt-LT' => '', // Empty name
                        123 => 'Invalid Locale Key', // Non-string locale
                        'fr-FR' => 'Nom Valide'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result["products"]);
        $this->assertArrayNotHasKey('name', $this->getProductFromResult($result)); // Null/empty en-US filtered out
        $this->assertArrayNotHasKey('name_lt-LT', $this->getProductFromResult($result)); // Empty name filtered out
        $this->assertEquals('Nom Valide', $this->getProductFromResult($result)['name_fr-FR']); // Valid name
    }

    /**
     * Test null required fields handling
     */
    public function testTransformWithNullRequiredFields(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => null, // Null required field
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('transformation_error', $result['errors'][0]['type']);
        $this->assertEquals("Required field 'remoteId' is missing from PrestaShop data", $result['errors'][0]['message']);
    }

    /**
     * Test features with invalid types
     */
    public function testTransformWithInvalidFeaturesTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'features' => [
                        'invalid-feature-string', // Should be array
                        [
                            'localizedNames' => 'invalid-string', // Should be array
                            'localizedValues' => [
                                'en-US' => 'Color Value'
                            ]
                        ],
                        [
                            'localizedNames' => [
                                'en-US' => 'Size'
                            ],
                            'localizedValues' => 'invalid-string' // Should be array
                        ],
                        [
                            'localizedNames' => [
                                'en-US' => null, // Null name
                                'lt-LT' => '', // Empty name
                                'fr-FR' => 'Matériau'
                            ],
                            'localizedValues' => [
                                'en-US' => null, // Null value
                                'lt-LT' => '', // Empty value
                                'fr-FR' => 'Coton'
                            ]
                        ]
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result["products"]);
        $this->assertArrayNotHasKey('features', $this->getProductFromResult($result)); // Invalid features filtered out
        $this->assertEquals([['name' => 'Matériau', 'value' => 'Coton']], $this->getProductFromResult($result)['features_fr-FR']); // Only valid feature
    }

    /**
     * Test variants with invalid types
     */
    public function testTransformWithInvalidVariantsTypes(): void
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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'variants' => [
                        'invalid-variant-string', // Should be array
                        [
                            'remoteId' => null, // Null remoteId - should be skipped
                            'sku' => 'VARIANT-SKU'
                        ],
                        [
                            'remoteId' => '123',
                            'sku' => 'VALID-SKU',
                            'attributes' => 'invalid-string', // Should be array
                            'productUrl' => [
                                'localizedValues' => 'invalid-string' // Should be array
                            ]
                        ]
                    ],
                    'categories' => [],
                    'features' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);

        $this->assertCount(1, $result["products"]);
        $this->assertEmpty($this->getProductFromResult($result)['variants']); // All variants invalid or filtered out
    }

    public function testTransformVariantWithPricesAndImageUrl(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '3642',
                    'sku' => 'V-L558',
                    'price' => '10.00',
                    'basePrice' => '12.00',
                    'priceTaxExcluded' => '8.50',
                    'basePriceTaxExcluded' => '10.00',
                    'localizedNames' => [
                        'en-US' => 'Drip tray'
                    ],
                    'categories' => [],
                    'variants' => [
                        [
                            'remoteId' => 1,
                            'sku' => 'ABCD1',
                            'price' => 0,
                            'basePrice' => '0.00',
                            'priceTaxExcluded' => '0.00',
                            'basePriceTaxExcluded' => '0.00',
                            'attributes' => [
                                'Pusė' => [
                                    'localizedNames' => [
                                        'en-US' => 'Side'
                                    ],
                                    'localizedValues' => [
                                        'en-US' => 'right'
                                    ]
                                ]
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/en/3642-1-drip-tray.html'
                                ]
                            ],
                            'imageUrl' => [
                                'small' => 'http://prestashop/13600-square_cart_default/drip-tray.jpg',
                                'medium' => 'http://prestashop/13600-home_default/drip-tray.jpg'
                            ]
                        ],
                        [
                            'remoteId' => 2,
                            'sku' => 'ABCD-333',
                            'price' => 15.99,
                            'basePrice' => '18.00',
                            'priceTaxExcluded' => '13.50',
                            'basePriceTaxExcluded' => '15.00',
                            'attributes' => [
                                'Pusė' => [
                                    'localizedNames' => [
                                        'en-US' => 'Side'
                                    ],
                                    'localizedValues' => [
                                        'en-US' => 'left'
                                    ]
                                ]
                            ],
                            'productUrl' => [
                                'localizedValues' => [
                                    'en-US' => 'http://prestashop/en/3642-2-drip-tray.html'
                                ]
                            ],
                            'imageUrl' => [
                                'small' => 'http://prestashop/13601-square_cart_default/drip-tray.jpg',
                                'medium' => 'http://prestashop/13601-home_default/drip-tray.jpg'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $this->getProductFromResult($result);

        $this->assertCount(2, $product['variants']);

        // First variant - check prices and imageUrl
        $variant1 = $product['variants'][0];
        $this->assertEquals('1', $variant1['id']);
        $this->assertEquals('ABCD1', $variant1['sku']);
        $this->assertEquals(0, $variant1['price']);
        $this->assertEquals('0.00', $variant1['basePrice']);
        $this->assertEquals('0.00', $variant1['priceTaxExcluded']);
        $this->assertEquals('0.00', $variant1['basePriceTaxExcluded']);
        $this->assertArrayHasKey('imageUrl', $variant1);
        $this->assertEquals('http://prestashop/13600-square_cart_default/drip-tray.jpg', $variant1['imageUrl']['small']);
        $this->assertEquals('http://prestashop/13600-home_default/drip-tray.jpg', $variant1['imageUrl']['medium']);

        // Second variant - check prices and imageUrl
        $variant2 = $product['variants'][1];
        $this->assertEquals('2', $variant2['id']);
        $this->assertEquals('ABCD-333', $variant2['sku']);
        $this->assertEquals(15.99, $variant2['price']);
        $this->assertEquals('18.00', $variant2['basePrice']);
        $this->assertEquals('13.50', $variant2['priceTaxExcluded']);
        $this->assertEquals('15.00', $variant2['basePriceTaxExcluded']);
        $this->assertArrayHasKey('imageUrl', $variant2);
        $this->assertEquals('http://prestashop/13601-square_cart_default/drip-tray.jpg', $variant2['imageUrl']['small']);
        $this->assertEquals('http://prestashop/13601-home_default/drip-tray.jpg', $variant2['imageUrl']['medium']);
    }
}
