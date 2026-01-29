<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use BradSearch\SyncSdk\V2\ValueObjects\Search\FuzzyMatchingConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\FuzzyMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class QueryConfigurationRequestTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $searchFields = [
            new SearchFieldConfig('name', 1, 2.0, MatchMode::FUZZY),
        ];
        $config = new QueryConfigurationRequest($searchFields);

        $this->assertCount(1, $config->searchFields);
        $this->assertEquals('name', $config->searchFields[0]->field);
        $this->assertNull($config->fuzzyMatching);
        $this->assertNull($config->popularityBoost);
        $this->assertEquals(MultiWordOperator::AND, $config->multiWordOperator);
        $this->assertNull($config->minScore);
    }

    public function testConstructorWithAllParameters(): void
    {
        $searchFields = [
            new SearchFieldConfig('name', 1, 2.0, MatchMode::FUZZY),
            new SearchFieldConfig('description', 2, 1.5, MatchMode::PHRASE_PREFIX),
        ];
        $fuzzyMatching = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2);
        $popularityBoost = new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0);

        $config = new QueryConfigurationRequest(
            $searchFields,
            $fuzzyMatching,
            $popularityBoost,
            MultiWordOperator::OR,
            0.5
        );

        $this->assertCount(2, $config->searchFields);
        $this->assertSame($fuzzyMatching, $config->fuzzyMatching);
        $this->assertSame($popularityBoost, $config->popularityBoost);
        $this->assertEquals(MultiWordOperator::OR, $config->multiWordOperator);
        $this->assertEquals(0.5, $config->minScore);
    }

    public function testThrowsExceptionForEmptySearchFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one search field is required.');

        new QueryConfigurationRequest([]);
    }

    public function testThrowsExceptionForInvalidSearchFieldType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search field at index 1 must be an instance of SearchFieldConfig.');

        new QueryConfigurationRequest([
            new SearchFieldConfig('name', 1, 1.0),
            'invalid',
        ]);
    }

    public function testThrowsExceptionForMinScoreBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0, got -0.10.');

        new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            -0.1
        );
    }

    public function testThrowsExceptionForMinScoreAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0, got 1.10.');

        new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            1.1
        );
    }

    public function testAcceptsMinimumMinScore(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            0.0
        );

        $this->assertEquals(0.0, $config->minScore);
    }

    public function testAcceptsMaximumMinScore(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            1.0
        );

        $this->assertEquals(1.0, $config->minScore);
    }

    public function testExtendsValueObject(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 2.0, MatchMode::FUZZY)]
        );

        $expected = [
            'search_fields' => [
                [
                    'field' => 'name',
                    'position' => 1,
                    'boost_multiplier' => 2.0,
                    'match_mode' => 'fuzzy',
                ],
            ],
            'multi_word_operator' => 'and',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $config = new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name', 1, 2.0, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('description', 2, 1.5, MatchMode::FUZZY),
            ],
            new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2),
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0),
            MultiWordOperator::OR,
            0.5
        );

        $expected = [
            'search_fields' => [
                [
                    'field' => 'name',
                    'position' => 1,
                    'boost_multiplier' => 2.0,
                    'match_mode' => 'phrase_prefix',
                ],
                [
                    'field' => 'description',
                    'position' => 2,
                    'boost_multiplier' => 1.5,
                    'match_mode' => 'fuzzy',
                ],
            ],
            'multi_word_operator' => 'or',
            'fuzzy_matching' => [
                'enabled' => true,
                'mode' => 'auto',
                'min_similarity' => 2,
            ],
            'popularity_boost' => [
                'enabled' => true,
                'field' => 'sales_count',
                'algorithm' => 'logarithmic',
                'max_boost' => 3.0,
            ],
            'min_score' => 0.5,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsNullValues(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('fuzzy_matching', $serialized);
        $this->assertArrayNotHasKey('popularity_boost', $serialized);
        $this->assertArrayNotHasKey('min_score', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithSearchFieldsReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $newFields = [new SearchFieldConfig('title', 1, 2.0)];
        $newConfig = $config->withSearchFields($newFields);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('name', $config->searchFields[0]->field);
        $this->assertEquals('title', $newConfig->searchFields[0]->field);
    }

    public function testWithAddedSearchFieldReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $newConfig = $config->withAddedSearchField(
            new SearchFieldConfig('description', 2, 1.5)
        );

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->searchFields);
        $this->assertCount(2, $newConfig->searchFields);
        $this->assertEquals('description', $newConfig->searchFields[1]->field);
    }

    public function testWithFuzzyMatchingReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $fuzzyMatching = new FuzzyMatchingConfig(true, FuzzyMode::FIXED, 1);
        $newConfig = $config->withFuzzyMatching($fuzzyMatching);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->fuzzyMatching);
        $this->assertSame($fuzzyMatching, $newConfig->fuzzyMatching);
    }

    public function testWithPopularityBoostReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $popularityBoost = new PopularityBoostConfig(true, 'views', BoostAlgorithm::LINEAR, 2.0);
        $newConfig = $config->withPopularityBoost($popularityBoost);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->popularityBoost);
        $this->assertSame($popularityBoost, $newConfig->popularityBoost);
    }

    public function testWithMultiWordOperatorReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $newConfig = $config->withMultiWordOperator(MultiWordOperator::OR);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(MultiWordOperator::AND, $config->multiWordOperator);
        $this->assertEquals(MultiWordOperator::OR, $newConfig->multiWordOperator);
    }

    public function testWithMinScoreReturnsNewInstance(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $newConfig = $config->withMinScore(0.75);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->minScore);
        $this->assertEquals(0.75, $newConfig->minScore);
    }

    public function testWithMinScoreCanSetToNull(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            0.5
        );

        $newConfig = $config->withMinScore(null);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(0.5, $config->minScore);
        $this->assertNull($newConfig->minScore);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 2.0, MatchMode::FUZZY)],
            new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2),
            null,
            MultiWordOperator::AND,
            0.5
        );

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('search_fields', $decoded);
        $this->assertArrayHasKey('fuzzy_matching', $decoded);
        $this->assertArrayHasKey('multi_word_operator', $decoded);
        $this->assertArrayHasKey('min_score', $decoded);
        $this->assertArrayNotHasKey('popularity_boost', $decoded);
    }

    /**
     * @dataProvider validMinScoreDataProvider
     */
    public function testAcceptsValidMinScores(float $minScore): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            $minScore
        );

        $this->assertEquals($minScore, $config->minScore);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validMinScoreDataProvider(): array
    {
        return [
            'zero' => [0.0],
            'quarter' => [0.25],
            'half' => [0.5],
            'three_quarters' => [0.75],
            'one' => [1.0],
        ];
    }

    /**
     * @dataProvider invalidMinScoreDataProvider
     */
    public function testRejectsInvalidMinScores(float $minScore): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)],
            null,
            null,
            MultiWordOperator::AND,
            $minScore
        );
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalidMinScoreDataProvider(): array
    {
        return [
            'negative' => [-0.1],
            'above_one' => [1.01],
            'large_negative' => [-1.0],
            'large_positive' => [2.0],
        ];
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new QueryConfigurationRequest([]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('search_fields', $e->argumentName);
            $this->assertEquals([], $e->invalidValue);
        }
    }

    public function testWithSearchFieldsValidatesNewFields(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one search field is required.');

        $config->withSearchFields([]);
    }

    public function testWithMinScoreValidatesNewValue(): void
    {
        $config = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1, 1.0)]
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0, got 1.50.');

        $config->withMinScore(1.5);
    }

    /**
     * Test output matches OpenAPI QueryConfigurationRequest schema structure.
     */
    public function testMatchesQueryConfigurationRequestSchema(): void
    {
        $config = new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name_lt-LT', 1, 2.0, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('brand_lt-LT', 2, 1.5, MatchMode::FUZZY),
            ],
            new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2),
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0),
            MultiWordOperator::AND,
            0.5
        );

        $serialized = $config->jsonSerialize();

        // Verify required structure
        $this->assertArrayHasKey('search_fields', $serialized);
        $this->assertArrayHasKey('multi_word_operator', $serialized);

        // Verify search_fields structure
        $this->assertIsArray($serialized['search_fields']);
        $this->assertCount(2, $serialized['search_fields']);

        foreach ($serialized['search_fields'] as $field) {
            $this->assertArrayHasKey('field', $field);
            $this->assertArrayHasKey('position', $field);
            $this->assertArrayHasKey('boost_multiplier', $field);
            $this->assertArrayHasKey('match_mode', $field);
        }

        // Verify optional configs are present when set
        $this->assertArrayHasKey('fuzzy_matching', $serialized);
        $this->assertArrayHasKey('popularity_boost', $serialized);
        $this->assertArrayHasKey('min_score', $serialized);

        // Verify types
        $this->assertIsString($serialized['multi_word_operator']);
        $this->assertIsFloat($serialized['min_score']);
    }

    /**
     * Test building an "advanced" configuration example matching OpenAPI documentation.
     */
    public function testAdvancedConfigurationExample(): void
    {
        $config = new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name_lt-LT', 1, 2.5, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('brand_lt-LT', 2, 2.0, MatchMode::FUZZY),
                new SearchFieldConfig('description_lt-LT', 3, 1.0, MatchMode::FUZZY),
                new SearchFieldConfig('sku', 4, 3.0, MatchMode::EXACT),
            ],
            new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2),
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0),
            MultiWordOperator::AND,
            0.1
        );

        $expected = [
            'search_fields' => [
                [
                    'field' => 'name_lt-LT',
                    'position' => 1,
                    'boost_multiplier' => 2.5,
                    'match_mode' => 'phrase_prefix',
                ],
                [
                    'field' => 'brand_lt-LT',
                    'position' => 2,
                    'boost_multiplier' => 2.0,
                    'match_mode' => 'fuzzy',
                ],
                [
                    'field' => 'description_lt-LT',
                    'position' => 3,
                    'boost_multiplier' => 1.0,
                    'match_mode' => 'fuzzy',
                ],
                [
                    'field' => 'sku',
                    'position' => 4,
                    'boost_multiplier' => 3.0,
                    'match_mode' => 'exact',
                ],
            ],
            'multi_word_operator' => 'and',
            'fuzzy_matching' => [
                'enabled' => true,
                'mode' => 'auto',
                'min_similarity' => 2,
            ],
            'popularity_boost' => [
                'enabled' => true,
                'field' => 'sales_count',
                'algorithm' => 'logarithmic',
                'max_boost' => 3.0,
            ],
            'min_score' => 0.1,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }
}
