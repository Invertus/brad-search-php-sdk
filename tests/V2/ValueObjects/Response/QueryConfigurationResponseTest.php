<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\QueryConfigurationResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class QueryConfigurationResponseTest extends TestCase
{
    private function createSearchField(string $field = 'name', int $position = 1): SearchFieldConfig
    {
        return new SearchFieldConfig($field, $position, MatchMode::FUZZY);
    }

    public function testConstructorWithValidValues(): void
    {
        $searchFields = [$this->createSearchField()];

        $response = new QueryConfigurationResponse(
            status: 'success',
            indexName: 'products',
            cacheTtlHours: 24,
            searchFields: $searchFields
        );

        $this->assertEquals('success', $response->status);
        $this->assertEquals('products', $response->indexName);
        $this->assertEquals(24, $response->cacheTtlHours);
        $this->assertCount(1, $response->searchFields);
    }

    public function testExtendsValueObject(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'test',
            24,
            [$this->createSearchField()]
        );

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'test',
            24,
            [$this->createSearchField()]
        );

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testConstructorWithAllOptionalParams(): void
    {
        $searchFields = [$this->createSearchField()];
        $popularityBoost = new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 2.0);

        $response = new QueryConfigurationResponse(
            status: 'success',
            indexName: 'products',
            cacheTtlHours: 24,
            searchFields: $searchFields,
            popularityBoost: $popularityBoost,
            multiWordOperator: MultiWordOperator::OR,
            minScore: 0.5
        );

        $this->assertNotNull($response->popularityBoost);
        $this->assertEquals(MultiWordOperator::OR, $response->multiWordOperator);
        $this->assertEquals(0.5, $response->minScore);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'status' => 'success',
            'index_name' => 'products',
            'cache_ttl_hours' => 48,
            'search_fields' => [
                [
                    'field' => 'name',
                    'position' => 1,
                    'match_mode' => 'fuzzy',
                ],
                [
                    'field' => 'description',
                    'position' => 2,
                    'match_mode' => 'exact',
                ],
            ],
            'multi_word_operator' => 'and',
        ];

        $response = QueryConfigurationResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertEquals('products', $response->indexName);
        $this->assertEquals(48, $response->cacheTtlHours);
        $this->assertCount(2, $response->searchFields);
        $this->assertEquals(MultiWordOperator::AND, $response->multiWordOperator);
    }

    public function testFromArrayWithOptionalFields(): void
    {
        $data = [
            'status' => 'success',
            'index_name' => 'products',
            'cache_ttl_hours' => 24,
            'search_fields' => [
                ['field' => 'name', 'position' => 1],
            ],
            'popularity_boost' => [
                'enabled' => true,
                'field' => 'sales',
                'algorithm' => 'logarithmic',
                'max_boost' => 3.0,
            ],
            'multi_word_operator' => 'or',
            'min_score' => 0.3,
        ];

        $response = QueryConfigurationResponse::fromArray($data);

        $this->assertNotNull($response->popularityBoost);
        $this->assertEquals('sales', $response->popularityBoost->field);
        $this->assertEquals(MultiWordOperator::OR, $response->multiWordOperator);
        $this->assertEquals(0.3, $response->minScore);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        QueryConfigurationResponse::fromArray([
            'index_name' => 'test',
            'cache_ttl_hours' => 24,
            'search_fields' => [],
        ]);
    }

    public function testFromArrayThrowsOnMissingIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: index_name');

        QueryConfigurationResponse::fromArray([
            'status' => 'success',
            'cache_ttl_hours' => 24,
            'search_fields' => [],
        ]);
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new QueryConfigurationResponse('', 'products', 24, [$this->createSearchField()]);
    }

    public function testRejectsEmptyIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('index_name cannot be empty');

        new QueryConfigurationResponse('success', '', 24, [$this->createSearchField()]);
    }

    public function testRejectsNegativeCacheTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cache_ttl_hours must be non-negative');

        new QueryConfigurationResponse('success', 'products', -1, [$this->createSearchField()]);
    }

    public function testRejectsNonSearchFieldConfigInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search field at index 1 must be an instance of SearchFieldConfig');

        new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField(), 'not a SearchFieldConfig']
        );
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField()]
        );

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('status', $serialized);
        $this->assertArrayHasKey('index_name', $serialized);
        $this->assertArrayHasKey('cache_ttl_hours', $serialized);
        $this->assertArrayHasKey('search_fields', $serialized);
        $this->assertArrayHasKey('multi_word_operator', $serialized);
        $this->assertEquals('success', $serialized['status']);
        $this->assertEquals('products', $serialized['index_name']);
    }

    public function testJsonSerializeIncludesOptionalFields(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField()],
            new PopularityBoostConfig(true, 'sales'),
            MultiWordOperator::OR,
            0.5
        );

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('popularity_boost', $serialized);
        $this->assertEquals('or', $serialized['multi_word_operator']);
        $this->assertEquals(0.5, $serialized['min_score']);
    }

    public function testJsonSerializeExcludesNullOptionalFields(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField()]
        );

        $serialized = $response->jsonSerialize();

        $this->assertArrayNotHasKey('popularity_boost', $serialized);
        $this->assertArrayNotHasKey('min_score', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField()]
        );

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'status' => 'success',
            'index_name' => 'app_12345_products',
            'cache_ttl_hours' => 24,
            'search_fields' => [
                [
                    'field' => 'name',
                    'position' => 1,
                    'match_mode' => 'fuzzy',
                ],
                [
                    'field' => 'description',
                    'position' => 2,
                    'match_mode' => 'phrase_prefix',
                ],
            ],
            'multi_word_operator' => 'and',
        ];

        $response = QueryConfigurationResponse::fromArray($apiResponse);

        $this->assertEquals('success', $response->status);
        $this->assertEquals('app_12345_products', $response->indexName);
        $this->assertEquals(24, $response->cacheTtlHours);
        $this->assertCount(2, $response->searchFields);
        $this->assertEquals('name', $response->searchFields[0]->field);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new QueryConfigurationResponse(
            'success',
            'products',
            24,
            [$this->createSearchField()]
        );

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals('products', $decoded['index_name']);
        $this->assertEquals(24, $decoded['cache_ttl_hours']);
    }

    public function testAcceptsEmptySearchFields(): void
    {
        $response = new QueryConfigurationResponse('success', 'products', 24, []);

        $this->assertCount(0, $response->searchFields);
    }

    public function testAcceptsZeroCacheTtl(): void
    {
        $response = new QueryConfigurationResponse('success', 'products', 0, [$this->createSearchField()]);

        $this->assertEquals(0, $response->cacheTtlHours);
    }
}
