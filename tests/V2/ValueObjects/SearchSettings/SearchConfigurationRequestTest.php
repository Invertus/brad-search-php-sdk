<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightField;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryField;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryFieldType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoreMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\VariantEnrichmentConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SearchConfigurationRequestTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $request = new SearchConfigurationRequest();

        $this->assertEquals([], $request->supportedLocales);
        $this->assertNull($request->queryConfig);
        $this->assertNull($request->responseConfig);
    }

    public function testConstructorWithAllParameters(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $queryConfig = new QueryConfig([$field]);
        $responseConfig = new ResponseConfig(['name', 'price']);

        $request = new SearchConfigurationRequest(
            ['lt-LT', 'en-US'],
            $queryConfig,
            $responseConfig
        );

        $this->assertEquals(['lt-LT', 'en-US'], $request->supportedLocales);
        $this->assertSame($queryConfig, $request->queryConfig);
        $this->assertSame($responseConfig, $request->responseConfig);
    }

    public function testExtendsValueObject(): void
    {
        $request = new SearchConfigurationRequest();
        $this->assertInstanceOf(ValueObject::class, $request);
    }

    public function testImplementsJsonSerializable(): void
    {
        $request = new SearchConfigurationRequest();
        $this->assertInstanceOf(JsonSerializable::class, $request);
    }

    public function testJsonSerializeWithEmptyConfig(): void
    {
        $request = new SearchConfigurationRequest();

        $this->assertEquals([], $request->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'product_name',
            searchTypes: [SearchType::MATCH]
        );
        $queryConfig = new QueryConfig([$field], ['product_name', 'brand']);

        $highlightField = new HighlightField('product_name', null, ['<em>'], ['</em>']);
        $highlightConfig = new HighlightConfig(true, [$highlightField]);

        $variantEnrichment = new VariantEnrichmentConfig(['price', 'imageUrl']);

        $responseConfig = new ResponseConfig(
            ['name', 'price'],
            ['price', 'created_at'],
            $highlightConfig,
            $variantEnrichment
        );

        $request = new SearchConfigurationRequest(
            ['lt-LT', 'en-US'],
            $queryConfig,
            $responseConfig
        );

        $expected = [
            'supported_locales' => ['lt-LT', 'en-US'],
            'query_config' => [
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'product_name',
                        'search_types' => ['match'],
                    ],
                ],
                'cross_fields_matching' => ['product_name', 'brand'],
            ],
            'response_config' => [
                'source_fields' => ['name', 'price'],
                'sortable_fields' => ['price', 'created_at'],
                'highlight_config' => [
                    'enabled' => true,
                    'fields' => [
                        [
                            'field_name' => 'product_name',
                            'pre_tags' => ['<em>'],
                            'post_tags' => ['</em>'],
                        ],
                    ],
                ],
                'variant_enrichment' => [
                    'replace_fields' => ['price', 'imageUrl'],
                ],
            ],
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testFromJsonWithValidJson(): void
    {
        $json = json_encode([
            'supported_locales' => ['lt-LT'],
            'query_config' => [
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'product_name',
                    ],
                ],
            ],
        ]);

        $request = SearchConfigurationRequest::fromJson($json);

        $this->assertEquals(['lt-LT'], $request->supportedLocales);
        $this->assertNotNull($request->queryConfig);
        $this->assertCount(1, $request->queryConfig->fields);
    }

    public function testFromJsonWithInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON:');

        SearchConfigurationRequest::fromJson('not valid json');
    }

    public function testFromJsonWithNonArrayJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON must decode to an array.');

        SearchConfigurationRequest::fromJson('"string value"');
    }

    public function testFromArrayWithEmptyData(): void
    {
        $request = SearchConfigurationRequest::fromArray([]);

        $this->assertEquals([], $request->supportedLocales);
        $this->assertNull($request->queryConfig);
        $this->assertNull($request->responseConfig);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'supported_locales' => ['lt-LT', 'en-US'],
            'query_config' => [
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'product_name',
                        'locale_suffix' => 'lt-LT',
                        'search_types' => ['match', 'autocomplete'],
                    ],
                    [
                        'type' => 'nested',
                        'name' => 'variants',
                        'nested_path' => 'variants',
                        'score_mode' => 'max',
                        'nested_fields' => [
                            [
                                'type' => 'text',
                                'name' => 'sku',
                                'search_types' => ['exact'],
                            ],
                        ],
                    ],
                ],
                'cross_fields_matching' => ['product_name', 'brand'],
            ],
            'response_config' => [
                'source_fields' => ['name', 'price', 'imageUrl'],
                'sortable_fields' => ['price', 'created_at'],
                'highlight_config' => [
                    'enabled' => true,
                    'fields' => [
                        [
                            'field_name' => 'name',
                            'pre_tags' => ['<em>'],
                            'post_tags' => ['</em>'],
                        ],
                    ],
                ],
                'variant_enrichment' => [
                    'replace_fields' => ['price', 'imageUrl'],
                ],
            ],
        ];

        $request = SearchConfigurationRequest::fromArray($data);

        // Verify supported locales
        $this->assertEquals(['lt-LT', 'en-US'], $request->supportedLocales);

        // Verify query config
        $this->assertNotNull($request->queryConfig);
        $this->assertCount(2, $request->queryConfig->fields);
        $this->assertEquals('product_name', $request->queryConfig->fields[0]->name);
        $this->assertEquals(QueryFieldType::TEXT, $request->queryConfig->fields[0]->type);
        $this->assertEquals('lt-LT', $request->queryConfig->fields[0]->localeSuffix);
        $this->assertEquals([SearchType::MATCH, SearchType::AUTOCOMPLETE], $request->queryConfig->fields[0]->searchTypes);

        // Verify nested field
        $this->assertEquals('variants', $request->queryConfig->fields[1]->name);
        $this->assertEquals(QueryFieldType::NESTED, $request->queryConfig->fields[1]->type);
        $this->assertEquals('variants', $request->queryConfig->fields[1]->nestedPath);
        $this->assertEquals(ScoreMode::MAX, $request->queryConfig->fields[1]->scoreMode);
        $this->assertCount(1, $request->queryConfig->fields[1]->nestedFields);
        $this->assertEquals('sku', $request->queryConfig->fields[1]->nestedFields[0]->name);

        // Verify cross fields matching
        $this->assertEquals(['product_name', 'brand'], $request->queryConfig->crossFieldsMatching);

        // Verify response config
        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['name', 'price', 'imageUrl'], $request->responseConfig->sourceFields);
        $this->assertEquals(['price', 'created_at'], $request->responseConfig->sortableFields);

        // Verify highlight config
        $this->assertNotNull($request->responseConfig->highlightConfig);
        $this->assertTrue($request->responseConfig->highlightConfig->enabled);
        $this->assertCount(1, $request->responseConfig->highlightConfig->fields);
        $this->assertEquals('name', $request->responseConfig->highlightConfig->fields[0]->fieldName);

        // Verify variant enrichment
        $this->assertNotNull($request->responseConfig->variantEnrichment);
        $this->assertEquals(['price', 'imageUrl'], $request->responseConfig->variantEnrichment->replaceFields);
    }

    public function testThrowsExceptionForNonStringLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported locale at index 1 must be a string.');

        new SearchConfigurationRequest(['valid', 123]);
    }

    public function testThrowsExceptionForEmptyLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported locale at index 0 cannot be empty.');

        new SearchConfigurationRequest(['']);
    }

    public function testWithSupportedLocalesReturnsNewInstance(): void
    {
        $request = new SearchConfigurationRequest();
        $newRequest = $request->withSupportedLocales(['lt-LT', 'en-US']);

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals([], $request->supportedLocales);
        $this->assertEquals(['lt-LT', 'en-US'], $newRequest->supportedLocales);
    }

    public function testWithAddedSupportedLocaleReturnsNewInstance(): void
    {
        $request = new SearchConfigurationRequest(['lt-LT']);
        $newRequest = $request->withAddedSupportedLocale('en-US');

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals(['lt-LT'], $request->supportedLocales);
        $this->assertEquals(['lt-LT', 'en-US'], $newRequest->supportedLocales);
    }

    public function testWithQueryConfigReturnsNewInstance(): void
    {
        $queryConfig = new QueryConfig();
        $request = new SearchConfigurationRequest();
        $newRequest = $request->withQueryConfig($queryConfig);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->queryConfig);
        $this->assertSame($queryConfig, $newRequest->queryConfig);
    }

    public function testWithResponseConfigReturnsNewInstance(): void
    {
        $responseConfig = new ResponseConfig();
        $request = new SearchConfigurationRequest();
        $newRequest = $request->withResponseConfig($responseConfig);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->responseConfig);
        $this->assertSame($responseConfig, $newRequest->responseConfig);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'supported_locales' => ['lt-LT', 'en-US'],
            'query_config' => [
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'product_name',
                        'locale_suffix' => 'lt-LT',
                        'search_types' => ['match', 'autocomplete'],
                    ],
                ],
                'cross_fields_matching' => ['product_name', 'brand'],
            ],
            'response_config' => [
                'source_fields' => ['name', 'price'],
                'sortable_fields' => ['price', 'date'],
                'highlight_config' => [
                    'enabled' => true,
                    'fields' => [
                        [
                            'field_name' => 'name',
                            'pre_tags' => ['<em>'],
                            'post_tags' => ['</em>'],
                        ],
                    ],
                ],
                'variant_enrichment' => [
                    'replace_fields' => ['price'],
                ],
            ],
        ];

        $request = SearchConfigurationRequest::fromArray($originalData);
        $serialized = $request->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }

    public function testComplexJsonParsing(): void
    {
        $json = <<<'JSON'
{
    "supported_locales": ["lt-LT"],
    "query_config": {
        "fields": [
            {
                "type": "text",
                "name": "name",
                "locale_suffix": "lt-LT",
                "search_types": ["match", "match-fuzzy", "autocomplete"],
                "last_word_search": true
            },
            {
                "type": "text",
                "name": "brand",
                "locale_suffix": "lt-LT",
                "search_types": ["match"]
            },
            {
                "type": "text",
                "name": "sku",
                "search_types": ["exact", "substring"]
            },
            {
                "type": "nested",
                "name": "variants",
                "nested_path": "variants",
                "score_mode": "max",
                "nested_fields": [
                    {
                        "type": "text",
                        "name": "sku",
                        "search_types": ["exact"]
                    },
                    {
                        "type": "text",
                        "name": "attrs",
                        "locale_aware": true,
                        "search_types": ["match"]
                    }
                ]
            }
        ],
        "cross_fields_matching": ["name", "brand"]
    },
    "response_config": {
        "source_fields": ["id", "name", "brand", "price", "imageUrl", "productUrl"],
        "sortable_fields": ["price", "name", "created_at"],
        "highlight_config": {
            "enabled": true,
            "fields": [
                {
                    "field_name": "name",
                    "locale_suffix": "lt-LT",
                    "pre_tags": ["<mark>"],
                    "post_tags": ["</mark>"]
                }
            ]
        },
        "variant_enrichment": {
            "replace_fields": ["price", "imageUrl", "productUrl"]
        }
    }
}
JSON;

        $request = SearchConfigurationRequest::fromJson($json);

        // Verify supported locales
        $this->assertEquals(['lt-LT'], $request->supportedLocales);

        // Verify query config fields
        $this->assertNotNull($request->queryConfig);
        $this->assertCount(4, $request->queryConfig->fields);

        // First field: name
        $nameField = $request->queryConfig->fields[0];
        $this->assertEquals('name', $nameField->name);
        $this->assertEquals(QueryFieldType::TEXT, $nameField->type);
        $this->assertEquals('lt-LT', $nameField->localeSuffix);
        $this->assertEquals([SearchType::MATCH, SearchType::MATCH_FUZZY, SearchType::AUTOCOMPLETE], $nameField->searchTypes);
        $this->assertTrue($nameField->lastWordSearch);

        // Third field: sku (non-localized)
        $skuField = $request->queryConfig->fields[2];
        $this->assertEquals('sku', $skuField->name);
        $this->assertNull($skuField->localeSuffix);
        $this->assertEquals([SearchType::EXACT, SearchType::SUBSTRING], $skuField->searchTypes);

        // Fourth field: variants (nested)
        $variantsField = $request->queryConfig->fields[3];
        $this->assertEquals('variants', $variantsField->name);
        $this->assertEquals(QueryFieldType::NESTED, $variantsField->type);
        $this->assertEquals('variants', $variantsField->nestedPath);
        $this->assertEquals(ScoreMode::MAX, $variantsField->scoreMode);
        $this->assertCount(2, $variantsField->nestedFields);

        // Nested field attrs
        $attrsField = $variantsField->nestedFields[1];
        $this->assertEquals('attrs', $attrsField->name);
        $this->assertTrue($attrsField->localeAware);

        // Verify cross fields matching
        $this->assertEquals(['name', 'brand'], $request->queryConfig->crossFieldsMatching);

        // Verify response config
        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(
            ['id', 'name', 'brand', 'price', 'imageUrl', 'productUrl'],
            $request->responseConfig->sourceFields
        );
        $this->assertEquals(
            ['price', 'name', 'created_at'],
            $request->responseConfig->sortableFields
        );

        // Verify highlight config
        $this->assertNotNull($request->responseConfig->highlightConfig);
        $this->assertTrue($request->responseConfig->highlightConfig->enabled);
        $this->assertCount(1, $request->responseConfig->highlightConfig->fields);
        $highlightField = $request->responseConfig->highlightConfig->fields[0];
        $this->assertEquals('name', $highlightField->fieldName);
        $this->assertEquals('lt-LT', $highlightField->localeSuffix);
        $this->assertEquals(['<mark>'], $highlightField->preTags);
        $this->assertEquals(['</mark>'], $highlightField->postTags);

        // Verify variant enrichment
        $this->assertNotNull($request->responseConfig->variantEnrichment);
        $this->assertEquals(
            ['price', 'imageUrl', 'productUrl'],
            $request->responseConfig->variantEnrichment->replaceFields
        );
    }
}
