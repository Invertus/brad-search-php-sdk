<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class PopularityBoostConfigTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $config = new PopularityBoostConfig(true, 'sales_count');

        $this->assertTrue($config->enabled);
        $this->assertEquals('sales_count', $config->field);
        $this->assertEquals(BoostAlgorithm::LOGARITHMIC, $config->algorithm);
        $this->assertEquals(2.0, $config->maxBoost);
    }

    public function testConstructorWithCustomValues(): void
    {
        $config = new PopularityBoostConfig(false, 'popularity', BoostAlgorithm::LINEAR, 5.0);

        $this->assertFalse($config->enabled);
        $this->assertEquals('popularity', $config->field);
        $this->assertEquals(BoostAlgorithm::LINEAR, $config->algorithm);
        $this->assertEquals(5.0, $config->maxBoost);
    }

    public function testThrowsExceptionForMaxBoostBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max boost must be between 1.0 and 10.0, got 0.5.');

        new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 0.5);
    }

    public function testThrowsExceptionForMaxBoostAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max boost must be between 1.0 and 10.0, got 15.0.');

        new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 15.0);
    }

    public function testAcceptsMinimumMaxBoost(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 1.0);

        $this->assertEquals(1.0, $config->maxBoost);
    }

    public function testAcceptsMaximumMaxBoost(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 10.0);

        $this->assertEquals(10.0, $config->maxBoost);
    }

    public function testExtendsValueObject(): void
    {
        $config = new PopularityBoostConfig(true, 'field');

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new PopularityBoostConfig(true, 'field');

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $config = new PopularityBoostConfig(false, 'popularity', BoostAlgorithm::LINEAR, 5.0);

        $expected = [
            'enabled' => false,
            'field' => 'popularity',
            'algorithm' => 'linear',
            'max_boost' => 5.0,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithDefaultValues(): void
    {
        $config = new PopularityBoostConfig(true, 'sales_count');

        $serialized = $config->jsonSerialize();

        $this->assertTrue($serialized['enabled']);
        $this->assertEquals('sales_count', $serialized['field']);
        $this->assertEquals('logarithmic', $serialized['algorithm']);
        $this->assertEquals(2.0, $serialized['max_boost']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new PopularityBoostConfig(true, 'field');

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithEnabledReturnsNewInstance(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 2.0);
        $newConfig = $config->withEnabled(false);

        $this->assertNotSame($config, $newConfig);
        $this->assertTrue($config->enabled);
        $this->assertFalse($newConfig->enabled);
        $this->assertEquals($config->field, $newConfig->field);
        $this->assertEquals($config->algorithm, $newConfig->algorithm);
        $this->assertEquals($config->maxBoost, $newConfig->maxBoost);
    }

    public function testWithFieldReturnsNewInstance(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 2.0);
        $newConfig = $config->withField('new_field');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('field', $config->field);
        $this->assertEquals('new_field', $newConfig->field);
        $this->assertEquals($config->enabled, $newConfig->enabled);
        $this->assertEquals($config->algorithm, $newConfig->algorithm);
        $this->assertEquals($config->maxBoost, $newConfig->maxBoost);
    }

    public function testWithAlgorithmReturnsNewInstance(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 2.0);
        $newConfig = $config->withAlgorithm(BoostAlgorithm::SQUARE_ROOT);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(BoostAlgorithm::LOGARITHMIC, $config->algorithm);
        $this->assertEquals(BoostAlgorithm::SQUARE_ROOT, $newConfig->algorithm);
        $this->assertEquals($config->enabled, $newConfig->enabled);
        $this->assertEquals($config->field, $newConfig->field);
        $this->assertEquals($config->maxBoost, $newConfig->maxBoost);
    }

    public function testWithMaxBoostReturnsNewInstance(): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, 2.0);
        $newConfig = $config->withMaxBoost(5.0);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(2.0, $config->maxBoost);
        $this->assertEquals(5.0, $newConfig->maxBoost);
        $this->assertEquals($config->enabled, $newConfig->enabled);
        $this->assertEquals($config->field, $newConfig->field);
        $this->assertEquals($config->algorithm, $newConfig->algorithm);
    }

    public function testWithMaxBoostValidatesNewValue(): void
    {
        $config = new PopularityBoostConfig(true, 'field');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max boost must be between 1.0 and 10.0, got 20.0.');

        $config->withMaxBoost(20.0);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $config = new PopularityBoostConfig(false, 'popularity', BoostAlgorithm::LINEAR, 3.5);

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertFalse($decoded['enabled']);
        $this->assertEquals('popularity', $decoded['field']);
        $this->assertEquals('linear', $decoded['algorithm']);
        $this->assertEquals(3.5, $decoded['max_boost']);
    }

    /**
     * @dataProvider boostAlgorithmDataProvider
     */
    public function testSupportsAllBoostAlgorithms(BoostAlgorithm $algorithm, string $expectedValue): void
    {
        $config = new PopularityBoostConfig(true, 'field', $algorithm, 2.0);

        $this->assertEquals($algorithm, $config->algorithm);
        $this->assertEquals($expectedValue, $config->jsonSerialize()['algorithm']);
    }

    /**
     * @return array<string, array{BoostAlgorithm, string}>
     */
    public static function boostAlgorithmDataProvider(): array
    {
        return [
            'logarithmic' => [BoostAlgorithm::LOGARITHMIC, 'logarithmic'],
            'linear' => [BoostAlgorithm::LINEAR, 'linear'],
            'square_root' => [BoostAlgorithm::SQUARE_ROOT, 'square_root'],
        ];
    }

    /**
     * @dataProvider validMaxBoostDataProvider
     */
    public function testAcceptsValidMaxBoostValues(float $maxBoost): void
    {
        $config = new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, $maxBoost);

        $this->assertEquals($maxBoost, $config->maxBoost);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validMaxBoostDataProvider(): array
    {
        return [
            'minimum' => [1.0],
            'default' => [2.0],
            'middle' => [5.5],
            'maximum' => [10.0],
        ];
    }

    /**
     * @dataProvider invalidMaxBoostDataProvider
     */
    public function testRejectsInvalidMaxBoostValues(float $maxBoost): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, $maxBoost);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalidMaxBoostDataProvider(): array
    {
        return [
            'zero' => [0.0],
            'negative' => [-1.0],
            'just_below_min' => [0.9],
            'just_above_max' => [10.1],
            'way_above_max' => [100.0],
        ];
    }

    /**
     * Test output matches OpenAPI PopularityBoostConfig schema structure.
     */
    public function testMatchesPopularityBoostConfigSchema(): void
    {
        $config = new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LINEAR, 3.0);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('enabled', $serialized);
        $this->assertArrayHasKey('field', $serialized);
        $this->assertArrayHasKey('algorithm', $serialized);
        $this->assertArrayHasKey('max_boost', $serialized);

        $this->assertIsBool($serialized['enabled']);
        $this->assertIsString($serialized['field']);
        $this->assertIsString($serialized['algorithm']);
        $this->assertIsFloat($serialized['max_boost']);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new PopularityBoostConfig(true, 'field', BoostAlgorithm::LOGARITHMIC, -5.0);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('max_boost', $e->argumentName);
            $this->assertEquals(-5.0, $e->invalidValue);
        }
    }

    public function testChainedWithMethods(): void
    {
        $config = (new PopularityBoostConfig(true, 'field'))
            ->withEnabled(false)
            ->withField('new_field')
            ->withAlgorithm(BoostAlgorithm::SQUARE_ROOT)
            ->withMaxBoost(7.5);

        $this->assertFalse($config->enabled);
        $this->assertEquals('new_field', $config->field);
        $this->assertEquals(BoostAlgorithm::SQUARE_ROOT, $config->algorithm);
        $this->assertEquals(7.5, $config->maxBoost);
    }

    public function testDefaultValuesMatchAcceptanceCriteria(): void
    {
        $config = new PopularityBoostConfig(true, 'field');

        $this->assertEquals(
            BoostAlgorithm::LOGARITHMIC,
            $config->algorithm,
            'Default algorithm should be logarithmic'
        );
        $this->assertEquals(2.0, $config->maxBoost, 'Default max_boost should be 2.0');
    }
}
