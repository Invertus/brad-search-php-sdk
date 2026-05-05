<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\BoostMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreModifier;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\NestedFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoringConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequestBuilder;
use PHPUnit\Framework\TestCase;

class SearchSettingsRequestBuilderTest extends TestCase
{
    public function testBuildWithMinimalConfig(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->build();

        $this->assertInstanceOf(SearchSettingsRequest::class, $request);
        $this->assertEquals('app_123', $request->appId);
        $this->assertNull($request->searchConfig);
        $this->assertNull($request->scoringConfig);
        $this->assertNull($request->responseConfig);
    }

    public function testBuildWithFields(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addField(new FieldConfig('name_field', 'name'))
            ->addField(new FieldConfig('desc_field', 'description'))
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertCount(2, $request->searchConfig->fields);
    }

    public function testBuildWithNestedFields(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addNestedField(new NestedFieldConfig('variants', 'variants'))
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertCount(1, $request->searchConfig->nestedFields);
    }

    public function testBuildWithMultiMatchConfigs(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addMultiMatchConfig(new MultiMatchConfig('multi', ['field1', 'field2']))
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertCount(1, $request->searchConfig->multiMatchConfigs);
    }

    public function testBuildWithFunctionScore(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $functionScore = new FunctionScoreConfig('sales_count');
        $request = $builder
            ->appId('app_123')
            ->functionScore($functionScore)
            ->build();

        $this->assertNotNull($request->scoringConfig);
        $this->assertSame($functionScore, $request->scoringConfig->functionScore);
    }

    public function testBuildWithMinScore(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->minScore(0.5)
            ->build();

        $this->assertNotNull($request->scoringConfig);
        $this->assertEquals(0.5, $request->scoringConfig->minScore);
    }

    public function testBuildWithSourceFields(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addSourceField('name')
            ->addSourceField('price')
            ->build();

        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['name', 'price'], $request->responseConfig->sourceFields);
    }

    public function testBuildWithSourceFieldsArray(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->sourceFields(['name', 'price', 'description'])
            ->build();

        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['name', 'price', 'description'], $request->responseConfig->sourceFields);
    }

    public function testBuildWithSortableFields(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addSortableField('price')
            ->addSortableField('date')
            ->build();

        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['price', 'date'], $request->responseConfig->sortableFields);
    }

    public function testBuildWithSortableFieldsArray(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->sortableFields(['price', 'date', 'sales'])
            ->build();

        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['price', 'date', 'sales'], $request->responseConfig->sortableFields);
    }

    public function testBuildWithSearchConfig(): void
    {
        $searchConfig = new SearchConfig(
            [new FieldConfig('name', 'name')],
            [new NestedFieldConfig('variants', 'variants')],
            [new MultiMatchConfig('multi', ['name'])]
        );

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->searchConfig($searchConfig)
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertCount(1, $request->searchConfig->fields);
        $this->assertCount(1, $request->searchConfig->nestedFields);
        $this->assertCount(1, $request->searchConfig->multiMatchConfigs);
    }

    public function testBuildWithScoringConfig(): void
    {
        $scoringConfig = new ScoringConfig(
            new FunctionScoreConfig('sales'),
            0.3
        );

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->scoringConfig($scoringConfig)
            ->build();

        $this->assertNotNull($request->scoringConfig);
        $this->assertNotNull($request->scoringConfig->functionScore);
        $this->assertEquals(0.3, $request->scoringConfig->minScore);
    }

    public function testBuildWithResponseConfig(): void
    {
        $responseConfig = new ResponseConfig(
            ['name', 'price'],
            ['price', 'date']
        );

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->responseConfig($responseConfig)
            ->build();

        $this->assertNotNull($request->responseConfig);
        $this->assertEquals(['name', 'price'], $request->responseConfig->sourceFields);
        $this->assertEquals(['price', 'date'], $request->responseConfig->sortableFields);
    }

    public function testBuildWithFullConfiguration(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('my_app')
            ->addField(new FieldConfig('name_field', 'name', 'en'))
            ->addField(new FieldConfig('desc_field', 'description', 'en'))
            ->addNestedField(new NestedFieldConfig('variants_config', 'variants'))
            ->addMultiMatchConfig(new MultiMatchConfig('name_desc', ['name_field', 'desc_field']))
            ->functionScore(new FunctionScoreConfig('sales_count', FunctionScoreModifier::LOG1P, 1.5))
            ->minScore(0.1)
            ->sourceFields(['id', 'name', 'price'])
            ->sortableFields(['price', 'date'])
            ->build();

        $this->assertEquals('my_app', $request->appId);
        $this->assertNotNull($request->searchConfig);
        $this->assertCount(2, $request->searchConfig->fields);
        $this->assertCount(1, $request->searchConfig->nestedFields);
        $this->assertCount(1, $request->searchConfig->multiMatchConfigs);
        $this->assertNotNull($request->scoringConfig);
        $this->assertNotNull($request->scoringConfig->functionScore);
        $this->assertEquals(0.1, $request->scoringConfig->minScore);
        $this->assertNotNull($request->responseConfig);
        $this->assertCount(3, $request->responseConfig->sourceFields);
        $this->assertCount(2, $request->responseConfig->sortableFields);
    }

    public function testThrowsExceptionWhenAppIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application ID is required.');

        $builder = new SearchSettingsRequestBuilder();
        $builder->build();
    }

    public function testThrowsExceptionWhenAppIdEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application ID is required.');

        $builder = new SearchSettingsRequestBuilder();
        $builder->appId('')->build();
    }

    public function testResetClearsAllState(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $builder
            ->appId('app_123')
            ->addField(new FieldConfig('name', 'name'))
            ->addNestedField(new NestedFieldConfig('variants', 'variants'))
            ->addMultiMatchConfig(new MultiMatchConfig('multi', ['name']))
            ->functionScore(new FunctionScoreConfig('sales'))
            ->minScore(0.5)
            ->sourceFields(['name'])
            ->sortableFields(['price'])
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application ID is required.');

        $builder->build();
    }

    public function testBuilderCanBeReused(): void
    {
        $builder = new SearchSettingsRequestBuilder();

        $request1 = $builder
            ->appId('app_1')
            ->addField(new FieldConfig('field1', 'name1'))
            ->build();

        $builder->reset();

        $request2 = $builder
            ->appId('app_2')
            ->addField(new FieldConfig('field2', 'name2'))
            ->build();

        $this->assertEquals('app_1', $request1->appId);
        $this->assertEquals('app_2', $request2->appId);
        $this->assertEquals('field1', $request1->searchConfig->fields[0]->id);
        $this->assertEquals('field2', $request2->searchConfig->fields[0]->id);
    }

    public function testBuildWithSupportedLocales(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->supportedLocales(['lt-LT', 'en-US'])
            ->build();

        $serialized = $request->jsonSerialize();
        $this->assertArrayHasKey('supported_locales', $serialized);
        $this->assertEquals(['lt-LT', 'en-US'], $serialized['supported_locales']);
    }

    public function testBuildWithRawQueryConfig(): void
    {
        $rawConfig = ['fields' => [['id' => 'name', 'field_name' => 'name']]];

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->rawQueryConfig($rawConfig)
            ->build();

        $serialized = $request->jsonSerialize();
        $this->assertArrayHasKey('query_config', $serialized);
        $this->assertEquals($rawConfig, $serialized['query_config']);
    }

    public function testRawQueryConfigTakesPrecedenceOverSearchConfig(): void
    {
        $rawConfig = ['fields' => [['id' => 'raw_field']]];

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addField(new FieldConfig('name', 'name'))
            ->rawQueryConfig($rawConfig)
            ->build();

        $serialized = $request->jsonSerialize();
        // rawQueryConfig should take precedence
        $this->assertEquals($rawConfig, $serialized['query_config']);
    }

    public function testBuildWithFilterConfig(): void
    {
        $filterConfig = [
            'fields' => [
                ['name' => 'brand', 'type' => 'term', 'locale_suffix' => true],
                ['name' => 'price', 'type' => 'range'],
            ],
        ];

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->filterConfig($filterConfig)
            ->build();

        $serialized = $request->jsonSerialize();
        $this->assertArrayHasKey('filter_config', $serialized);
        $this->assertEquals($filterConfig, $serialized['filter_config']);
    }

    public function testBuildWithoutFilterConfigOmitsIt(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->build();

        $serialized = $request->jsonSerialize();
        $this->assertArrayNotHasKey('filter_config', $serialized);
        $this->assertNull($request->filterConfig);
    }

    public function testResetClearsFilterConfig(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $builder
            ->appId('app_123')
            ->filterConfig(['fields' => [['name' => 'brand', 'type' => 'term']]])
            ->reset()
            ->appId('app_456');

        $request = $builder->build();
        $this->assertNull($request->filterConfig);
    }

    public function testBuildWithFeaturesKeyValueMap(): void
    {
        $map = [
            '5' => ['lt-LT' => 'Spalva', 'en-US' => 'Color'],
            '12' => ['lt-LT' => 'Dydis', 'en-US' => 'Size'],
        ];

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->featuresKeyValueMap($map)
            ->build();

        $this->assertEquals($map, $request->featuresKeyValueMap);

        $serialized = $request->jsonSerialize();
        $this->assertArrayHasKey('features_key_value_map', $serialized);
        $this->assertEquals($map, $serialized['features_key_value_map']);
    }

    public function testBuildWithAttributeKeyValueMap(): void
    {
        $map = [
            '3' => ['lt-LT' => 'Spalva', 'en-US' => 'Color'],
            '7' => ['lt-LT' => 'Dydis', 'en-US' => 'Size'],
        ];

        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->attributeKeyValueMap($map)
            ->build();

        $this->assertEquals($map, $request->attributeKeyValueMap);

        $serialized = $request->jsonSerialize();
        $this->assertArrayHasKey('attribute_key_value_map', $serialized);
        $this->assertEquals($map, $serialized['attribute_key_value_map']);
    }

    public function testFluentInterfaceReturnsSelf(): void
    {
        $builder = new SearchSettingsRequestBuilder();

        $this->assertSame($builder, $builder->appId('app_123'));
        $this->assertSame($builder, $builder->addField(new FieldConfig('id', 'name')));
        $this->assertSame($builder, $builder->addNestedField(new NestedFieldConfig('id', 'path')));
        $this->assertSame($builder, $builder->addMultiMatchConfig(new MultiMatchConfig('id', ['f1'])));
        $this->assertSame($builder, $builder->functionScore(new FunctionScoreConfig('field')));
        $this->assertSame($builder, $builder->minScore(0.5));
        $this->assertSame($builder, $builder->addSourceField('name'));
        $this->assertSame($builder, $builder->sourceFields(['name']));
        $this->assertSame($builder, $builder->addSortableField('price'));
        $this->assertSame($builder, $builder->sortableFields(['price']));
        $this->assertSame($builder, $builder->supportedLocales(['lt-LT']));
        $this->assertSame($builder, $builder->rawQueryConfig(['fields' => []]));
        $this->assertSame($builder, $builder->filterConfig(['fields' => []]));
        $this->assertSame($builder, $builder->featuresKeyValueMap(['5' => ['lt-LT' => 'Spalva']]));
        $this->assertSame($builder, $builder->attributeKeyValueMap(['3' => ['lt-LT' => 'Spalva']]));
        $this->assertSame($builder, $builder->searchConfig(new SearchConfig()));
        $this->assertSame($builder, $builder->scoringConfig(new ScoringConfig()));
        $this->assertSame($builder, $builder->responseConfig(new ResponseConfig()));
        $this->assertSame($builder, $builder->reset());
    }

    public function testNoSearchConfigWhenNoFieldsAdded(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->minScore(0.5)
            ->build();

        $this->assertNull($request->searchConfig);
        $this->assertNotNull($request->scoringConfig);
    }

    public function testNoScoringConfigWhenNoScoringSet(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addField(new FieldConfig('name', 'name'))
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertNull($request->scoringConfig);
    }

    public function testNoResponseConfigWhenNoResponseFieldsSet(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->addField(new FieldConfig('name', 'name'))
            ->build();

        $this->assertNotNull($request->searchConfig);
        $this->assertNull($request->responseConfig);
    }

    public function testBuildWithSimilarity(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $request = $builder
            ->appId('app_123')
            ->similarity('boolean')
            ->build();

        $this->assertEquals('boolean', $request->similarity);
        $this->assertEquals('boolean', $request->jsonSerialize()['similarity']);
    }

    public function testResetClearsSimilarity(): void
    {
        $builder = new SearchSettingsRequestBuilder();
        $builder->appId('app_123')->similarity('boolean');
        $builder->reset();

        $request = $builder->appId('app_456')->build();

        $this->assertNull($request->similarity);
    }
}
