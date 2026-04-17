<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\ShopifyAdapter;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ShopifyAdapterTest extends TestCase
{
    private ShopifyAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new ShopifyAdapter();
    }

    // ─── Basic transform without locales (backward compat) ───

    public function testTransformWithoutLocalesReturnsPlainFieldNames(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports'),
        ]);

        $result = $this->adapter->transform($data);

        $this->assertCount(1, $result['products']);
        $this->assertEmpty($result['errors']);

        $product = $result['products'][0];
        $this->assertEquals('1', $product['id']);
        $this->assertEquals('Snowboard', $product['name']);
        $this->assertEquals('Great board', $product['description']);
        $this->assertEquals('BrandX', $product['brand']);
        $this->assertEquals('Sports', $product['categoryDefault']);
        $this->assertContains('Sports', $product['categories']);
    }

    public function testTransformWithoutLocalesOmitsEmptyDescription(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', '', 'BrandX', 'Sports'),
        ]);

        $result = $this->adapter->transform($data);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('description', $product);
    }

    public function testTransformWithoutLocalesOmitsEmptyBrand(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', '', 'Sports'),
        ]);

        $result = $this->adapter->transform($data);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('brand', $product);
    }

    // ─── Transform with locales ───

    public function testTransformWithLocalesProducesSuffixedFields(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports'),
        ], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $product = $result['products'][0];

        // Primary locale uses native fields
        $this->assertEquals('Snowboard', $product['name_en']);
        $this->assertEquals('Great board', $product['description_en']);

        // Non-primary falls back to primary when no translations
        $this->assertEquals('Snowboard', $product['name_lt']);
        $this->assertEquals('Great board', $product['description_lt']);

        // Brand is not translatable in Shopify — same across all locales
        $this->assertEquals('BrandX', $product['brand_en']);
        $this->assertEquals('BrandX', $product['brand_lt']);
        // categoryDefault falls back to primary when no product_type translation
        $this->assertEquals('Sports', $product['categoryDefault_en']);
        $this->assertEquals('Sports', $product['categoryDefault_lt']);

        // Plain field names should NOT exist
        $this->assertArrayNotHasKey('name', $product);
        $this->assertArrayNotHasKey('description', $product);
        $this->assertArrayNotHasKey('brand', $product);
    }

    public function testTransformWithTranslationsUsesTranslatedValues(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', '<p>Great board</p>', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => 'Snieglentė'],
                ['key' => 'body_html', 'value' => '<p>Puiki lenta</p>'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('Snowboard', $p['name_en']);
        $this->assertEquals('Great board', $p['description_en']);
        $this->assertEquals('Snieglentė', $p['name_lt']);
        $this->assertEquals('Puiki lenta', $p['description_lt']);
    }

    public function testTransformWithProductTypeTranslation(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports');
        $product['node']['tags'] = ['winter', 'outdoor'];
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => 'Snieglentė'],
                ['key' => 'product_type', 'value' => 'Sportas'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');
        $result = $this->adapter->transform($data, ['en', 'lt']);
        $p = $result['products'][0];

        // Primary locale uses native productType
        $this->assertEquals('Sports', $p['categoryDefault_en']);
        $this->assertContains('Sports', $p['categories_en']);

        // Non-primary uses translated product_type
        $this->assertEquals('Sportas', $p['categoryDefault_lt']);
        $this->assertContains('Sportas', $p['categories_lt']);
        // Tags are not translatable — still in English
        $this->assertContains('winter', $p['categories_lt']);
        $this->assertContains('outdoor', $p['categories_lt']);
        // Original English productType should NOT be in lt categories
        $this->assertNotContains('Sports', $p['categories_lt']);
    }

    public function testBrandIsNotTranslatable(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => 'Snieglentė'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');
        $result = $this->adapter->transform($data, ['en', 'lt']);
        $p = $result['products'][0];

        // Brand stays English for all locales — Shopify does not support vendor translation
        $this->assertEquals('BrandX', $p['brand_en']);
        $this->assertEquals('BrandX', $p['brand_lt']);
    }

    public function testTransformWithMissingTranslationFallsBackToPrimary(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => 'Snieglentė'],
                // body_html is missing — should fall back to primary
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('Snieglentė', $p['name_lt']);
        $this->assertEquals('Great board', $p['description_lt']); // fallback
    }

    public function testTransformWithEmptyStringTranslationFallsBack(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => ''],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('Snowboard', $p['name_lt']); // empty string → fallback
    }

    public function testTransformWithNullTranslationValueFallsBack(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Great board', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => null],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('Snowboard', $p['name_lt']); // null → fallback
    }

    public function testThreeLocalesWithPartialTranslations(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', '<p>Great board</p>', 'BrandX', 'Sports');
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'title', 'value' => 'Snieglentė'],
                ['key' => 'body_html', 'value' => '<p>Puiki lenta</p>'],
                ['key' => 'product_type', 'value' => 'Sportas'],
            ],
            // fr has no translations at all
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt', 'fr']);

        $p = $result['products'][0];

        // Primary (en): native fields
        $this->assertEquals('Snowboard', $p['name_en']);
        $this->assertEquals('Great board', $p['description_en']);
        $this->assertEquals('Sports', $p['categoryDefault_en']);

        // lt: has translations
        $this->assertEquals('Snieglentė', $p['name_lt']);
        $this->assertEquals('Puiki lenta', $p['description_lt']);
        $this->assertEquals('Sportas', $p['categoryDefault_lt']);

        // fr: no translations — falls back to English
        $this->assertEquals('Snowboard', $p['name_fr']);
        $this->assertEquals('Great board', $p['description_fr']);
        $this->assertEquals('Sports', $p['categoryDefault_fr']);

        // Brand is same across all three
        $this->assertEquals('BrandX', $p['brand_en']);
        $this->assertEquals('BrandX', $p['brand_lt']);
        $this->assertEquals('BrandX', $p['brand_fr']);
    }

    // ─── Backward compatibility: empty locales ───

    public function testEmptyLocalesArrayProducesPlainFields(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports'),
        ]);

        $result = $this->adapter->transform($data, []);

        $product = $result['products'][0];
        $this->assertArrayHasKey('name', $product);
        $this->assertArrayNotHasKey('name_en', $product);
    }

    // ─── Variant options with locales ───

    public function testVariantsUseAttrsFormatWithLocales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/1001', 'name' => 'Color', 'values' => ['White', 'Black']],
            ['id' => 'gid://shopify/ProductOption/1002', 'name' => 'Size', 'values' => ['S', 'M', 'L']],
        ];
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/100',
                        'sku' => 'SNO-001',
                        'selectedOptions' => [
                            ['name' => 'Color', 'value' => 'White'],
                            ['name' => 'Size', 'value' => 'L'],
                        ],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $variant = $result['products'][0]['variants'][0];
        $this->assertArrayHasKey('attrs', $variant);
        $this->assertArrayNotHasKey('attributes', $variant);
        $this->assertEquals(['en' => 'White', 'lt' => 'White'], $variant['attrs']['color']);
        $this->assertEquals(['en' => 'L', 'lt' => 'L'], $variant['attrs']['size']);
    }

    public function testVariantsUseAttributesFormatWithoutLocales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/1001', 'name' => 'Color', 'values' => ['White', 'Black']],
        ];
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/100',
                        'sku' => 'SNO-001',
                        'selectedOptions' => [
                            ['name' => 'Color', 'value' => 'White'],
                        ],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertArrayHasKey('attributes', $variant);
        $this->assertArrayNotHasKey('attrs', $variant);
        $this->assertEquals([['name' => 'color', 'value' => 'White']], $variant['attributes']);
    }

    public function testVariantAttrsUseSlugKeyWhenProductOptionsAbsent(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        // No product-level options field; slug is derived from selectedOptions[].name
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/100',
                        'sku' => 'SNO-001',
                        'selectedOptions' => [
                            ['name' => 'Color', 'value' => 'White'],
                        ],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en']);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals(['en' => 'White'], $variant['attrs']['color']);
    }

    /**
     * Two products with different per-product option GIDs but the same option
     * name must collapse to the same slug key. This is the invariant the
     * shared Elasticsearch mapping relies on to avoid field explosion.
     */
    public function testSameOptionNameAcrossProductsCollapsesToSingleSlug(): void
    {
        $productA = $this->makeProduct('gid://shopify/Product/1', 'A', 'Desc', 'BrandX', 'Sports');
        $productA['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/111', 'name' => 'Color', 'values' => ['Red']],
        ];
        $productA['node']['variants'] = [
            'edges' => [[
                'node' => [
                    'id' => 'gid://shopify/ProductVariant/11',
                    'sku' => 'A-1',
                    'selectedOptions' => [['name' => 'Color', 'value' => 'Red']],
                ],
            ]],
        ];

        $productB = $this->makeProduct('gid://shopify/Product/2', 'B', 'Desc', 'BrandX', 'Sports');
        $productB['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/999', 'name' => 'Color', 'values' => ['Blue']],
        ];
        $productB['node']['variants'] = [
            'edges' => [[
                'node' => [
                    'id' => 'gid://shopify/ProductVariant/22',
                    'sku' => 'B-1',
                    'selectedOptions' => [['name' => 'Color', 'value' => 'Blue']],
                ],
            ]],
        ];

        $data = $this->makeShopifyResponse([$productA, $productB], 'en');

        $result = $this->adapter->transform($data, ['en']);

        $variantA = $result['products'][0]['variants'][0];
        $variantB = $result['products'][1]['variants'][0];

        $this->assertEquals(['en' => 'Red'], $variantA['attrs']['color']);
        $this->assertEquals(['en' => 'Blue'], $variantB['attrs']['color']);
        $this->assertArrayNotHasKey('gid://shopify/ProductOption/111', $variantA['attrs']);
        $this->assertArrayNotHasKey('gid://shopify/ProductOption/999', $variantB['attrs']);
    }

    /**
     * Slug rule must be Unicode-safe and produce lowercase dash-joined keys.
     * Must match bradsearch-shopify-app1 AttributesController::slugify().
     */
    public function testSlugifiesMultiWordAndUnicodeOptionNames(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Shoe Size', 'values' => ['42']],
            ['id' => 'gid://shopify/ProductOption/2', 'name' => 'Größe', 'values' => ['M']],
            ['id' => 'gid://shopify/ProductOption/3', 'name' => 'Material / Fabric', 'values' => ['Cotton']],
        ];
        $product['node']['variants'] = [
            'edges' => [[
                'node' => [
                    'id' => 'gid://shopify/ProductVariant/100',
                    'sku' => 'SNO-001',
                    'selectedOptions' => [
                        ['name' => 'Shoe Size', 'value' => '42'],
                        ['name' => 'Größe', 'value' => 'M'],
                        ['name' => 'Material / Fabric', 'value' => 'Cotton'],
                    ],
                ],
            ]],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en']);

        $attrs = $result['products'][0]['variants'][0]['attrs'];
        $this->assertArrayHasKey('shoe-size', $attrs);
        $this->assertArrayHasKey('größe', $attrs);
        $this->assertArrayHasKey('material-fabric', $attrs);
    }

    // ─── Product URL ───

    public function testProductUrlIncludedWhenPresent(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data);

        $this->assertEquals('https://shop.example.com/products/snowboard', $result['products'][0]['productUrl']);
    }

    public function testProductUrlDuplicatedAcrossLocales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard', $p['productUrl_en']);
        $this->assertEquals('https://shop.example.com/products/snowboard', $p['productUrl_lt']);
    }

    // ─── Error handling ───

    public function testMalformedEdgeIsReportedAsError(): void
    {
        $data = [
            'data' => [
                'products' => [
                    'edges' => [
                        'not an array',
                    ],
                ],
            ],
        ];

        $result = $this->adapter->transform($data);

        $this->assertEmpty($result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('invalid_structure', $result['errors'][0]['type']);
    }

    public function testMissingDataFieldThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->adapter->transform([]);
    }

    public function testMissingProductsFieldThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->adapter->transform(['data' => []]);
    }

    public function testMissingEdgesReturnsEmpty(): void
    {
        $result = $this->adapter->transform(['data' => ['products' => []]]);

        $this->assertEmpty($result['products']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Multiple products ───

    public function testTransformMultipleProducts(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc1', 'BrandX', 'Sports'),
            $this->makeProduct('gid://shopify/Product/2', 'Ski', 'Desc2', 'BrandY', 'Winter'),
        ]);

        $result = $this->adapter->transform($data);

        $this->assertCount(2, $result['products']);
        $this->assertEquals('1', $result['products'][0]['id']);
        $this->assertEquals('2', $result['products'][1]['id']);
    }

    // ─── Pricing ───

    public function testPriceExtractedFromPriceRange(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['priceRangeV2'] = [
            'minVariantPrice' => ['amount' => '29.99', 'currencyCode' => 'USD'],
            'maxVariantPrice' => ['amount' => '49.99', 'currencyCode' => 'USD'],
        ];

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $this->assertEquals('29.99', $result['products'][0]['price']);
    }

    public function testBasePriceFromCompareAtPrice(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/100',
                        'sku' => 'SNO-001',
                        'price' => '29.99',
                        'compareAtPrice' => '39.99',
                        'availableForSale' => true,
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];
        $product['node']['priceRangeV2'] = [
            'minVariantPrice' => ['amount' => '29.99', 'currencyCode' => 'USD'],
            'maxVariantPrice' => ['amount' => '29.99', 'currencyCode' => 'USD'],
        ];

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $this->assertEquals('39.99', $result['products'][0]['basePrice']);
    }

    // ─── In stock ───

    public function testInStockWhenVariantAvailable(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['variants'] = [
            'edges' => [
                ['node' => ['id' => 'gid://shopify/ProductVariant/100', 'sku' => 'S', 'availableForSale' => false, 'selectedOptions' => []]],
                ['node' => ['id' => 'gid://shopify/ProductVariant/101', 'sku' => 'M', 'availableForSale' => true, 'selectedOptions' => []]],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $this->assertTrue($result['products'][0]['inStock']);
    }

    public function testOutOfStockWhenNoVariantAvailable(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['variants'] = [
            'edges' => [
                ['node' => ['id' => 'gid://shopify/ProductVariant/100', 'sku' => 'S', 'availableForSale' => false, 'selectedOptions' => []]],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $this->assertFalse($result['products'][0]['inStock']);
    }

    // ─── Helpers ───

    private function makeProduct(string $gid, string $title, string $description, string $vendor, string $productType): array
    {
        return [
            'node' => [
                'id' => $gid,
                'title' => $title,
                'descriptionHtml' => $description,
                'vendor' => $vendor,
                'productType' => $productType,
                'tags' => [],
                'status' => 'ACTIVE',
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-01-01T00:00:00Z',
                'publishedAt' => '2024-01-01T00:00:00Z',
                'variants' => ['edges' => [
                    ['node' => ['id' => 'gid://shopify/ProductVariant/1', 'sku' => 'SKU-001', 'price' => '19.99', 'availableForSale' => true, 'selectedOptions' => []]],
                ]],
                'images' => ['edges' => []],
                'priceRangeV2' => [
                    'minVariantPrice' => ['amount' => '19.99', 'currencyCode' => 'USD'],
                    'maxVariantPrice' => ['amount' => '19.99', 'currencyCode' => 'USD'],
                ],
            ],
        ];
    }

    private function makeShopifyResponse(array $edges, ?string $primaryLocale = null): array
    {
        $data = [
            'data' => [
                'products' => [
                    'edges' => $edges,
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ],
        ];

        if ($primaryLocale !== null) {
            $data['locales'] = ['primary' => $primaryLocale, 'published' => [$primaryLocale]];
        }

        return $data;
    }
}
