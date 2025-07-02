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
        $this->adapter = new PrestaShopAdapter(['en-US', 'lt-LT']);
    }

    public function testConstructorWithEmptyLocales(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('At least one locale must be specified');
        new PrestaShopAdapter([]);
    }

    public function testGetSupportedLocales(): void
    {
        $this->assertEquals(['en-US', 'lt-LT'], $this->adapter->getSupportedLocales());
    }

    public function testGetDefaultLocale(): void
    {
        $this->assertEquals('en-US', $this->adapter->getDefaultLocale());
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
        $product = $result[0];

        // Default locale fields (no suffix)
        $this->assertEquals('Sneakers Multi', $product['name']);
        $this->assertEquals('Springa', $product['brand']);
        $this->assertEquals('http://prestashop/en/sneakers.html', $product['productUrl']);

        // Additional locale fields (with suffix)
        $this->assertEquals('Sportiniai batai Multi', $product['name_lt-LT']);
        $this->assertEquals('Springa LT', $product['brand_lt-LT']);
    }

    public function testTransformProductWithVariants(): void
    {
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
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
            'size' => [
                'name' => 'size',
                'value' => '34'
            ],
            'color' => [
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
            'size' => [
                'name' => 'size',
                'value' => '34.5'
            ],
            'color' => [
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
        $adapter = new PrestaShopAdapter(['lt-LT']);
        
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
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

        // Should use lt-LT as default since it's the only supported locale
        $this->assertEquals('Lithuanian Name', $product['name']);
        // Should not have any suffixed fields since there's only one locale
        $this->assertArrayNotHasKey('name_lt-LT', $product);
    }

    public function testFallbackToFirstAvailableValue(): void
    {
        $adapter = new PrestaShopAdapter(['fr-FR', 'de-DE']); // Locales not in data
        
        $prestaShopData = [
            'products' => [
                [
                    'remoteId' => '1807',
                    'sku' => 'M0E20000000EAAK',
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
    }
} 