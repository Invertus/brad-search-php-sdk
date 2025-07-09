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
                    'formattedPrice' => '$99.99',
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
                        'localizedValues' => [
                            'en-US' => 'http://prestashop/sneakers/1807-sneakers.html'
                        ]
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

        $this->assertCount(1, $result);
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
                        'localizedValues' => [
                            'en-US' => 'http://prestashop/en/sneakers.html',
                            'lt-LT' => 'http://prestashop/lt/sportiniai-batai.html'
                        ]
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'localizedValues' => [
                            'en-US' => 'http://prestashop/en/test-product.html',
                            'lt-LT' => 'http://prestashop/lt/testas-produktas.html',
                            'de-DE' => 'http://prestashop/de/test-produkt.html',
                            'fr-FR' => 'http://prestashop/fr/produit-test.html'
                        ]
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'localizedValues' => [
                            'en-US' => 'http://prestashop/en/test-product.html'
                        ]
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $this->assertEquals([], array_values($productUrlFields));
        $this->assertArrayNotHasKey('productUrl', $product);
    }

    public function testProductUrlTransformationWithNestedLocalizedValues(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    'productUrl' => [
                        'localizedValues' => [
                            'lt-LT' => 'http://prestashop.com/lt/product',
                            'en-US' => 'http://prestashop.com/en/product'
                        ]
                    ],
                    'categories' => [],
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result[0];

        // Default locale (en-US) should be stored without suffix
        $this->assertEquals('http://prestashop.com/en/product', $product['productUrl']);

        // Other locales should be stored with locale suffix
        $this->assertEquals('http://prestashop.com/lt/product', $product['productUrl_lt-LT']);

        // Verify only the expected product URL fields exist
        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $expectedFields = ['productUrl', 'productUrl_lt-LT'];
        sort($productUrlFields);
        sort($expectedFields);

        $this->assertEquals($expectedFields, $productUrlFields);
    }

    public function testProductUrlTransformationIgnoresFlatStructure(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

        // No productUrl fields should exist since flat structure is ignored
        $productUrlFields = array_filter(array_keys($product), function ($key) {
            return strpos($key, 'productUrl') === 0;
        });

        $this->assertEquals([], array_values($productUrlFields));
        $this->assertArrayNotHasKey('productUrl', $product);
    }

    public function testTransformProductWithVariants(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

        $this->assertCount(2, $product['variants']);

        // First variant
        $variant1 = $product['variants'][0];
        $this->assertEquals('26911', $variant1['id']);
        $this->assertEquals('M0E20000000EAAK-34', $variant1['sku']);
        $this->assertEquals('http://prestashop/sneakers/1807-26911-sneakers.html', $variant1['url']);
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
        $this->assertEquals('http://prestashop/sneakers/1807-26912-sneakers.html', $variant2['url']);
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Required field 'remoteId' is missing from PrestaShop data");
        $this->adapter->transform($prestaShopData);
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Required field 'sku' is missing from PrestaShop data");
        $this->adapter->transform($prestaShopData);
    }

    public function testTransformVariantWithoutRemoteId(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
                    'price' => '99.99',
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
                    'localizedNames' => [
                        'en-US' => 'Test Product'
                    ],
                    // No categories
                    'variants' => []
                ]
            ]
        ];

        $result = $this->adapter->transform($prestaShopData);
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

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
                    'formattedPrice' => '$99.99',
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
        $product = $result[0];

        // Should fallback to first available value (en-US)
        $this->assertEquals('English Name', $product['name']);
        $this->assertEquals('Lithuanian Name', $product['name_lt-LT']);
    }
}
