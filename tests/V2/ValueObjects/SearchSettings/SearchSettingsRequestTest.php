<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\BoostMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreModifier;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\NestedFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoreMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoringConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehavior;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehaviorType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SearchSettingsRequestTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $request = new SearchSettingsRequest('app_123');

        $this->assertEquals('app_123', $request->appId);
        $this->assertNull($request->searchConfig);
        $this->assertNull($request->scoringConfig);
        $this->assertNull($request->responseConfig);
    }

    public function testConstructorWithAllParameters(): void
    {
        $searchConfig = new SearchConfig([new FieldConfig('name', 'name')]);
        $scoringConfig = new ScoringConfig(new FunctionScoreConfig('sales'), 0.5);
        $responseConfig = new ResponseConfig(['name'], ['price']);

        $request = new SearchSettingsRequest(
            'app_123',
            $searchConfig,
            $scoringConfig,
            $responseConfig
        );

        $this->assertEquals('app_123', $request->appId);
        $this->assertSame($searchConfig, $request->searchConfig);
        $this->assertSame($scoringConfig, $request->scoringConfig);
        $this->assertSame($responseConfig, $request->responseConfig);
    }

    public function testExtendsValueObject(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $this->assertInstanceOf(ValueObject::class, $request);
    }

    public function testImplementsJsonSerializable(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $this->assertInstanceOf(JsonSerializable::class, $request);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $request = new SearchSettingsRequest('app_123');

        $expected = [
            'app_id' => 'app_123',
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $searchConfig = new SearchConfig([new FieldConfig('name_field', 'name', 'en')]);
        $scoringConfig = new ScoringConfig(null, 0.3);
        $responseConfig = new ResponseConfig(['name', 'price'], ['price']);

        $request = new SearchSettingsRequest(
            'app_123',
            $searchConfig,
            $scoringConfig,
            $responseConfig
        );

        $expected = [
            'app_id' => 'app_123',
            'query_config' => [
                'fields' => [
                    [
                        'id' => 'name_field',
                        'field_name' => 'name',
                        'locale_suffix' => 'en',
                    ],
                ],
            ],
            'scoring_config' => [
                'min_score' => 0.3,
            ],
            'response_config' => [
                'source_fields' => ['name', 'price'],
                'sortable_fields' => ['price'],
            ],
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testJsonSerializeOmitsEmptyConfigs(): void
    {
        $emptySearchConfig = new SearchConfig();
        $emptyScoringConfig = new ScoringConfig();
        $emptyResponseConfig = new ResponseConfig();

        $request = new SearchSettingsRequest(
            'app_123',
            $emptySearchConfig,
            $emptyScoringConfig,
            $emptyResponseConfig
        );

        $serialized = $request->jsonSerialize();

        $this->assertEquals(['app_id' => 'app_123'], $serialized);
    }

    public function testThrowsExceptionForEmptyAppId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application ID cannot be empty.');

        new SearchSettingsRequest('');
    }

    public function testWithAppIdReturnsNewInstance(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $newRequest = $request->withAppId('app_456');

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals('app_123', $request->appId);
        $this->assertEquals('app_456', $newRequest->appId);
    }

    public function testWithSearchConfigReturnsNewInstance(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $searchConfig = new SearchConfig([new FieldConfig('name', 'name')]);
        $newRequest = $request->withSearchConfig($searchConfig);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->searchConfig);
        $this->assertSame($searchConfig, $newRequest->searchConfig);
    }

    public function testWithScoringConfigReturnsNewInstance(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $scoringConfig = new ScoringConfig(null, 0.5);
        $newRequest = $request->withScoringConfig($scoringConfig);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->scoringConfig);
        $this->assertSame($scoringConfig, $newRequest->scoringConfig);
    }

    public function testWithResponseConfigReturnsNewInstance(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $responseConfig = new ResponseConfig(['name']);
        $newRequest = $request->withResponseConfig($responseConfig);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->responseConfig);
        $this->assertSame($responseConfig, $newRequest->responseConfig);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new SearchSettingsRequest('');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('app_id', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $request = new SearchSettingsRequest('app_123');
        $this->assertEquals($request->jsonSerialize(), $request->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $searchConfig = new SearchConfig([new FieldConfig('name', 'name')]);
        $request = new SearchSettingsRequest('app_123', $searchConfig);

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('app_id', $decoded);
        $this->assertArrayHasKey('query_config', $decoded);
        $this->assertEquals('app_123', $decoded['app_id']);
    }

    /**
     * Test output matches OpenAPI SearchSettingsRequest schema structure for full configuration.
     */
    public function testMatchesSearchSettingsRequestSchemaFullConfiguration(): void
    {
        $searchBehaviors = [
            new SearchBehavior(SearchBehaviorType::FUZZY, 'keyword', 'and', 2.0, 1, 2),
            new SearchBehavior(SearchBehaviorType::PHRASE_PREFIX),
        ];

        $fields = [
            new FieldConfig('name_field', 'name', 'en', $searchBehaviors),
            new FieldConfig('description_field', 'description', 'en'),
        ];

        $nestedFields = [
            new NestedFieldConfig(
                'variants_config',
                'variants',
                null,
                ScoreMode::MAX,
                [new FieldConfig('variant_sku', 'sku')]
            ),
        ];

        $multiMatchConfigs = [
            new MultiMatchConfig(
                'name_desc_multi',
                ['name_field', 'description_field'],
                MultiMatchType::CROSS_FIELDS,
                'and',
                1.5
            ),
        ];

        $searchConfig = new SearchConfig($fields, $nestedFields, $multiMatchConfigs);

        $functionScore = new FunctionScoreConfig(
            'sales_count',
            FunctionScoreModifier::LOG1P,
            1.5,
            1.0,
            BoostMode::MULTIPLY,
            10.0
        );

        $scoringConfig = new ScoringConfig($functionScore, 0.1);

        $responseConfig = new ResponseConfig(
            ['id', 'name', 'price', 'description'],
            ['price', 'created_at', 'sales_count']
        );

        $request = new SearchSettingsRequest(
            'my_app_123',
            $searchConfig,
            $scoringConfig,
            $responseConfig
        );

        $serialized = $request->jsonSerialize();

        // Verify top-level structure
        $this->assertArrayHasKey('app_id', $serialized);
        $this->assertArrayHasKey('query_config', $serialized);
        $this->assertArrayHasKey('scoring_config', $serialized);
        $this->assertArrayHasKey('response_config', $serialized);

        // Verify query_config structure
        $this->assertArrayHasKey('fields', $serialized['query_config']);
        $this->assertArrayHasKey('nested_fields', $serialized['query_config']);
        $this->assertArrayHasKey('multi_match_configs', $serialized['query_config']);

        // Verify fields structure
        $this->assertCount(2, $serialized['query_config']['fields']);
        $this->assertArrayHasKey('id', $serialized['query_config']['fields'][0]);
        $this->assertArrayHasKey('field_name', $serialized['query_config']['fields'][0]);
        $this->assertArrayHasKey('search_behaviors', $serialized['query_config']['fields'][0]);

        // Verify search_behaviors structure
        $this->assertCount(2, $serialized['query_config']['fields'][0]['search_behaviors']);
        $this->assertArrayHasKey('type', $serialized['query_config']['fields'][0]['search_behaviors'][0]);
        $this->assertArrayHasKey('boost', $serialized['query_config']['fields'][0]['search_behaviors'][0]);

        // Verify nested_fields structure
        $this->assertCount(1, $serialized['query_config']['nested_fields']);
        $this->assertArrayHasKey('path', $serialized['query_config']['nested_fields'][0]);
        $this->assertArrayHasKey('score_mode', $serialized['query_config']['nested_fields'][0]);
        $this->assertEquals('max', $serialized['query_config']['nested_fields'][0]['score_mode']);

        // Verify multi_match_configs structure
        $this->assertCount(1, $serialized['query_config']['multi_match_configs']);
        $this->assertArrayHasKey('field_ids', $serialized['query_config']['multi_match_configs'][0]);
        $this->assertArrayHasKey('type', $serialized['query_config']['multi_match_configs'][0]);
        $this->assertEquals('cross_fields', $serialized['query_config']['multi_match_configs'][0]['type']);

        // Verify scoring_config structure
        $this->assertArrayHasKey('function_score', $serialized['scoring_config']);
        $this->assertArrayHasKey('min_score', $serialized['scoring_config']);
        $this->assertArrayHasKey('field', $serialized['scoring_config']['function_score']);
        $this->assertArrayHasKey('modifier', $serialized['scoring_config']['function_score']);
        $this->assertArrayHasKey('max_boost', $serialized['scoring_config']['function_score']);

        // Verify response_config structure
        $this->assertArrayHasKey('source_fields', $serialized['response_config']);
        $this->assertArrayHasKey('sortable_fields', $serialized['response_config']);
        $this->assertCount(4, $serialized['response_config']['source_fields']);
        $this->assertCount(3, $serialized['response_config']['sortable_fields']);

        // Verify specific values
        $this->assertEquals('my_app_123', $serialized['app_id']);
        $this->assertEquals(0.1, $serialized['scoring_config']['min_score']);
        $this->assertEquals('sales_count', $serialized['scoring_config']['function_score']['field']);
    }
}
