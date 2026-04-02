<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\MagentoAdapterV2;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use PHPUnit\Framework\TestCase;

class MagentoAdapterV2Test extends TestCase
{
    private MagentoAdapterV2 $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MagentoAdapterV2('lt-LT');
    }

    // --- Validation Tests ---

    public function testTransformThrowsOnMissingDataField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid Magento data: missing data field');
        $this->adapter->transform([]);
    }

    public function testTransformThrowsOnMissingProductsField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid Magento data: missing products field');
        $this->adapter->transform(['data' => ['unknown' => []]]);
    }

    public function testTransformReturnsEmptyWhenNoItems(): void
    {
        $result = $this->adapter->transform(['data' => ['bradProducts' => []]]);

        $this->assertNull($result['request']);
        $this->assertEmpty($result['products']);
        $this->assertEmpty($result['errors']);
    }

    // --- Transform Structure Tests ---

    public function testTransformReturnsBulkOperationsRequest(): void
    {
        $data = $this->wrapMagentoData([$this->buildMinimalProduct()]);

        $result = $this->adapter->transform($data);

        $this->assertInstanceOf(BulkOperationsRequest::class, $result['request']);
        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);
    }

    public function testTransformCollectsErrors(): void
    {
        $data = $this->wrapMagentoData([
            ['sku' => 'SKU1'], // Missing 'id'
            $this->buildMinimalProduct(),
        ]);

        $result = $this->adapter->transform($data);

        $this->assertCount(1, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('transformation_error', $result['errors'][0]['type']);
    }

    public function testTransformSupportsStandardProductsKey(): void
    {
        $data = [
            'data' => [
                'products' => [
                    'items' => [$this->buildMinimalProduct()],
                ],
            ],
        ];

        $result = $this->adapter->transform($data);

        $this->assertCount(1, $result['products']);
    }

    // --- Locale Suffix Tests ---

    public function testLocaleUsedAsIs(): void
    {
        // Locale normalization happens in brad-app (MagentoApi::getLocales()), not in the adapter
        $adapter = new MagentoAdapterV2('lt');
        $product = $adapter->transformProduct($this->buildMinimalProduct(['name' => 'Grąžtas']));
        $serialized = $product->jsonSerialize();

        $this->assertSame('Grąžtas', $serialized['name_lt']);
    }

    public function testLocaleNormalizedFromHyphenFormat(): void
    {
        $adapter = new MagentoAdapterV2('lt-LT');
        $product = $adapter->transformProduct($this->buildMinimalProduct(['name' => 'Grąžtas']));
        $serialized = $product->jsonSerialize();

        $this->assertSame('Grąžtas', $serialized['name_lt-LT']);
    }

    public function testAllLocaleAwareFieldsHaveSuffix(): void
    {
        $product = $this->buildMinimalProduct([
            'name' => 'Test Product',
            'full_url' => 'https://example.com/product.html',
            'description' => ['html' => '<p>Description</p>'],
            'short_description' => ['html' => '<p>Short</p>'],
            'categories' => [
                ['id' => '2', 'name' => 'Tools', 'path' => '1/2', 'level' => 1],
            ],
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Bosch', 'is_searchable' => true, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // All locale-aware fields should have _lt-LT suffix
        $this->assertArrayHasKey('name_lt-LT', $serialized);
        $this->assertArrayHasKey('description_lt-LT', $serialized);
        $this->assertArrayHasKey('descriptionShort_lt-LT', $serialized);
        $this->assertArrayHasKey('productUrl_lt-LT', $serialized);
        $this->assertArrayHasKey('categories_lt-LT', $serialized);
        $this->assertArrayHasKey('categoryDefault_lt-LT', $serialized);
        $this->assertArrayHasKey('brand_lt-LT', $serialized);

        // No bare field names
        $this->assertArrayNotHasKey('name', $serialized);
        $this->assertArrayNotHasKey('description', $serialized);
        $this->assertArrayNotHasKey('descriptionShort', $serialized);
        $this->assertArrayNotHasKey('productUrl', $serialized);
        $this->assertArrayNotHasKey('categories', $serialized);
        $this->assertArrayNotHasKey('categoryDefault', $serialized);
        $this->assertArrayNotHasKey('brand', $serialized);
    }

    public function testLocaleAgnosticFieldsHaveNoSuffix(): void
    {
        $product = $this->buildMinimalProduct([
            'sort_popularity_sales' => 42,
            'attributes' => [
                ['code' => 'mpn', 'value' => 'E-03707', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'barcode', 'value' => '4039784620186', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'mpn_without_symbols', 'value' => 'E03707', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'beginning_of_product_nam', 'value' => 'Grąžtas', 'is_searchable' => false, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // These are locale-agnostic — no suffix
        $this->assertArrayHasKey('mpn', $serialized);
        $this->assertArrayHasKey('barcode', $serialized);
        $this->assertArrayHasKey('mpn_without_symbols', $serialized);
        $this->assertArrayHasKey('nameShort', $serialized);
        $this->assertArrayHasKey('sort_popularity_sales', $serialized);
    }

    // --- Attribute Processing: Flat Field Tests ---

    public function testAttributeCreatedAsFlatFieldWithLocale(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'diameter', 'value' => '10 mm', 'is_searchable' => true, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('10 mm', $serialized['feature_diameter_lt-LT']);
        $this->assertArrayNotHasKey('feature_diameter', $serialized);
    }

    public function testNonSearchableAttributeAlsoCreatedAsFlatField(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'color', 'value' => 'Red', 'is_searchable' => false, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // All attributes get flat fields regardless of is_searchable (matching v1 behavior)
        $this->assertSame('Red', $serialized['feature_color_lt-LT']);
    }

    // --- Attribute Processing: is_filterable Tests ---

    public function testFilterableAttributeIncludedInBothFlatAndNested(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'color', 'value' => 'Red', 'is_searchable' => false, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Flat field for search
        $this->assertSame('Red', $serialized['feature_color_lt-LT']);
        // Nested for filtering/aggregations
        $this->assertArrayHasKey('features', $serialized);
        $this->assertCount(1, $serialized['features']);
        $this->assertSame('color', $serialized['features'][0]['name']);
        $this->assertSame('Red', $serialized['features'][0]['value']);
    }

    public function testSearchableAndFilterableAttributeInBothFlatAndNested(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'diameter', 'value' => '10 mm', 'is_searchable' => true, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Flat field for text search
        $this->assertSame('10 mm', $serialized['feature_diameter_lt-LT']);
        // Also in nested features for aggregations/filtering
        $this->assertCount(1, $serialized['features']);
        $this->assertSame('diameter', $serialized['features'][0]['name']);
        $this->assertSame('10 mm', $serialized['features'][0]['value']);
    }

    public function testNonFilterableAttributeGetsFlatFieldButNotNested(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'internal_code', 'value' => 'XYZ', 'is_searchable' => false, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // All attributes get flat fields for search (matching v1 behavior)
        $this->assertSame('XYZ', $serialized['feature_internal_code_lt-LT']);
        // But non-filterable attributes are NOT in nested features
        $this->assertArrayNotHasKey('features', $serialized);
    }

    // --- Nested Features Format Tests ---

    public function testNestedFeaturesHaveUnifiedNameValueFormat(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'material', 'value' => 'Steel', 'is_searchable' => false, 'is_filterable' => true],
                ['code' => 'finish', 'value' => 'Matte', 'is_searchable' => false, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertCount(2, $serialized['features']);

        foreach ($serialized['features'] as $feature) {
            $this->assertArrayHasKey('name', $feature);
            $this->assertArrayHasKey('value', $feature);
            // Must NOT have platform-specific fields
            $this->assertArrayNotHasKey('numeric_value', $feature);
            $this->assertArrayNotHasKey('unit', $feature);
            $this->assertArrayNotHasKey('is_searchable', $feature);
            $this->assertArrayNotHasKey('is_filterable', $feature);
        }
    }

    // --- Popularity/Sorting Metrics Tests ---

    public function testSortPopularitySalesInverted(): void
    {
        $product = $this->buildMinimalProduct([
            'sort_popularity_sales' => 42,
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Inverted: 1000 - 42 = 958 (most popular products get highest values)
        $this->assertSame(958, $serialized['sort_popularity_sales']);
    }

    public function testSortPopularitySalesInvertedEdgeCases(): void
    {
        // Most popular (rank 1) → inverted 999
        $product = $this->adapter->transformProduct($this->buildMinimalProduct(['sort_popularity_sales' => 1]));
        $this->assertSame(999, $product->jsonSerialize()['sort_popularity_sales']);

        // Least popular (rank 999) → inverted 1
        $product = $this->adapter->transformProduct($this->buildMinimalProduct(['sort_popularity_sales' => 999]));
        $this->assertSame(1, $product->jsonSerialize()['sort_popularity_sales']);

        // Beyond range → clamped to 0
        $product = $this->adapter->transformProduct($this->buildMinimalProduct(['sort_popularity_sales' => 1500]));
        $this->assertSame(0, $product->jsonSerialize()['sort_popularity_sales']);
    }

    public function testSortPopularitySalesMissingIsOmitted(): void
    {
        $product = $this->buildMinimalProduct();

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertArrayNotHasKey('sort_popularity_sales', $serialized);
    }

    // --- Product Identifier Extraction Tests ---

    public function testMpnExtractedAsTopLevelField(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'mpn', 'value' => 'E-03707', 'is_searchable' => true, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('E-03707', $serialized['mpn']);
        $this->assertArrayNotHasKey('feature_mpn', $serialized);
        $this->assertArrayNotHasKey('feature_mpn_lt-LT', $serialized);
    }

    public function testBarcodeExtractedAsTopLevelField(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'barcode', 'value' => '4039784620186', 'is_searchable' => true, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('4039784620186', $serialized['barcode']);
        $this->assertArrayNotHasKey('feature_barcode', $serialized);
        $this->assertArrayNotHasKey('feature_barcode_lt-LT', $serialized);
    }

    public function testMpnWithoutSymbolsExtractedAsTopLevelField(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'mpn_without_symbols', 'value' => 'E03707', 'is_searchable' => true, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('E03707', $serialized['mpn_without_symbols']);
        $this->assertArrayNotHasKey('feature_mpn_without_symbols', $serialized);
        $this->assertArrayNotHasKey('feature_mpn_without_symbols_lt-LT', $serialized);
    }

    public function testBeginningOfProductNameExtractedAsNameShort(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'beginning_of_product_nam', 'value' => 'Gręžimo karūna', 'is_searchable' => false, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Gręžimo karūna', $serialized['nameShort']);
        $this->assertArrayNotHasKey('feature_beginning_of_product_nam', $serialized);
        $this->assertArrayNotHasKey('feature_beginning_of_product_nam_lt-LT', $serialized);
    }

    // --- Brand Extraction Tests ---

    public function testBrandExtractedFromManufacturerWithLocale(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Bosch', 'is_searchable' => true, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Bosch', $serialized['brand_lt-LT']);
        $this->assertArrayNotHasKey('brand', $serialized);
        // manufacturer should NOT appear as feature_ flat field or nested feature
        $this->assertArrayNotHasKey('feature_manufacturer', $serialized);
        $this->assertArrayNotHasKey('feature_manufacturer_lt-LT', $serialized);
        $this->assertArrayNotHasKey('features', $serialized);
    }

    public function testBrandMixedWithOtherAttributes(): void
    {
        $product = $this->buildMinimalProduct([
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Stanley', 'is_searchable' => true, 'is_filterable' => true],
                ['code' => 'color', 'value' => 'Yellow', 'is_searchable' => false, 'is_filterable' => true],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Stanley', $serialized['brand_lt-LT']);
        $this->assertCount(1, $serialized['features']);
        $this->assertSame('color', $serialized['features'][0]['name']);
    }

    // --- Price Flattening Tests ---

    public function testPriceFlattenedFromNestedStructure(): void
    {
        $product = $this->buildMinimalProduct([
            'price_range' => [
                'minimum_price' => [
                    'final_price' => ['value' => 29.99],
                    'final_price_excl_tax' => ['value' => 24.79],
                ],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(29.99, $serialized['price']);
        $this->assertSame(29.99, $serialized['basePrice']);
        $this->assertSame(24.79, $serialized['priceTaxExcluded']);
        $this->assertSame(24.79, $serialized['basePriceTaxExcluded']);
    }

    public function testPriceFallsBackToZeroWhenMissing(): void
    {
        $product = $this->buildMinimalProduct();

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(0.0, $serialized['price']);
    }

    public function testPriceTaxExcludedFallsBackToPrice(): void
    {
        $product = $this->buildMinimalProduct([
            'price_range' => [
                'minimum_price' => [
                    'final_price' => ['value' => 15.50],
                    // no final_price_excl_tax
                ],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(15.50, $serialized['price']);
        $this->assertSame(15.50, $serialized['priceTaxExcluded']);
    }

    // --- Image URL Tests ---

    public function testImageExtractedFromImageOptimized(): void
    {
        $product = $this->buildMinimalProduct([
            'image_optimized' => 'https://example.com/product.jpg',
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('https://example.com/product.jpg', $serialized['imageUrl']['small']);
        $this->assertSame('https://example.com/product.jpg', $serialized['imageUrl']['medium']);
    }

    public function testImageFallsBackToNestedImageUrl(): void
    {
        $product = $this->buildMinimalProduct([
            'image_optimized' => '',
            'image' => ['url' => 'https://example.com/fallback.jpg'],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('https://example.com/fallback.jpg', $serialized['imageUrl']['small']);
    }

    // --- Stock Status Tests ---

    public function testInStockFromBooleanField(): void
    {
        $product = $this->buildMinimalProduct(['is_in_stock' => true]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertTrue($serialized['inStock']);
    }

    public function testInStockFromStockStatusEnum(): void
    {
        $product = $this->buildMinimalProduct(['stock_status' => 'IN_STOCK']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertTrue($serialized['inStock']);
    }

    public function testOutOfStockFromStockStatusEnum(): void
    {
        $product = $this->buildMinimalProduct(['stock_status' => 'OUT_OF_STOCK']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertFalse($serialized['inStock']);
    }

    // --- Description Tests ---

    public function testDescriptionStripsHtml(): void
    {
        $product = $this->buildMinimalProduct([
            'description' => ['html' => '<p>Product <b>description</b></p>'],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Product description', $serialized['description_lt-LT']);
    }

    public function testShortDescriptionStripsHtml(): void
    {
        $product = $this->buildMinimalProduct([
            'short_description' => ['html' => '<p>Short <em>desc</em></p>'],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Short desc', $serialized['descriptionShort_lt-LT']);
    }

    // --- Category Tests ---

    public function testCategoriesBuildHierarchicalPaths(): void
    {
        $product = $this->buildMinimalProduct([
            'categories' => [
                ['id' => '2', 'name' => 'Root', 'path' => '1/2', 'level' => 1],
                ['id' => '10', 'name' => 'Tools', 'path' => '1/2/10', 'level' => 2],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertContains('Root', $serialized['categories_lt-LT']);
        $this->assertContains('Root > Tools', $serialized['categories_lt-LT']);
    }

    public function testDefaultCategoryIsDeepest(): void
    {
        $product = $this->buildMinimalProduct([
            'categories' => [
                ['id' => '2', 'name' => 'Root', 'path' => '1/2', 'level' => 1],
                ['id' => '10', 'name' => 'Tools', 'path' => '1/2/10', 'level' => 2],
                ['id' => '20', 'name' => 'Power Tools', 'path' => '1/2/10/20', 'level' => 3],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('Power Tools', $serialized['categoryDefault_lt-LT']);
    }

    // --- Product URL Tests ---

    public function testProductUrlExtractedWithLocale(): void
    {
        $product = $this->buildMinimalProduct([
            'full_url' => 'https://example.com/product-123.html',
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame('https://example.com/product-123.html', $serialized['productUrl_lt-LT']);
        $this->assertArrayNotHasKey('productUrl', $serialized);
    }

    // --- Full Integration Test ---

    public function testFullProductTransformation(): void
    {
        $product = $this->buildMinimalProduct([
            'name' => 'Grąžtas metalui 10mm',
            'sort_popularity_sales' => 15,
            'full_url' => 'https://shop.example.com/graztas.html',
            'description' => ['html' => '<p>High quality drill bit</p>'],
            'short_description' => ['html' => '<p>Drill bit</p>'],
            'image_optimized' => 'https://shop.example.com/drill.jpg',
            'is_in_stock' => true,
            'price_range' => [
                'minimum_price' => [
                    'final_price' => ['value' => 5.99],
                    'final_price_excl_tax' => ['value' => 4.95],
                ],
            ],
            'categories' => [
                ['id' => '2', 'name' => 'Įrankiai', 'path' => '1/2', 'level' => 1],
            ],
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Bosch', 'is_searchable' => true, 'is_filterable' => true],
                ['code' => 'mpn', 'value' => 'E-03707', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'barcode', 'value' => '0088381561945', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'mpn_without_symbols', 'value' => 'E03707', 'is_searchable' => true, 'is_filterable' => false],
                ['code' => 'beginning_of_product_nam', 'value' => 'Grąžtas metalui', 'is_searchable' => false, 'is_filterable' => false],
                ['code' => 'diameter', 'value' => '10 mm', 'is_searchable' => true, 'is_filterable' => true],
                ['code' => 'color', 'value' => 'Silver', 'is_searchable' => false, 'is_filterable' => true],
                ['code' => 'internal_code', 'value' => 'XYZ', 'is_searchable' => false, 'is_filterable' => false],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Core fields (no locale)
        $this->assertSame('123', $serialized['id']);
        $this->assertSame('SKU-001', $serialized['sku']);
        $this->assertSame(5.99, $serialized['price']);
        $this->assertSame(4.95, $serialized['priceTaxExcluded']);
        $this->assertTrue($serialized['inStock']);

        // Locale-aware text fields
        $this->assertSame('Grąžtas metalui 10mm', $serialized['name_lt-LT']);
        $this->assertSame('High quality drill bit', $serialized['description_lt-LT']);
        $this->assertSame('Drill bit', $serialized['descriptionShort_lt-LT']);
        $this->assertSame('https://shop.example.com/graztas.html', $serialized['productUrl_lt-LT']);

        // Popularity metric (inverted: 1000 - 15 = 985)
        $this->assertSame(985, $serialized['sort_popularity_sales']);

        // Brand from manufacturer (locale-aware)
        $this->assertSame('Bosch', $serialized['brand_lt-LT']);

        // Product identifiers as top-level fields (no locale)
        $this->assertSame('E-03707', $serialized['mpn']);
        $this->assertSame('0088381561945', $serialized['barcode']);
        $this->assertSame('E03707', $serialized['mpn_without_symbols']);

        // Name prefix for fuzzy matching (no locale)
        $this->assertSame('Grąžtas metalui', $serialized['nameShort']);

        // Flat search fields with locale (all non-special attributes, matching v1 behavior)
        $this->assertSame('10 mm', $serialized['feature_diameter_lt-LT']);
        $this->assertSame('Silver', $serialized['feature_color_lt-LT']);
        $this->assertSame('XYZ', $serialized['feature_internal_code_lt-LT']);
        $this->assertArrayNotHasKey('feature_diameter', $serialized);
        // Special attributes are NOT duplicated as feature_ fields
        $this->assertArrayNotHasKey('feature_manufacturer_lt-LT', $serialized);
        $this->assertArrayNotHasKey('feature_mpn_lt-LT', $serialized);
        $this->assertArrayNotHasKey('feature_barcode_lt-LT', $serialized);

        // Nested features (all filterable attributes, including searchable+filterable)
        $this->assertCount(2, $serialized['features']);
        $featureNames = array_column($serialized['features'], 'name');
        $this->assertContains('diameter', $featureNames);
        $this->assertContains('color', $featureNames);
        // Non-filterable attributes excluded
        $this->assertNotContains('internal_code', $featureNames);

        // No bare field names
        $this->assertArrayNotHasKey('name', $serialized);
        $this->assertArrayNotHasKey('brand', $serialized);
        $this->assertArrayNotHasKey('description', $serialized);
    }

    // --- Helpers ---

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildMinimalProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => 123,
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'image_optimized' => 'https://example.com/image.jpg',
        ], $overrides);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function wrapMagentoData(array $items): array
    {
        return [
            'data' => [
                'bradProducts' => [
                    'items' => $items,
                ],
            ],
        ];
    }
}
