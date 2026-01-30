<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfigBuilder;
use PHPUnit\Framework\TestCase;

class SearchFieldConfigBuilderTest extends TestCase
{
    public function testBuildCreatesSearchFieldConfig(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config = $builder
            ->withField('name')
            ->withPosition(1)
            ->build();

        $this->assertInstanceOf(SearchFieldConfig::class, $config);
        $this->assertEquals('name', $config->field);
        $this->assertEquals(1, $config->position);
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $this->assertSame($builder, $builder->withField('test'));
        $this->assertSame($builder, $builder->withPosition(1));
        $this->assertSame($builder, $builder->withMatchMode(MatchMode::EXACT));
    }

    public function testBuildWithDefaultMatchMode(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config = $builder
            ->withField('name')
            ->withPosition(1)
            ->build();

        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
    }

    public function testBuildWithCustomMatchMode(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config = $builder
            ->withField('name')
            ->withPosition(1)
            ->withMatchMode(MatchMode::EXACT)
            ->build();

        $this->assertEquals(MatchMode::EXACT, $config->matchMode);
    }

    public function testThrowsExceptionWhenFieldIsMissing(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name is required.');

        $builder
            ->withPosition(1)
            ->build();
    }

    public function testThrowsExceptionWhenPositionIsMissing(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position is required.');

        $builder
            ->withField('name')
            ->build();
    }

    public function testResetClearsAllValues(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $builder
            ->withField('test')
            ->withPosition(1)
            ->withMatchMode(MatchMode::EXACT)
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name is required.');

        $builder->build();
    }

    public function testResetResetsMatchModeToDefault(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $builder
            ->withField('test')
            ->withPosition(1)
            ->withMatchMode(MatchMode::EXACT)
            ->reset()
            ->withField('name')
            ->withPosition(1);

        $config = $builder->build();

        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
    }

    public function testResetReturnsBuilder(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $this->assertSame($builder, $builder->reset());
    }

    public function testCanReuseBuilderAfterReset(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config1 = $builder
            ->withField('field1')
            ->withPosition(1)
            ->build();

        $builder->reset();

        $config2 = $builder
            ->withField('field2')
            ->withPosition(2)
            ->build();

        $this->assertEquals('field1', $config1->field);
        $this->assertEquals('field2', $config2->field);
        $this->assertNotSame($config1, $config2);
    }

    public function testBuilderDelegatesValidationToValueObject(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got 0.');

        $builder
            ->withField('name')
            ->withPosition(0)
            ->build();
    }

    public function testBuilderCanBuildMultipleConfigsSequentially(): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config1 = $builder
            ->withField('name')
            ->withPosition(1)
            ->withMatchMode(MatchMode::FUZZY)
            ->build();

        $builder->reset();

        $config2 = $builder
            ->withField('description')
            ->withPosition(2)
            ->withMatchMode(MatchMode::PHRASE_PREFIX)
            ->build();

        $builder->reset();

        $config3 = $builder
            ->withField('sku')
            ->withPosition(3)
            ->withMatchMode(MatchMode::EXACT)
            ->build();

        $this->assertEquals('name', $config1->field);
        $this->assertEquals('fuzzy', $config1->jsonSerialize()['match_mode']);

        $this->assertEquals('description', $config2->field);
        $this->assertEquals('phrase_prefix', $config2->jsonSerialize()['match_mode']);

        $this->assertEquals('sku', $config3->field);
        $this->assertEquals('exact', $config3->jsonSerialize()['match_mode']);
    }

    /**
     * @dataProvider matchModeDataProvider
     */
    public function testCanBuildAllMatchModes(MatchMode $mode, string $expectedValue): void
    {
        $builder = new SearchFieldConfigBuilder();

        $config = $builder
            ->withField('test')
            ->withPosition(1)
            ->withMatchMode($mode)
            ->build();

        $this->assertEquals($expectedValue, $config->jsonSerialize()['match_mode']);
    }

    /**
     * @return array<string, array{MatchMode, string}>
     */
    public static function matchModeDataProvider(): array
    {
        return [
            'exact' => [MatchMode::EXACT, 'exact'],
            'fuzzy' => [MatchMode::FUZZY, 'fuzzy'],
            'phrase_prefix' => [MatchMode::PHRASE_PREFIX, 'phrase_prefix'],
        ];
    }

    /**
     * Test building search field configs matching typical use case.
     */
    public function testBuildingTypicalSearchFieldConfigs(): void
    {
        $builder = new SearchFieldConfigBuilder();

        // Build name field config
        $nameConfig = $builder
            ->withField('name_lt-LT')
            ->withPosition(1)
            ->withMatchMode(MatchMode::PHRASE_PREFIX)
            ->build();

        $expected = [
            'field' => 'name_lt-LT',
            'position' => 1,
            'match_mode' => 'phrase_prefix',
        ];

        $this->assertEquals($expected, $nameConfig->jsonSerialize());
    }

    public function testExceptionArgumentNameWhenFieldMissing(): void
    {
        $builder = new SearchFieldConfigBuilder();

        try {
            $builder
                ->withPosition(1)
                ->build();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('field', $e->argumentName);
            $this->assertNull($e->invalidValue);
        }
    }

    public function testExceptionArgumentNameWhenPositionMissing(): void
    {
        $builder = new SearchFieldConfigBuilder();

        try {
            $builder
                ->withField('name')
                ->build();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('position', $e->argumentName);
            $this->assertNull($e->invalidValue);
        }
    }
}
