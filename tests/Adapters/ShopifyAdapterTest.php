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
        $this->assertEquals('Sports', $product['productType']);
        $this->assertSame('', $product['categoryDefault']);
        $this->assertSame([], $product['categories']);
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
        // productType is now its own field. Primary locale gets native value;
        // non-primary without translation gets nothing (no fallback to primary).
        $this->assertEquals('Sports', $product['productType_en']);
        $this->assertArrayNotHasKey('productType_lt', $product);
        // categoryDefault is taxonomy-only — absent when product has no taxonomy.
        $this->assertArrayNotHasKey('categoryDefault_en', $product);
        $this->assertArrayNotHasKey('categoryDefault_lt', $product);

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

        // productType is its own field, locale-suffixed and translated.
        $this->assertEquals('Sports', $p['productType_en']);
        $this->assertEquals('Sportas', $p['productType_lt']);

        // categoryDefault is taxonomy-only; this product has none.
        $this->assertArrayNotHasKey('categoryDefault_en', $p);
        $this->assertArrayNotHasKey('categoryDefault_lt', $p);

        // categories now reflects taxonomy + tags only (productType excluded).
        $this->assertContains('winter', $p['categories_en']);
        $this->assertContains('outdoor', $p['categories_en']);
        $this->assertNotContains('Sports', $p['categories_en']);
        $this->assertContains('winter', $p['categories_lt']);
        $this->assertContains('outdoor', $p['categories_lt']);
        $this->assertNotContains('Sportas', $p['categories_lt']);
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

        // Primary (en): native productType
        $this->assertEquals('Snowboard', $p['name_en']);
        $this->assertEquals('Great board', $p['description_en']);
        $this->assertEquals('Sports', $p['productType_en']);

        // lt: translated productType
        $this->assertEquals('Snieglentė', $p['name_lt']);
        $this->assertEquals('Puiki lenta', $p['description_lt']);
        $this->assertEquals('Sportas', $p['productType_lt']);

        // fr: no productType translation — field omitted (no fallback to primary).
        $this->assertEquals('Snowboard', $p['name_fr']);
        $this->assertEquals('Great board', $p['description_fr']);
        $this->assertArrayNotHasKey('productType_fr', $p);

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
     * Pathological option names made entirely of separators must not
     * produce an empty key. Must fall back to "unknown" to match
     * bradsearch-shopify-app1 AttributesController::slugify().
     */
    public function testSlugifyAllNonAlphanumericOptionNameDoesNotProduceEmptyKey(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['options'] = [
            ['id' => 'gid://shopify/ProductOption/1', 'name' => '---', 'values' => ['x']],
        ];
        $product['node']['variants'] = [
            'edges' => [[
                'node' => [
                    'id' => 'gid://shopify/ProductVariant/100',
                    'sku' => 'SNO-001',
                    'selectedOptions' => [
                        ['name' => '---', 'value' => 'x'],
                    ],
                ],
            ]],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en']);

        $attrs = $result['products'][0]['variants'][0]['attrs'];
        $this->assertArrayNotHasKey('', $attrs);
        $this->assertArrayHasKey('unknown', $attrs);
        $this->assertEquals(['en' => 'x'], $attrs['unknown']);
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

    // ─── Variant URL / price / image ───

    public function testVariantsCarryLocalizedProductUrlWithVariantQueryParam(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['translations'] = [
            'lt' => [['key' => 'handle', 'value' => 'snieglente']],
        ];
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '99.95',
                        'selectedOptions' => [['name' => 'Size', 'value' => 'L']],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard?variant=12345', $variant['productUrl_en']);
        $this->assertEquals('https://shop.example.com/lt/products/snieglente?variant=12345', $variant['productUrl_lt']);
    }

    public function testVariantsCarryProductUrlWithoutLocales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '99.95',
                        'selectedOptions' => [['name' => 'Color', 'value' => 'White']],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard?variant=12345', $variant['productUrl']);
    }

    public function testVariantUrlPreservesPreviewKeyAlongsideVariantParam(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = null;
        $product['node']['onlineStorePreviewUrl'] = 'https://shop.myshopify.com/products/snowboard?preview_key=abc123';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '99.95',
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('https://shop.myshopify.com/products/snowboard?preview_key=abc123&variant=12345', $variant['productUrl_en']);
        $this->assertEquals('https://shop.myshopify.com/lt/products/snowboard?preview_key=abc123&variant=12345', $variant['productUrl_lt']);
    }

    public function testVariantsCarryPriceAndBasePriceFromCompareAtPrice(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '79.95',
                        'compareAtPrice' => '99.95',
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('79.95', $variant['price']);
        $this->assertEquals('79.95', $variant['priceTaxExcluded']);
        $this->assertEquals('99.95', $variant['basePrice']);
        $this->assertEquals('99.95', $variant['basePriceTaxExcluded']);
    }

    public function testVariantUrlOverridesDefaultVariantParamFromBaseUrl(): void
    {
        // If onlineStoreUrl already pins ?variant=111 (some themes do this for the
        // canonical variant), the per-variant deep-link must still win — otherwise
        // every indexed variant would point to the same default variant.
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard?variant=111';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '79.95',
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard?variant=12345', $variant['productUrl']);
    }

    public function testVariantBasePriceFallsBackToPriceWhenCompareAtIsZero(): void
    {
        // Shopify reports "0.00" when no compare-at is configured; bccomp guard
        // ensures basePrice still reflects the actual price rather than zero.
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '79.95',
                        'compareAtPrice' => '0.00',
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('79.95', $variant['price']);
        $this->assertEquals('79.95', $variant['basePrice']);
    }

    public function testVariantBasePriceFallsBackToPriceWhenCompareAtMissing(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '79.95',
                        'compareAtPrice' => null,
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertEquals('79.95', $variant['price']);
        $this->assertEquals('79.95', $variant['basePrice']);
    }

    public function testVariantsDoNotCarryImageUrl(): void
    {
        // Per merchant feedback: search rows must show the curated featuredImage,
        // not whatever variant happens to match. Keeping imageUrl off the variant
        // means variant enrichment can't swap the parent's hero image at search time.
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['images'] = [
            'edges' => [
                ['node' => ['url' => 'https://cdn.shopify.com/product-image.jpg', 'width' => 800, 'height' => 800]],
            ],
        ];
        $product['node']['variants'] = [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/ProductVariant/12345',
                        'sku' => 'SNO-001',
                        'price' => '79.95',
                        'selectedOptions' => [],
                    ],
                ],
            ],
        ];

        $data = $this->makeShopifyResponse([$product]);

        $result = $this->adapter->transform($data, []);

        $variant = $result['products'][0]['variants'][0];
        $this->assertArrayNotHasKey('imageUrl', $variant);
        $this->assertEquals('https://cdn.shopify.com/product-image.jpg', $result['products'][0]['imageUrl']['small']);
    }

    // ─── Taxonomy category ───

    public function testTaxonomyFullNameWinsOverProductType(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $product['node']['category'] = [
            'id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8',
            'name' => 'Shirts',
            'fullName' => 'Apparel & Accessories > Clothing > Shirts',
        ];

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $p = $result['products'][0];
        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $p['categoryDefault']);
        $this->assertContains('Apparel & Accessories > Clothing > Shirts', $p['categories']);
        $this->assertNotContains('Shoes', $p['categories']);
    }

    public function testNullCategoryEmitsEmptyCategoryDefaultAndProductTypeField(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $product['node']['category'] = null;

        $data = $this->makeShopifyResponse([$product]);
        $p = $this->adapter->transform($data)['products'][0];

        $this->assertSame('', $p['categoryDefault']);
        $this->assertEquals('Shoes', $p['productType']);
    }

    public function testUncategorizedGidTreatedAsMissing(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $product['node']['category'] = [
            'id' => 'gid://shopify/TaxonomyCategory/na',
            'name' => 'Uncategorized',
            'fullName' => 'Uncategorized',
        ];

        $data = $this->makeShopifyResponse([$product]);
        $p = $this->adapter->transform($data)['products'][0];

        $this->assertSame('', $p['categoryDefault']);
        $this->assertEquals('Shoes', $p['productType']);
        $this->assertNotContains('Uncategorized', $p['categories']);
        $this->assertNotContains('Shoes', $p['categories']);
    }

    public function testTaxonomyOnlyWithNoProductTypeOmitsProductTypeField(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', '');
        $product['node']['category'] = [
            'id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8',
            'name' => 'Shirts',
            'fullName' => 'Apparel & Accessories > Clothing > Shirts',
        ];

        $data = $this->makeShopifyResponse([$product], 'en');
        $p = $this->adapter->transform($data, ['en', 'lt'])['products'][0];

        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $p['categoryDefault_en']);
        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $p['categoryDefault_lt']);
        $this->assertArrayNotHasKey('productType_en', $p);
        $this->assertArrayNotHasKey('productType_lt', $p);
    }

    public function testCategoryAndProductTypeBothMissingEmitsEmptyString(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', '');
        $product['node']['category'] = null;

        $data = $this->makeShopifyResponse([$product]);
        $result = $this->adapter->transform($data);

        $p = $result['products'][0];
        // Prior contract: empty string is emitted (not null/absent).
        $this->assertArrayHasKey('categoryDefault', $p);
        $this->assertSame('', $p['categoryDefault']);
        $this->assertSame([], $p['categories']);
    }

    public function testTaxonomyAndProductTypeAreIndependentFieldsInLocales(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $product['node']['category'] = [
            'id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8',
            'name' => 'Shirts',
            'fullName' => 'Apparel & Accessories > Clothing > Shirts',
        ];
        $product['node']['translations'] = [
            'lt-LT' => [
                ['key' => 'product_type', 'value' => 'Batai'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en-US');
        $p = $this->adapter->transform($data, ['en-US', 'lt-LT'])['products'][0];

        // Taxonomy is English-only; same value across every locale.
        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $p['categoryDefault_en-US']);
        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $p['categoryDefault_lt-LT']);
        // productType is translated independently.
        $this->assertEquals('Shoes', $p['productType_en-US']);
        $this->assertEquals('Batai', $p['productType_lt-LT']);
    }

    public function testProductTypeIsTranslatedPerLocaleWhenNoTaxonomy(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $product['node']['category'] = null;
        $product['node']['translations'] = [
            'lt-LT' => [
                ['key' => 'product_type', 'value' => 'Batai'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en-US');
        $p = $this->adapter->transform($data, ['en-US', 'lt-LT'])['products'][0];

        $this->assertArrayNotHasKey('categoryDefault_en-US', $p);
        $this->assertArrayNotHasKey('categoryDefault_lt-LT', $p);
        $this->assertEquals('Shoes', $p['productType_en-US']);
        $this->assertEquals('Batai', $p['productType_lt-LT']);
    }

    public function testCategoriesArrayContainsTaxonomyAndTagsOnly(): void
    {
        // Taxonomy: categories = [fullName, ...tags]
        $taxProduct = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
        $taxProduct['node']['category'] = [
            'id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8',
            'name' => 'Shirts',
            'fullName' => 'Apparel & Accessories > Clothing > Shirts',
        ];
        $taxProduct['node']['tags'] = ['summer', 'cotton'];

        // No taxonomy: categories = tags only; productType emitted separately.
        $noTaxProduct = $this->makeProduct('gid://shopify/Product/2', 'Ski', 'Desc', 'BrandX', 'Winter');
        $noTaxProduct['node']['category'] = null;
        $noTaxProduct['node']['tags'] = ['cold'];

        // Both empty: categories = tags only.
        $emptyProduct = $this->makeProduct('gid://shopify/Product/3', 'Mystery', 'Desc', 'BrandX', '');
        $emptyProduct['node']['category'] = null;
        $emptyProduct['node']['tags'] = ['gift'];

        $result = $this->adapter->transform($this->makeShopifyResponse([$taxProduct, $noTaxProduct, $emptyProduct]));
        [$a, $b, $c] = $result['products'];

        $this->assertEquals('Apparel & Accessories > Clothing > Shirts', $a['categoryDefault']);
        $this->assertEqualsCanonicalizing(
            ['Apparel & Accessories > Clothing > Shirts', 'summer', 'cotton'],
            $a['categories']
        );
        $this->assertEquals('Shoes', $a['productType']);

        $this->assertSame('', $b['categoryDefault']);
        $this->assertEquals(['cold'], $b['categories']);
        $this->assertEquals('Winter', $b['productType']);

        $this->assertSame('', $c['categoryDefault']);
        $this->assertEquals(['gift'], $c['categories']);
        $this->assertArrayNotHasKey('productType', $c);
    }

    public function testMalformedCategoryFieldEmitsProductTypeAsOwnField(): void
    {
        $cases = [
            'string' => 'not-an-object',
            'empty-array' => [],
            'id-only' => ['id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8'],
            'non-string-names' => ['id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8', 'name' => 42, 'fullName' => null],
            'empty-names' => ['id' => 'gid://shopify/TaxonomyCategory/aa-1-13-8', 'name' => '', 'fullName' => ''],
        ];

        foreach ($cases as $label => $categoryValue) {
            $product = $this->makeProduct('gid://shopify/Product/1', 'Tee', 'Desc', 'BrandX', 'Shoes');
            $product['node']['category'] = $categoryValue;

            $result = $this->adapter->transform($this->makeShopifyResponse([$product]));
            $p = $result['products'][0];

            $this->assertEmpty($result['errors'], "case '{$label}' produced errors");
            $this->assertSame('', $p['categoryDefault'], "case '{$label}': categoryDefault");
            $this->assertEquals('Shoes', $p['productType'], "case '{$label}': productType");
        }
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

    public function testProductUrlPrependsLocalePrefixForNonPrimary(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard', $p['productUrl_en']);
        $this->assertEquals('https://shop.example.com/lt/products/snowboard', $p['productUrl_lt']);
    }

    public function testProductUrlUsesTranslatedHandleWhenPresent(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/snowboard';
        $product['node']['translations'] = [
            'lt' => [
                ['key' => 'handle', 'value' => 'snieglente'],
                ['key' => 'title', 'value' => 'Snieglentė'],
            ],
        ];

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('https://shop.example.com/products/snowboard', $p['productUrl_en']);
        $this->assertEquals('https://shop.example.com/lt/products/snieglente', $p['productUrl_lt']);
    }

    public function testProductUrlFallsBackToUrlParsedHandleWhenHandleFieldMissing(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        // no $product['node']['handle'] — simulates payloads from older shopify-app versions
        $product['node']['onlineStoreUrl'] = 'https://shop.example.com/products/my-board';

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('https://shop.example.com/products/my-board', $p['productUrl_en']);
        $this->assertEquals('https://shop.example.com/lt/products/my-board', $p['productUrl_lt']);
    }

    public function testProductUrlPreservesPreviewQueryStringForDevStores(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');
        $product['node']['handle'] = 'snowboard';
        $product['node']['onlineStoreUrl'] = null;
        $product['node']['onlineStorePreviewUrl'] = 'https://shop.myshopify.com/products/snowboard?preview_key=abc123';

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en', 'lt']);

        $p = $result['products'][0];
        $this->assertEquals('https://shop.myshopify.com/products/snowboard?preview_key=abc123', $p['productUrl_en']);
        $this->assertEquals('https://shop.myshopify.com/lt/products/snowboard?preview_key=abc123', $p['productUrl_lt']);
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

    // ─── Collections ───

    public function testTransformWithCollectionsEmitsLocalizedFacet(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');

        $data = $this->makeShopifyResponse([$product], 'en');
        $data['collections'] = [
            [
                'id' => 'gid://shopify/Collection/10',
                'title' => 'Hyrogen',
                'translations' => ['lt' => [['key' => 'title', 'value' => 'Hidrogenas']]],
                'product_gids' => ['gid://shopify/Product/1'],
            ],
            [
                'id' => 'gid://shopify/Collection/20',
                'title' => 'Automated Collection',
                'translations' => [],
                'product_gids' => ['gid://shopify/Product/1'],
            ],
            [
                'id' => 'gid://shopify/Collection/30',
                'title' => 'Other Collection',
                'translations' => [],
                'product_gids' => ['gid://shopify/Product/999'],
            ],
        ];

        $result = $this->adapter->transform($data, ['en', 'lt']);
        $product = $result['products'][0];

        $this->assertEquals(['Hyrogen', 'Automated Collection'], $product['collections_en']);
        $this->assertEquals(['Hidrogenas', 'Automated Collection'], $product['collections_lt']);
    }

    public function testTransformWithoutCollectionsMetadataOmitsField(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, ['en']);
        $product = $result['products'][0];

        $this->assertArrayNotHasKey('collections_en', $product);
    }

    public function testTransformOmitsCollectionsForProductNotListedInAnyCollection(): void
    {
        $productA = $this->makeProduct('gid://shopify/Product/1', 'A', 'Desc', 'BrandX', 'Sports');
        $productB = $this->makeProduct('gid://shopify/Product/2', 'B', 'Desc', 'BrandX', 'Sports');

        $data = $this->makeShopifyResponse([$productA, $productB], 'en');
        $data['collections'] = [
            [
                'id' => 'gid://shopify/Collection/10',
                'title' => 'Hyrogen',
                'translations' => [],
                'product_gids' => ['gid://shopify/Product/1'],
            ],
        ];

        $result = $this->adapter->transform($data, ['en']);

        $this->assertEquals(['Hyrogen'], $result['products'][0]['collections_en']);
        $this->assertArrayNotHasKey('collections_en', $result['products'][1]);
    }

    public function testTransformPlainModeEmitsCollectionsField(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');

        $data = $this->makeShopifyResponse([$product], 'en');
        $data['collections'] = [
            [
                'id' => 'gid://shopify/Collection/10',
                'title' => 'Hyrogen',
                'translations' => ['lt' => [['key' => 'title', 'value' => 'Hidrogenas']]],
                'product_gids' => ['gid://shopify/Product/1'],
            ],
            [
                'id' => 'gid://shopify/Collection/20',
                'title' => 'Automated Collection',
                'translations' => [],
                'product_gids' => ['gid://shopify/Product/1'],
            ],
        ];

        $result = $this->adapter->transform($data, []);
        $product = $result['products'][0];

        $this->assertEquals(['Hyrogen', 'Automated Collection'], $product['collections']);
        $this->assertArrayNotHasKey('collections_en', $product);
        $this->assertArrayNotHasKey('collections_lt', $product);
    }

    public function testTransformPlainModeOmitsCollectionsWhenAbsent(): void
    {
        $product = $this->makeProduct('gid://shopify/Product/1', 'Snowboard', 'Desc', 'BrandX', 'Sports');

        $data = $this->makeShopifyResponse([$product], 'en');

        $result = $this->adapter->transform($data, []);

        $this->assertArrayNotHasKey('collections', $result['products'][0]);
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
