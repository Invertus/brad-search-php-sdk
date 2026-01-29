<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SearchFieldConfigTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.5, MatchMode::EXACT);

        $this->assertEquals('name', $config->field);
        $this->assertEquals(1, $config->position);
        $this->assertEquals(1.5, $config->boostMultiplier);
        $this->assertEquals(MatchMode::EXACT, $config->matchMode);
    }

    public function testConstructorWithDefaultMatchMode(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
    }

    public function testThrowsExceptionForEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new SearchFieldConfig('', 1, 1.0);
    }

    public function testThrowsExceptionForPositionLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got 0.');

        new SearchFieldConfig('name', 0, 1.0);
    }

    public function testThrowsExceptionForNegativePosition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got -5.');

        new SearchFieldConfig('name', -5, 1.0);
    }

    public function testThrowsExceptionForBoostMultiplierBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost multiplier must be between 0.01 and 100.00, got 0.00.');

        new SearchFieldConfig('name', 1, 0.0);
    }

    public function testThrowsExceptionForBoostMultiplierAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost multiplier must be between 0.01 and 100.00, got 100.01.');

        new SearchFieldConfig('name', 1, 100.01);
    }

    public function testAcceptsMinimumBoostMultiplier(): void
    {
        $config = new SearchFieldConfig('name', 1, 0.01);

        $this->assertEquals(0.01, $config->boostMultiplier);
    }

    public function testAcceptsMaximumBoostMultiplier(): void
    {
        $config = new SearchFieldConfig('name', 1, 100.0);

        $this->assertEquals(100.0, $config->boostMultiplier);
    }

    public function testAcceptsMinimumPosition(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->assertEquals(1, $config->position);
    }

    public function testExtendsValueObject(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $config = new SearchFieldConfig('name', 1, 2.5, MatchMode::PHRASE_PREFIX);

        $expected = [
            'field' => 'name',
            'position' => 1,
            'boost_multiplier' => 2.5,
            'match_mode' => 'phrase_prefix',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithDefaultMatchMode(): void
    {
        $config = new SearchFieldConfig('title', 2, 1.0);

        $serialized = $config->jsonSerialize();

        $this->assertEquals('fuzzy', $serialized['match_mode']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithFieldReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0, MatchMode::EXACT);
        $newConfig = $config->withField('title');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('name', $config->field);
        $this->assertEquals('title', $newConfig->field);
        $this->assertEquals($config->position, $newConfig->position);
        $this->assertEquals($config->boostMultiplier, $newConfig->boostMultiplier);
        $this->assertEquals($config->matchMode, $newConfig->matchMode);
    }

    public function testWithPositionReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);
        $newConfig = $config->withPosition(5);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(1, $config->position);
        $this->assertEquals(5, $newConfig->position);
        $this->assertEquals($config->field, $newConfig->field);
    }

    public function testWithBoostMultiplierReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);
        $newConfig = $config->withBoostMultiplier(5.5);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(1.0, $config->boostMultiplier);
        $this->assertEquals(5.5, $newConfig->boostMultiplier);
        $this->assertEquals($config->field, $newConfig->field);
    }

    public function testWithMatchModeReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0, MatchMode::FUZZY);
        $newConfig = $config->withMatchMode(MatchMode::EXACT);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
        $this->assertEquals(MatchMode::EXACT, $newConfig->matchMode);
        $this->assertEquals($config->field, $newConfig->field);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.5, MatchMode::EXACT);

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertEquals('name', $decoded['field']);
        $this->assertEquals(1, $decoded['position']);
        $this->assertEquals(1.5, $decoded['boost_multiplier']);
        $this->assertEquals('exact', $decoded['match_mode']);
    }

    /**
     * @dataProvider matchModeDataProvider
     */
    public function testSupportsAllMatchModes(MatchMode $mode, string $expectedValue): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0, $mode);

        $this->assertEquals($mode, $config->matchMode);
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
     * @dataProvider validBoostMultiplierDataProvider
     */
    public function testAcceptsValidBoostMultipliers(float $boostMultiplier): void
    {
        $config = new SearchFieldConfig('name', 1, $boostMultiplier);

        $this->assertEquals($boostMultiplier, $config->boostMultiplier);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validBoostMultiplierDataProvider(): array
    {
        return [
            'minimum' => [0.01],
            'low' => [0.5],
            'one' => [1.0],
            'medium' => [10.0],
            'high' => [50.0],
            'maximum' => [100.0],
        ];
    }

    /**
     * @dataProvider invalidBoostMultiplierDataProvider
     */
    public function testRejectsInvalidBoostMultipliers(float $boostMultiplier): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchFieldConfig('name', 1, $boostMultiplier);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalidBoostMultiplierDataProvider(): array
    {
        return [
            'zero' => [0.0],
            'negative' => [-1.0],
            'too_small' => [0.001],
            'above_max' => [100.01],
            'way_above_max' => [200.0],
        ];
    }

    /**
     * Test output matches OpenAPI SearchFieldConfigV2 schema structure.
     */
    public function testMatchesSearchFieldConfigV2Schema(): void
    {
        $config = new SearchFieldConfig('name_lt-LT', 1, 2.0, MatchMode::PHRASE_PREFIX);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('field', $serialized);
        $this->assertArrayHasKey('position', $serialized);
        $this->assertArrayHasKey('boost_multiplier', $serialized);
        $this->assertArrayHasKey('match_mode', $serialized);

        $this->assertIsString($serialized['field']);
        $this->assertIsInt($serialized['position']);
        $this->assertIsFloat($serialized['boost_multiplier']);
        $this->assertIsString($serialized['match_mode']);
    }

    public function testWithFieldValidatesNewField(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        $config->withField('');
    }

    public function testWithPositionValidatesNewPosition(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got 0.');

        $config->withPosition(0);
    }

    public function testWithBoostMultiplierValidatesNewBoostMultiplier(): void
    {
        $config = new SearchFieldConfig('name', 1, 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost multiplier must be between 0.01 and 100.00, got 150.00.');

        $config->withBoostMultiplier(150.0);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new SearchFieldConfig('', 1, 1.0);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('field', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testExceptionContainsInvalidValue(): void
    {
        try {
            new SearchFieldConfig('name', 0, 1.0);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('position', $e->argumentName);
            $this->assertEquals(0, $e->invalidValue);
        }
    }
}
