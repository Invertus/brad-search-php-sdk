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

    public function test_transform_without_locales_returns_plain_field_names(): void
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

    public function test_transform_without_locales_omits_empty_description(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', '', 'BrandX', 'Sports'),
        ]);

        $result = $this->adapter->transform($data);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('description', $product);
    }

    public function test_transform_without_locales_omits_empty_brand(): void
    {
        $data = $this->makeShopifyResponse([
            $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', '', 'Sports'),
        ]);

        $result = $this->adapter->transform($data);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('brand', $product);
    }

    // ─── Transform with locales ───

    public function test_transform_with_locales_produces_suffixed_fields(): void
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

    public function test_transform_with_translations_uses_translated_values(): void
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

    public function test_transform_with_product_type_translation(): void
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

    public function test_brand_is_not_translatable(): void
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

    public function test_transform_with_missing_translation_falls_back_to_primary(): void
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

    public function test_transform_with_empty_string_translation_falls_back(): void
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

    public function test_transform_with_null_translation_value_falls_back(): void
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

    public function test_three_locales_with_partial_translations(): void
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

    public function test_empty_locales_array_produces_plain_fields(): void
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

    public function test_variants_use_attrs_format_with_locales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
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

    public function test_variants_use_attributes_format_without_locales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
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

    // ─── Product URL ───

    public function test_product_url_included_when_present(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data);

        $this->assertEquals('https://shop.example.com/products/snowboard', $result['products'][0]['productUrl']);
    }

    public function test_product_url_duplicated_across_locales(): void
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

    public function test_malformed_edge_is_reported_as_error(): void
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

    public function test_missing_data_field_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->adapter->transform([]);
    }

    public function test_missing_products_field_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->adapter->transform(['data' => []]);
    }

    public function test_missing_edges_returns_empty(): void
    {
        $result = $this->adapter->transform(['data' => ['products' => []]]);

        $this->assertEmpty($result['products']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Multiple products ───

    public function test_transform_multiple_products(): void
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

    public function test_price_extracted_from_price_range(): void
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

    public function test_base_price_from_compare_at_price(): void
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

    public function test_in_stock_when_variant_available(): void
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

    public function test_out_of_stock_when_no_variant_available(): void
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
