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
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequestBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use PHPUnit\Framework\TestCase;

class QueryConfigurationRequestBuilderTest extends TestCase
{
    public function testBuildCreatesQueryConfigurationRequest(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 2.0))
            ->build();

        $this->assertInstanceOf(QueryConfigurationRequest::class, $request);
        $this->assertCount(1, $request->searchFields);
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $this->assertSame($builder, $builder->addSearchField(new SearchFieldConfig('name', 1, 1.0)));
        $this->assertSame($builder, $builder->fuzzyMatching(new FuzzyMatchingConfig()));
        $this->assertSame($builder, $builder->popularityBoost(new PopularityBoostConfig(true, 'sales')));
        $this->assertSame($builder, $builder->multiWordOperator(MultiWordOperator::OR));
        $this->assertSame($builder, $builder->minScore(0.5));
    }

    public function testBuildWithMultipleSearchFields(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 2.0))
            ->addSearchField(new SearchFieldConfig('description', 2, 1.5))
            ->addSearchField(new SearchFieldConfig('sku', 3, 3.0))
            ->build();

        $this->assertCount(3, $request->searchFields);
        $this->assertEquals('name', $request->searchFields[0]->field);
        $this->assertEquals('description', $request->searchFields[1]->field);
        $this->assertEquals('sku', $request->searchFields[2]->field);
    }

    public function testBuildWithFuzzyMatching(): void
    {
        $builder = new QueryConfigurationRequestBuilder();
        $fuzzyMatching = new FuzzyMatchingConfig(true, FuzzyMode::FIXED, 1);

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->fuzzyMatching($fuzzyMatching)
            ->build();

        $this->assertSame($fuzzyMatching, $request->fuzzyMatching);
    }

    public function testBuildWithPopularityBoost(): void
    {
        $builder = new QueryConfigurationRequestBuilder();
        $popularityBoost = new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LINEAR, 5.0);

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->popularityBoost($popularityBoost)
            ->build();

        $this->assertSame($popularityBoost, $request->popularityBoost);
    }

    public function testBuildWithMultiWordOperator(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->multiWordOperator(MultiWordOperator::OR)
            ->build();

        $this->assertEquals(MultiWordOperator::OR, $request->multiWordOperator);
    }

    public function testBuildWithMinScore(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->minScore(0.75)
            ->build();

        $this->assertEquals(0.75, $request->minScore);
    }

    public function testThrowsExceptionWhenNoSearchFieldsAdded(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one search field is required.');

        $builder->build();
    }

    public function testResetClearsAllValues(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->fuzzyMatching(new FuzzyMatchingConfig())
            ->popularityBoost(new PopularityBoostConfig(true, 'sales'))
            ->multiWordOperator(MultiWordOperator::OR)
            ->minScore(0.5)
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one search field is required.');

        $builder->build();
    }

    public function testResetReturnsBuilder(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $this->assertSame($builder, $builder->reset());
    }

    public function testCanReuseBuilderAfterReset(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request1 = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->multiWordOperator(MultiWordOperator::AND)
            ->build();

        $builder->reset();

        $request2 = $builder
            ->addSearchField(new SearchFieldConfig('title', 1, 2.0))
            ->multiWordOperator(MultiWordOperator::OR)
            ->build();

        $this->assertEquals('name', $request1->searchFields[0]->field);
        $this->assertEquals(MultiWordOperator::AND, $request1->multiWordOperator);
        $this->assertEquals('title', $request2->searchFields[0]->field);
        $this->assertEquals(MultiWordOperator::OR, $request2->multiWordOperator);
        $this->assertNotSame($request1, $request2);
    }

    public function testDefaultMultiWordOperatorIsAnd(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->build();

        $this->assertEquals(MultiWordOperator::AND, $request->multiWordOperator);
    }

    public function testBuilderCanBuildMultipleRequestsSequentially(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request1 = $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->build();

        $builder->reset();

        $request2 = $builder
            ->addSearchField(new SearchFieldConfig('title', 1, 2.0))
            ->addSearchField(new SearchFieldConfig('description', 2, 1.5))
            ->fuzzyMatching(new FuzzyMatchingConfig())
            ->build();

        $this->assertCount(1, $request1->searchFields);
        $this->assertNull($request1->fuzzyMatching);

        $this->assertCount(2, $request2->searchFields);
        $this->assertNotNull($request2->fuzzyMatching);
    }

    public function testOrderOfSearchFieldsIsPreserved(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('sku', 1, 1.0))
            ->addSearchField(new SearchFieldConfig('name', 2, 1.0))
            ->addSearchField(new SearchFieldConfig('description', 3, 1.0))
            ->build();

        $this->assertEquals('sku', $request->searchFields[0]->field);
        $this->assertEquals('name', $request->searchFields[1]->field);
        $this->assertEquals('description', $request->searchFields[2]->field);
    }

    /**
     * Test building an "advanced" configuration using the builder.
     * This verifies the builder produces the exact structure documented in the API.
     */
    public function testBuildingAdvancedConfigurationWithBuilder(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $request = $builder
            ->addSearchField(new SearchFieldConfig('name_lt-LT', 1, 2.5, MatchMode::PHRASE_PREFIX))
            ->addSearchField(new SearchFieldConfig('brand_lt-LT', 2, 2.0, MatchMode::FUZZY))
            ->addSearchField(new SearchFieldConfig('description_lt-LT', 3, 1.0, MatchMode::FUZZY))
            ->addSearchField(new SearchFieldConfig('sku', 4, 3.0, MatchMode::EXACT))
            ->fuzzyMatching(new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2))
            ->popularityBoost(new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0))
            ->multiWordOperator(MultiWordOperator::AND)
            ->minScore(0.1)
            ->build();

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

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testBuildWithInvalidMinScoreThrowsException(): void
    {
        $builder = new QueryConfigurationRequestBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0');

        $builder
            ->addSearchField(new SearchFieldConfig('name', 1, 1.0))
            ->minScore(1.5)
            ->build();
    }
}
