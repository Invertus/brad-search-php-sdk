<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Adapters;

use BradSearch\SyncSdk\Adapters\MagentoAdapter;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class MagentoAdapterTest extends TestCase
{
    private MagentoAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MagentoAdapter();
    }

    private function getProductFromResult(array $result, int $index = 0): array
    {
        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey($index, $result['products']);
        return $result['products'][$index];
    }

    public function testTransformWithMissingDataField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid Magento data: missing data field');
        $this->adapter->transform([]);
    }

    public function testTransformWithMissingProductsField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid Magento data: missing products field');
        $this->adapter->transform(['data' => []]);
    }

    public function testTransformWithEmptyItems(): void
    {
        $result = $this->adapter->transform([
            'data' => [
                'products' => []
            ]
        ]);

        $this->assertSame([], $result['products']);
        $this->assertSame([], $result['errors']);
    }

    public function testTransformWithMissingItems(): void
    {
        $result = $this->adapter->transform([
            'data' => [
                'products' => [
                    'total_count' => 0
                ]
            ]
        ]);

        $this->assertSame([], $result['products']);
        $this->assertSame([], $result['errors']);
    }

    public function testTransformSimpleProduct(): void
    {
        $magentoData = $this->getSampleMagentoResponse();

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(1, $result['products']);
        $this->assertCount(0, $result['errors']);

        $product = $this->getProductFromResult($result);

        // Check required fields are present and id is cast to string
        $this->assertSame('1924184', $product['id']);
        $this->assertSame('1924184', $product['sku']);
        $this->assertSame('Gręžimo karūna Bahco Bi-Metal; 98x38 mm ', $product['name']);
    }

    public function testTransformPreservesAllFields(): void
    {
        $magentoData = $this->getSampleMagentoResponse();

        $result = $this->adapter->transform($magentoData);
        $product = $this->getProductFromResult($result);

        // Check that nested structures are preserved as-is
        $this->assertArrayHasKey('url_key', $product);
        $this->assertSame('grezimo-karuna-bahco-bi-metal-98x38-mm', $product['url_key']);

        $this->assertArrayHasKey('is_in_stock', $product);
        $this->assertTrue($product['is_in_stock']);

        $this->assertArrayHasKey('allows_backorders', $product);
        $this->assertTrue($product['allows_backorders']);

        // Check attributes array is preserved
        $this->assertArrayHasKey('attributes', $product);
        $this->assertIsArray($product['attributes']);
        $this->assertCount(12, $product['attributes']);
        $this->assertSame('manufacturer', $product['attributes'][0]['code']);

        // Check price_range structure is preserved
        $this->assertArrayHasKey('price_range', $product);
        $this->assertArrayHasKey('minimum_price', $product['price_range']);
        $this->assertSame(18.5, $product['price_range']['minimum_price']['final_price']['value']);

        // Check categories array is preserved
        $this->assertArrayHasKey('categories', $product);
        $this->assertIsArray($product['categories']);
        $this->assertCount(3, $product['categories']);

        // Check image structures are preserved
        $this->assertArrayHasKey('image', $product);
        $this->assertArrayHasKey('url', $product['image']);

        // Check media_gallery is preserved
        $this->assertArrayHasKey('media_gallery', $product);
        $this->assertIsArray($product['media_gallery']);
    }

    public function testTransformWithMissingRequiredId(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        [
                            'sku' => 'TEST-SKU',
                            'name' => 'Test Product'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('transformation_error', $result['errors'][0]['type']);
        $this->assertStringContainsString("'id'", $result['errors'][0]['message']);
    }

    public function testTransformWithMissingRequiredSku(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        [
                            'id' => 123,
                            'name' => 'Test Product'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("'sku'", $result['errors'][0]['message']);
    }

    public function testTransformWithMissingRequiredName(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        [
                            'id' => 123,
                            'sku' => 'TEST-SKU'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(0, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("'name'", $result['errors'][0]['message']);
    }

    public function testTransformWithNonArrayItem(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        'not an array',
                        [
                            'id' => 123,
                            'sku' => 'TEST-SKU',
                            'name' => 'Valid Product'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(1, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('invalid_structure', $result['errors'][0]['type']);
        $this->assertSame(0, $result['errors'][0]['product_index']);
    }

    public function testTransformCastsIdToString(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        [
                            'id' => 12345,
                            'sku' => 'TEST-SKU',
                            'name' => 'Test Product'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);
        $product = $this->getProductFromResult($result);

        $this->assertSame('12345', $product['id']);
        $this->assertIsString($product['id']);
    }

    public function testExtractPaginationInfo(): void
    {
        $magentoData = $this->getSampleMagentoResponse();

        $pagination = $this->adapter->extractPaginationInfo($magentoData);

        $this->assertNotNull($pagination);
        $this->assertSame(78574, $pagination['total_count']);
        $this->assertSame(1, $pagination['current_page']);
        $this->assertSame(1, $pagination['page_size']);
        $this->assertSame(78574, $pagination['total_pages']);
    }

    public function testExtractPaginationInfoReturnsNullWhenMissing(): void
    {
        $pagination = $this->adapter->extractPaginationInfo([]);
        $this->assertNull($pagination);

        $pagination = $this->adapter->extractPaginationInfo(['data' => []]);
        $this->assertNull($pagination);
    }

    public function testExtractPaginationInfoWithPartialData(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'total_count' => 100
                ]
            ]
        ];

        $pagination = $this->adapter->extractPaginationInfo($magentoData);

        $this->assertNotNull($pagination);
        $this->assertSame(100, $pagination['total_count']);
        $this->assertArrayNotHasKey('current_page', $pagination);
    }

    public function testTransformMultipleProducts(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        ['id' => 1, 'sku' => 'SKU-1', 'name' => 'Product 1'],
                        ['id' => 2, 'sku' => 'SKU-2', 'name' => 'Product 2'],
                        ['id' => 3, 'sku' => 'SKU-3', 'name' => 'Product 3'],
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        $this->assertCount(3, $result['products']);
        $this->assertCount(0, $result['errors']);
        $this->assertSame('1', $result['products'][0]['id']);
        $this->assertSame('2', $result['products'][1]['id']);
        $this->assertSame('3', $result['products'][2]['id']);
    }

    public function testTransformContinuesAfterError(): void
    {
        $magentoData = [
            'data' => [
                'products' => [
                    'items' => [
                        ['id' => 1, 'sku' => 'SKU-1', 'name' => 'Product 1'],
                        ['id' => 2, 'name' => 'Missing SKU'], // Invalid
                        ['id' => 3, 'sku' => 'SKU-3', 'name' => 'Product 3'],
                    ]
                ]
            ]
        ];

        $result = $this->adapter->transform($magentoData);

        // Should have 2 valid products and 1 error
        $this->assertCount(2, $result['products']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('1', $result['products'][0]['id']);
        $this->assertSame('3', $result['products'][1]['id']);
        $this->assertSame(1, $result['errors'][0]['product_index']);
        $this->assertSame('2', $result['errors'][0]['product_id']);
    }

    private function getSampleMagentoResponse(): array
    {
        return [
            'data' => [
                'products' => [
                    'total_count' => 78574,
                    'page_info' => [
                        'current_page' => 1,
                        'page_size' => 1,
                        'total_pages' => 78574
                    ],
                    'items' => [
                        [
                            'id' => 1924184,
                            'sku' => '1924184',
                            'name' => 'Gręžimo karūna Bahco Bi-Metal; 98x38 mm ',
                            'url_key' => 'grezimo-karuna-bahco-bi-metal-98x38-mm',
                            'is_in_stock' => true,
                            'allows_backorders' => true,
                            'short_description' => ['html' => ''],
                            'description' => ['html' => ''],
                            'attributes' => [
                                ['code' => 'manufacturer', 'label' => 'Gamintojas', 'value' => 'Bahco', 'formatted' => 'Bahco', 'position' => 2, 'is_searchable' => false, 'is_filterable' => true, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'mpn', 'label' => 'Prekės gamintojo kodas', 'value' => '3830-98-Cbahco', 'formatted' => '3830-98-Cbahco', 'position' => 1, 'is_searchable' => true, 'is_filterable' => false, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'barcode', 'label' => 'Brūkšninis kodas', 'value' => '7311518228514', 'formatted' => '7311518228514', 'position' => 1, 'is_searchable' => true, 'is_filterable' => false, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'mpn_without_symbols', 'label' => 'Manufacturer code without symbols', 'value' => '383098Cbahco', 'formatted' => '383098Cbahco', 'position' => 1, 'is_searchable' => true, 'is_filterable' => false, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'amazon_asin', 'label' => '_amazon_asin', 'value' => 'B0001P0P7C', 'formatted' => 'B0001P0P7C', 'position' => 1, 'is_searchable' => false, 'is_filterable' => false, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'middle_of_product_name', 'label' => 'Middle of product name', 'value' => 'Bahco Bi-Metal; 98x38 mm', 'formatted' => 'Bahco Bi-Metal; 98x38 mm', 'position' => 1, 'is_searchable' => false, 'is_filterable' => false, 'unit' => 'mm', 'numeric_value' => 38, 'has_unit' => true],
                                ['code' => 'beginning_of_product_nam', 'label' => 'Beginning of product name', 'value' => 'Gręžimo karūna', 'formatted' => 'Gręžimo karūna', 'position' => 1, 'is_searchable' => false, 'is_filterable' => false, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'b6f2c76b997fff72c8a41e1531e5ab', 'label' => 'Kategorija', 'value' => 'Grąžtai, kaltai, frezos, antgaliai', 'formatted' => 'Grąžtai, kaltai, frezos, antgaliai', 'position' => 1, 'is_searchable' => false, 'is_filterable' => true, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'f71a39ed758a2aba322bd3a9212e01', 'label' => 'Subkategorija', 'value' => 'Gręžimo karūnos', 'formatted' => 'Gręžimo karūnos', 'position' => 1, 'is_searchable' => true, 'is_filterable' => true, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'diameter', 'label' => 'Skersmuo', 'value' => '98 mm', 'formatted' => '98 mm', 'position' => 1, 'is_searchable' => false, 'is_filterable' => true, 'unit' => 'mm', 'numeric_value' => 98, 'has_unit' => true],
                                ['code' => 'a3767c82d98d8ddcb8dc0e5b266aeb', 'label' => 'Grąžto / kalto kotelio tipas', 'value' => 'Komplekte nėra grąžtų ar kaltų', 'formatted' => 'Komplekte nėra grąžtų ar kaltų', 'position' => 1, 'is_searchable' => false, 'is_filterable' => true, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                                ['code' => 'suitable_for_drill_materials', 'label' => 'Tinka gręžti', 'value' => 'Medieną, Metalą, Plastiką', 'formatted' => 'Medieną, Metalą, Plastiką', 'position' => 1, 'is_searchable' => false, 'is_filterable' => true, 'unit' => null, 'numeric_value' => null, 'has_unit' => false],
                            ],
                            'image' => [
                                'url' => 'https://lt-stage.verkter.com/media/catalog/product/placeholder/default/verkter_logo_blank_JPG_4.jpg',
                                'label' => 'Gręžimo karūna Bahco Bi-Metal; 98x38 mm '
                            ],
                            'small_image' => [
                                'url' => 'https://lt-stage.verkter.com/media/catalog/product/placeholder/default/verkter_logo_blank_JPG_5.jpg',
                                'label' => 'Gręžimo karūna Bahco Bi-Metal; 98x38 mm '
                            ],
                            'thumbnail' => [
                                'url' => 'https://lt-stage.verkter.com/media/catalog/product/placeholder/default/verkter_logo_blank_JPG_6.jpg',
                                'label' => 'Gręžimo karūna Bahco Bi-Metal; 98x38 mm '
                            ],
                            'media_gallery' => [
                                [
                                    'url' => 'https://lt-stage.verkter.com/media/catalog/product/placeholder/default/verkter_logo_blank_JPG_4.jpg',
                                    'label' => null,
                                    'position' => 0,
                                    'disabled' => false
                                ]
                            ],
                            'price_range' => [
                                'minimum_price' => [
                                    'regular_price' => ['value' => 18.56, 'currency' => 'EUR'],
                                    'final_price' => ['value' => 18.5, 'currency' => 'EUR'],
                                    'discount' => ['amount_off' => 0.06, 'percent_off' => 0.32]
                                ],
                                'maximum_price' => [
                                    'regular_price' => ['value' => 18.56, 'currency' => 'EUR'],
                                    'final_price' => ['value' => 18.5, 'currency' => 'EUR']
                                ]
                            ],
                            'categories' => [
                                ['id' => 34, 'name' => 'Grąžtai, kaltai, frezos, antgaliai', 'url_path' => 'graztai-kaltai-frezos-antgaliai', 'level' => 2, 'path' => '1/2/34'],
                                ['id' => 663, 'name' => 'Gręžimo karūnos', 'url_path' => 'graztai-kaltai-frezos-antgaliai/grezimo-karunos', 'level' => 3, 'path' => '1/2/34/663'],
                                ['id' => 1128, 'name' => 'TEST ', 'url_path' => 'test', 'level' => 2, 'path' => '1/2/1128']
                            ],
                            'stock_status' => 'IN_STOCK'
                        ]
                    ]
                ]
            ]
        ];
    }
}
