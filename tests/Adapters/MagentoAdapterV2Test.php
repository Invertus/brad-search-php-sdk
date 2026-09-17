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

    // --- hasImage & delivery_speed from sort_popularity Tests ---

    public function testHasImageExtractedFromSortPopularity(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042I003']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertTrue($serialized['hasImage']);
    }

    public function testHasImageFalseExtracted(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042N003']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertFalse($serialized['hasImage']);
    }

    public function testDeliverySpeedExtractedAndInverted(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042I003']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Inverted: 999 - 3 = 996 (faster delivery = higher value)
        $this->assertSame(996, $serialized['delivery_speed']);
    }

    public function testDeliverySpeedZeroDelay(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042I000']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Inverted: 999 - 0 = 999 (instant delivery = max boost)
        $this->assertSame(999, $serialized['delivery_speed']);
    }

    public function testDeliverySpeedMaxDelay(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042I999']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        // Inverted: 999 - 999 = 0 (slowest delivery = no boost)
        $this->assertSame(0, $serialized['delivery_speed']);
    }

    public function testDeliverySpeedNulOmitted(): void
    {
        $product = $this->buildMinimalProduct(['sort_popularity' => 'I042INUL']);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertTrue($serialized['hasImage']);
        $this->assertArrayNotHasKey('delivery_speed', $serialized);
    }

    public function testSortPopularityFieldsMissingWhenNoString(): void
    {
        $product = $this->buildMinimalProduct();

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertArrayNotHasKey('hasImage', $serialized);
        $this->assertArrayNotHasKey('delivery_speed', $serialized);
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
        // manufacturer also appears as feature_ flat field and nested feature for aggregations
        $this->assertArrayNotHasKey('feature_manufacturer', $serialized);
        $this->assertSame('Bosch', $serialized['feature_manufacturer_lt-LT']);
        $this->assertCount(1, $serialized['features']);
        $this->assertSame('manufacturer', $serialized['features'][0]['name']);
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
        $this->assertCount(2, $serialized['features']);
        $featureNames = array_column($serialized['features'], 'name');
        $this->assertContains('manufacturer', $featureNames);
        $this->assertContains('color', $featureNames);
    }

    // --- Price Flattening Tests ---

    public function testPriceFlattenedFromNestedStructure(): void
    {
        $product = $this->buildMinimalProduct([
            'calculated_price' => [
                'minimum_price' => [
                    'regular_price' => ['value' => 29.99],
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
        $this->assertEqualsWithDelta(24.79, $serialized['basePriceTaxExcluded'], 0.001);
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
            'calculated_price' => [
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

    public function testBasePriceUsesRegularPriceWhenDiscounted(): void
    {
        $product = $this->buildMinimalProduct([
            'calculated_price' => [
                'minimum_price' => [
                    'regular_price' => ['value' => 50.00],
                    'final_price' => ['value' => 40.00],
                    'final_price_excl_tax' => ['value' => 33.06],
                ],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(40.00, $serialized['price']);
        $this->assertSame(50.00, $serialized['basePrice']);
        $this->assertSame(33.06, $serialized['priceTaxExcluded']);
        // 50.00 * (33.06 / 40.00) = 41.325
        $this->assertEqualsWithDelta(41.325, $serialized['basePriceTaxExcluded'], 0.001);
    }

    public function testBasePriceFallsBackToPriceWhenRegularPriceMissing(): void
    {
        $product = $this->buildMinimalProduct([
            'calculated_price' => [
                'minimum_price' => [
                    'final_price' => ['value' => 12.34],
                    'final_price_excl_tax' => ['value' => 10.20],
                ],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(12.34, $serialized['price']);
        $this->assertSame(12.34, $serialized['basePrice']);
        $this->assertSame(10.20, $serialized['priceTaxExcluded']);
        $this->assertEqualsWithDelta(10.20, $serialized['basePriceTaxExcluded'], 0.001);
    }

    public function testBasePriceTaxExcludedFallsBackToBasePriceWhenPriceIsZero(): void
    {
        $product = $this->buildMinimalProduct([
            'calculated_price' => [
                'minimum_price' => [
                    'regular_price' => ['value' => 50.00],
                    'final_price' => ['value' => 0.0],
                    'final_price_excl_tax' => ['value' => 0.0],
                ],
            ],
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertSame(50.00, $serialized['basePrice']);
        $this->assertEqualsWithDelta(50.00, $serialized['basePriceTaxExcluded'], 0.001);
    }

    /** @dataProvider taxExcludedDiscountProvider */
    public function testBasePriceTaxExcludedReflectsTrueDiscountRate(
        float $price,
        float $priceTaxExcluded,
        float $basePrice,
        float $expectedBasePriceTaxExcluded
    ): void {
        $product = $this->buildMinimalProduct([
            'calculated_price' => [
                'minimum_price' => [
                    'regular_price' => ['value' => $basePrice],
                    'final_price' => ['value' => $price],
                    'final_price_excl_tax' => ['value' => $priceTaxExcluded],
                ],
            ],
        ]);

        $serialized = $this->adapter->transformProduct($product)->jsonSerialize();

        $this->assertEqualsWithDelta($expectedBasePriceTaxExcluded, $serialized['basePriceTaxExcluded'], 0.01);

        // tax-excluded discount must equal the gross discount
        $grossDiscount = ($basePrice - $price) / $basePrice;
        $netDiscount = ($serialized['basePriceTaxExcluded'] - $serialized['priceTaxExcluded'])
            / $serialized['basePriceTaxExcluded'];
        $this->assertEqualsWithDelta($grossDiscount, $netDiscount, 0.001);
    }

    /** @return array<string, array{0: float, 1: float, 2: float, 3: float}> */
    public static function taxExcludedDiscountProvider(): array
    {
        return [
            // real Verkter products, full price
            'real Stanley STA10080' => [6.97, 5.76, 6.97, 5.76],
            'real Metabo STA18 LTX' => [291.44, 240.86, 291.44, 240.86],
            'real Yato YT-0682' => [18.62, 15.39, 18.62, 15.39],
            // synthetic discounts (LT 21% VAT)
            'synthetic 100.00 -> 80.00' => [80.00, 66.12, 100.00, 82.65],
            'synthetic 49.99 -> 39.99' => [39.99, 33.05, 49.99, 41.31],
            'synthetic 1200.00 -> 999.00' => [999.00, 825.62, 1200.00, 991.74],
        ];
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

    public function testPlaceholderImageSetsHasImageFalse(): void
    {
        $product = $this->buildMinimalProduct([
            'image_optimized' => 'https://www.irankiai.lt/media/catalog/product/placeholder/default/verkter_logo_blank_JPG_4.jpg?auto=webp&format=pjpg&width=840&height=375&fit=cover',
            'sort_popularity' => 'I042I003', // hashed flag says has image
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertFalse($serialized['hasImage']);
    }

    public function testRealImageKeepsHasImageTrue(): void
    {
        $product = $this->buildMinimalProduct([
            'image_optimized' => 'https://www.irankiai.lt/media/catalog/product/1/0/108594_p1.jpg?auto=webp&format=pjpg&width=840&height=375&fit=cover',
            'sort_popularity' => 'I042I003',
        ]);

        $result = $this->adapter->transformProduct($product);
        $serialized = $result->jsonSerialize();

        $this->assertTrue($serialized['hasImage']);
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
            'calculated_price' => [
                'minimum_price' => [
                    'regular_price' => ['value' => 5.99],
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
        // manufacturer also appears as feature_ flat field for aggregations
        $this->assertSame('Bosch', $serialized['feature_manufacturer_lt-LT']);
        // mpn/barcode are special top-level fields, not duplicated as feature_
        $this->assertArrayNotHasKey('feature_mpn_lt-LT', $serialized);
        $this->assertArrayNotHasKey('feature_barcode_lt-LT', $serialized);

        // Nested features (all filterable attributes, including manufacturer)
        $this->assertCount(3, $serialized['features']);
        $featureNames = array_column($serialized['features'], 'name');
        $this->assertContains('manufacturer', $featureNames);
        $this->assertContains('diameter', $featureNames);
        $this->assertContains('color', $featureNames);
        // Non-filterable attributes excluded
        $this->assertNotContains('internal_code', $featureNames);

        // No bare field names
        $this->assertArrayNotHasKey('name', $serialized);
        $this->assertArrayNotHasKey('brand', $serialized);
        $this->assertArrayNotHasKey('description', $serialized);
    }

    public function testCategoriesFlatContainsUniqueLevels(): void
    {
        $product = $this->adapter->transformProduct($this->buildMinimalProduct([
            'categories' => [
                ['id' => '2', 'name' => 'Tools', 'path' => '1/2', 'level' => 1],
                ['id' => '5', 'name' => 'Drills', 'path' => '1/2/5', 'level' => 2],
            ],
        ]));
        $serialized = $product->jsonSerialize();

        $this->assertSame(['Tools', 'Tools > Drills'], $serialized['categories_lt-LT']);
        $this->assertSame(['Tools', 'Drills'], $serialized['categoriesFlat_lt-LT']);
    }

    public function testNoCategoriesFlatWithoutCategories(): void
    {
        $product = $this->adapter->transformProduct($this->buildMinimalProduct());
        $serialized = $product->jsonSerialize();

        $this->assertArrayNotHasKey('categoriesFlat_lt-LT', $serialized);
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

    // --- Store View Map (multiple locales) Tests ---

    public function testStoreViewMapProducesOneProductPerSkuWithEveryLocaleSuffix(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            'data' => ['bradProducts' => ['items' => [$this->buildLocalizedProduct('lv')]]],
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([$this->buildLocalizedProduct('lv')]),
                'lv_ru' => $this->wrapMagentoData([$this->buildLocalizedProduct('ru')]),
            ],
        ]);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);
        $this->assertInstanceOf(BulkOperationsRequest::class, $result['request']);

        $serialized = $result['products'][0]->jsonSerialize();

        $this->assertSame('SKU-001', $serialized['sku']);
        $this->assertSame('Name lv', $serialized['name_lv-LV']);
        $this->assertSame('Name ru', $serialized['name_ru-RU']);
        $this->assertSame('Description lv', $serialized['description_lv-LV']);
        $this->assertSame('Description ru', $serialized['description_ru-RU']);
        $this->assertSame('Short lv', $serialized['descriptionShort_lv-LV']);
        $this->assertSame('Short ru', $serialized['descriptionShort_ru-RU']);
        $this->assertSame('https://example.com/lv.html', $serialized['productUrl_lv-LV']);
        $this->assertSame('https://example.com/ru.html', $serialized['productUrl_ru-RU']);
        $this->assertSame(['Tools lv'], $serialized['categories_lv-LV']);
        $this->assertSame(['Tools ru'], $serialized['categories_ru-RU']);
        $this->assertSame('Tools lv', $serialized['categoryDefault_lv-LV']);
        $this->assertSame('Tools ru', $serialized['categoryDefault_ru-RU']);
        $this->assertSame('Bosch', $serialized['brand_lv-LV']);
        $this->assertSame('Bosch', $serialized['brand_ru-RU']);
        $this->assertSame('Bosch', $serialized['feature_manufacturer_lv-LV']);
        $this->assertSame('Bosch', $serialized['feature_manufacturer_ru-RU']);
        $this->assertSame('Silver lv', $serialized['feature_color_lv-LV']);
        $this->assertSame('Silver ru', $serialized['feature_color_ru-RU']);
    }

    public function testStoreViewMapTakesLocaleAgnosticFieldsFromPrimaryViewOnly(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $primary = $this->buildLocalizedProduct('lv', ['sort_popularity_sales' => 10, 'sort_popularity' => 'I010I002']);
        $secondary = $this->buildLocalizedProduct('ru', [
            'sort_popularity_sales' => 500,
            'sort_popularity' => 'N500N100',
            'image_optimized' => 'https://example.com/other.jpg',
            'is_in_stock' => false,
            'calculated_price' => ['minimum_price' => ['final_price' => ['value' => 99.0]]],
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Bosch', 'is_filterable' => true],
                ['code' => 'color', 'value' => 'Silver ru', 'is_filterable' => true],
                ['code' => 'mpn', 'value' => 'RU-MPN'],
                ['code' => 'beginning_of_product_nam', 'value' => 'Ru short'],
            ],
        ]);

        $result = $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([$primary]),
                'lv_ru' => $this->wrapMagentoData([$secondary]),
            ],
        ]);

        $serialized = $result['products'][0]->jsonSerialize();

        $this->assertSame(990, $serialized['sort_popularity_sales']);
        $this->assertSame(997, $serialized['delivery_speed']);
        $this->assertTrue($serialized['hasImage']);
        $this->assertSame('https://example.com/image.jpg', $serialized['imageUrl']['small']);
        $this->assertTrue($serialized['inStock']);
        $this->assertSame(5.99, $serialized['price']);
        $this->assertSame('E-03707', $serialized['mpn']);
        $this->assertSame('Lv short', $serialized['nameShort']);
        $this->assertSame([['name' => 'manufacturer', 'value' => 'Bosch'], ['name' => 'color', 'value' => 'Silver lv']], $serialized['features']);
    }

    public function testStoreViewMapMergesBySkuAcrossDifferentItemOrder(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([
                    $this->buildLocalizedProduct('lv', ['id' => 1, 'sku' => 'A']),
                    $this->buildLocalizedProduct('lv', ['id' => 2, 'sku' => 'B']),
                ]),
                'lv_ru' => $this->wrapMagentoData([
                    $this->buildLocalizedProduct('ru', ['id' => 2, 'sku' => 'B', 'name' => 'B ru']),
                    $this->buildLocalizedProduct('ru', ['id' => 1, 'sku' => 'A', 'name' => 'A ru']),
                ]),
            ],
        ]);

        $this->assertCount(2, $result['products']);
        $bySku = [];
        foreach ($result['products'] as $product) {
            $bySku[$product->sku] = $product->jsonSerialize();
        }

        $this->assertSame('1', $bySku['A']['id']);
        $this->assertSame('A ru', $bySku['A']['name_ru-RU']);
        $this->assertSame('2', $bySku['B']['id']);
        $this->assertSame('B ru', $bySku['B']['name_ru-RU']);
    }

    public function testStoreViewMapIndexesSkuPresentOnlyInSecondaryView(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([]),
                'lv_ru' => $this->wrapMagentoData([$this->buildLocalizedProduct('ru', ['sku' => 'RU-ONLY'])]),
            ],
        ]);

        $this->assertCount(1, $result['products']);
        $serialized = $result['products'][0]->jsonSerialize();
        $this->assertSame('RU-ONLY', $serialized['sku']);
        $this->assertSame('Name ru', $serialized['name_ru-RU']);
        $this->assertArrayNotHasKey('name_lv-LV', $serialized);
    }

    public function testStoreViewMapLeavesLocaleAbsentWhenViewIsMissingFromPayload(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([$this->buildLocalizedProduct('lv')]),
            ],
        ]);

        $serialized = $result['products'][0]->jsonSerialize();
        $this->assertSame('Name lv', $serialized['name_lv-LV']);
        $this->assertArrayNotHasKey('name_ru-RU', $serialized);
    }

    public function testStoreViewMapWithPlainResponseUsesPrimaryLocale(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform($this->wrapMagentoData([$this->buildLocalizedProduct('lv')]));

        $serialized = $result['products'][0]->jsonSerialize();
        $this->assertSame('Name lv', $serialized['name_lv-LV']);
        $this->assertArrayNotHasKey('name_ru-RU', $serialized);
    }

    public function testSingleLocaleAdapterIgnoresStoreViewsKey(): void
    {
        $result = $this->adapter->transform([
            'data' => ['bradProducts' => ['items' => [$this->buildLocalizedProduct('lv')]]],
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_ru' => $this->wrapMagentoData([$this->buildLocalizedProduct('ru')]),
            ],
        ]);

        $this->assertCount(1, $result['products']);
        $serialized = $result['products'][0]->jsonSerialize();
        $this->assertSame('Name lv', $serialized['name_lt-LT']);
        $this->assertArrayNotHasKey('name_ru-RU', $serialized);
    }

    public function testStoreViewMapCollectsErrorsPerSku(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([
                    ['id' => 1, 'sku' => 'NO-IMAGE', 'name' => 'x'],
                    ['id' => 2, 'name' => 'no sku'],
                    $this->buildLocalizedProduct('lv', ['id' => 3, 'sku' => 'OK']),
                ]),
                'lv_ru' => $this->wrapMagentoData([
                    $this->buildLocalizedProduct('ru', ['id' => 3, 'sku' => 'OK']),
                ]),
            ],
        ]);

        $this->assertCount(1, $result['products']);
        $this->assertSame('OK', $result['products'][0]->sku);
        $this->assertCount(2, $result['errors']);
        $this->assertSame('2', $result['errors'][0]['product_id']);
        $this->assertSame('1', $result['errors'][1]['product_id']);
        $this->assertSame($result['errors'], $adapter->getErrors());
    }

    public function testStoreViewMapThrowsOnUnknownStoreView(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("unknown store view 'de_store'");

        $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => [
                'lv_store' => $this->wrapMagentoData([]),
                'de_store' => $this->wrapMagentoData([]),
            ],
        ]);
    }

    public function testStoreViewMapThrowsOnInvalidViewResponse(): void
    {
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('missing data field');

        $adapter->transform([
            MagentoAdapterV2::STORE_VIEWS_KEY => ['lv_store' => ['items' => []]],
        ]);
    }

    public function testEmptyStoreViewMapIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        new MagentoAdapterV2([]);
    }

    public function testStoreViewMapWithEmptyLocaleIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        new MagentoAdapterV2(['lv_store' => '']);
    }

    // --- Real store response shape: two store views of one product from a live store (domain and prices changed) ---

    public function testRealStoreShapeSingleViewProducesLatvianDocument(): void
    {
        $fixture = $this->loadStoreViewsFixture();

        $result = (new MagentoAdapterV2('lv-LV'))->transform($fixture['lv_store']);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);

        $doc = $result['products'][0]->jsonSerialize();

        $this->assertSame('20074', $doc['id']);
        $this->assertSame('20074', $doc['sku']);
        $this->assertSame(119.99, $doc['price']);
        $this->assertSame(149.99, $doc['basePrice']);
        $this->assertSame(99.17, $doc['priceTaxExcluded']);
        $this->assertTrue($doc['inStock']);
        $this->assertTrue($doc['hasImage']);
        $this->assertSame(999, $doc['delivery_speed']);
        $this->assertSame(1, $doc['sort_popularity_sales']);
        $this->assertSame('Ekscentra slīpmašīna Bosch GEX 125-1 AE', $doc['name_lv-LV']);
        $this->assertArrayNotHasKey('description_lv-LV', $doc);
        $this->assertStringStartsWith('Pagariniet Bosch instrumenta garantiju', $doc['descriptionShort_lv-LV']);
        $this->assertStringNotContainsString('<a', $doc['descriptionShort_lv-LV']);
        $this->assertSame(
            ['Slīpmašīnas un pulētāji', 'Slīpmašīnas un pulētāji > Ekscentra slīpmašīnas'],
            $doc['categories_lv-LV']
        );
        $this->assertSame('Ekscentra slīpmašīnas', $doc['categoryDefault_lv-LV']);
        $this->assertSame('Bosch', $doc['brand_lv-LV']);
        $this->assertSame('0601387500', $doc['mpn']);
        $this->assertSame('3165140438278', $doc['barcode']);
        $this->assertSame('Ekscentra slīpmašīna', $doc['nameShort']);
        $this->assertSame('125 mm', $doc['feature_attr_9d8856a44b8c2873999555aedf7bf8_lv-LV']);
        $this->assertSame('Slīpmašīnas un pulētāji', $doc['feature_b6f2c76b997fff72c8a41e1531e5ab_lv-LV']);
        $this->assertSame('1.600000', $doc['feature_weight_slider_lv-LV']);
        $this->assertArrayNotHasKey('feature_mpn_lv-LV', $doc);
        $this->assertSame(
            ['manufacturer', 'b6f2c76b997fff72c8a41e1531e5ab', 'f71a39ed758a2aba322bd3a9212e01', 'attr_9d8856a44b8c2873999555aedf7bf8'],
            array_column($doc['features'], 'name')
        );
    }

    public function testRealStoreShapeTwoViewsMergeIntoOneDocumentWithBothLocales(): void
    {
        $fixture = $this->loadStoreViewsFixture();
        $adapter = new MagentoAdapterV2(['lv_store' => 'lv-LV', 'lv_ru' => 'ru-RU']);

        $result = $adapter->transform([
            'data' => $fixture['lv_store']['data'],
            MagentoAdapterV2::STORE_VIEWS_KEY => $fixture,
        ]);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);

        $doc = $result['products'][0]->jsonSerialize();
        $single = (new MagentoAdapterV2('lv-LV'))->transform($fixture['lv_store'])['products'][0]->jsonSerialize();

        // Everything the single-view sync produced is still there, unchanged.
        foreach ($single as $key => $value) {
            $this->assertSame($value, $doc[$key], "field {$key} changed by the merge");
        }

        // The RU view added only its own suffixed fields.
        $added = array_diff_key($doc, $single);
        $this->assertNotEmpty($added);
        foreach (array_keys($added) as $key) {
            $this->assertStringEndsWith('_ru-RU', $key, "unexpected unsuffixed field {$key} from the secondary view");
        }

        $this->assertSame('Эксцентриковая шлифмашина Bosch GEX 125-1 AE', $doc['name_ru-RU']);
        // The RU view has an empty short_description, so no descriptionShort_ru-RU and the LV one stays.
        $this->assertArrayNotHasKey('descriptionShort_ru-RU', $doc);
        $this->assertArrayHasKey('descriptionShort_lv-LV', $doc);
        $this->assertSame('https://magento.example.com/jekscentrikovaja-shlifmashina-bosch-gex-125-1-ae.html', $doc['productUrl_ru-RU']);
        $this->assertSame(
            ['Шлифовальные и полировальные машины', 'Шлифовальные и полировальные машины > Эксцентриковые шлифмашины'],
            $doc['categories_ru-RU']
        );
        $this->assertSame('Эксцентриковые шлифмашины', $doc['categoryDefault_ru-RU']);
        $this->assertSame('Bosch', $doc['brand_ru-RU']);
        $this->assertSame('125 mm', $doc['feature_attr_9d8856a44b8c2873999555aedf7bf8_ru-RU']);
        $this->assertSame('Электрический', $doc['feature_engine_type_ru-RU']);
        $this->assertSame('фильтр', $doc['feature_set_includes_grinders_ru-RU']);
        // Price, image and popularity differ between the two views; the primary view's values win.
        $this->assertSame(119.99, $doc['price']);
        $this->assertSame(149.99, $doc['basePrice']);
        $this->assertStringNotContainsString('/ru/', $doc['imageUrl']['small']);
        $this->assertSame(1, $doc['sort_popularity_sales']);

        // Same count of feature_* fields per locale: every attribute got both suffixes.
        $lvFeatures = array_filter(array_keys($doc), fn(string $k) => str_starts_with($k, 'feature_') && str_ends_with($k, '_lv-LV'));
        $ruFeatures = array_filter(array_keys($doc), fn(string $k) => str_starts_with($k, 'feature_') && str_ends_with($k, '_ru-RU'));
        $this->assertCount(17, $lvFeatures);
        $this->assertCount(count($lvFeatures), $ruFeatures);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadStoreViewsFixture(): array
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/magento/store-views-two-locales.json');
        $this->assertNotFalse($json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildLocalizedProduct(string $lang, array $overrides = []): array
    {
        return array_merge([
            'id' => 123,
            'sku' => 'SKU-001',
            'name' => "Name {$lang}",
            'full_url' => "https://example.com/{$lang}.html",
            'description' => ['html' => "<p>Description {$lang}</p>"],
            'short_description' => ['html' => "<p>Short {$lang}</p>"],
            'image_optimized' => 'https://example.com/image.jpg',
            'is_in_stock' => true,
            'calculated_price' => ['minimum_price' => ['final_price' => ['value' => 5.99]]],
            'categories' => [
                ['id' => '2', 'name' => "Tools {$lang}", 'path' => '1/2', 'level' => 1],
            ],
            'attributes' => [
                ['code' => 'manufacturer', 'value' => 'Bosch', 'is_filterable' => true],
                ['code' => 'color', 'value' => "Silver {$lang}", 'is_filterable' => true],
                ['code' => 'mpn', 'value' => 'E-03707'],
                ['code' => 'beginning_of_product_nam', 'value' => ucfirst($lang) . ' short'],
            ],
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
