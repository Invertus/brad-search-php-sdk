<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\BoostMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreModifier;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class FunctionScoreConfigTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $config = new FunctionScoreConfig('sales_count');

        $this->assertEquals('sales_count', $config->field);
        $this->assertEquals(FunctionScoreModifier::LOG1P, $config->modifier);
        $this->assertEquals(1.0, $config->factor);
        $this->assertEquals(1.0, $config->missing);
        $this->assertEquals(BoostMode::MULTIPLY, $config->boostMode);
        $this->assertNull($config->maxBoost);
    }

    public function testConstructorWithAllParameters(): void
    {
        $config = new FunctionScoreConfig(
            'sales_count',
            FunctionScoreModifier::SQRT,
            2.0,
            0.5,
            BoostMode::SUM,
            10.0
        );

        $this->assertEquals('sales_count', $config->field);
        $this->assertEquals(FunctionScoreModifier::SQRT, $config->modifier);
        $this->assertEquals(2.0, $config->factor);
        $this->assertEquals(0.5, $config->missing);
        $this->assertEquals(BoostMode::SUM, $config->boostMode);
        $this->assertEquals(10.0, $config->maxBoost);
    }

    public function testExtendsValueObject(): void
    {
        $config = new FunctionScoreConfig('field');
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new FunctionScoreConfig('field');
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $config = new FunctionScoreConfig('sales_count');

        $expected = [
            'field' => 'sales_count',
            'modifier' => 'log1p',
            'factor' => 1.0,
            'missing' => 1.0,
            'boost_mode' => 'multiply',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $config = new FunctionScoreConfig(
            'sales_count',
            FunctionScoreModifier::LN,
            1.5,
            0.5,
            BoostMode::AVG,
            50.0
        );

        $expected = [
            'field' => 'sales_count',
            'modifier' => 'ln',
            'factor' => 1.5,
            'missing' => 0.5,
            'boost_mode' => 'avg',
            'max_boost' => 50.0,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsNullMaxBoost(): void
    {
        $config = new FunctionScoreConfig('field');

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('max_boost', $serialized);
    }

    public function testThrowsExceptionForEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function score field cannot be empty.');

        new FunctionScoreConfig('');
    }

    public function testThrowsExceptionForFactorBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factor must be between 0.01 and 100.00, got 0.00.');

        new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 0.0);
    }

    public function testThrowsExceptionForFactorAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factor must be between 0.01 and 100.00, got 100.01.');

        new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 100.01);
    }

    public function testThrowsExceptionForNegativeMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing value must be at least 0.0, got -0.10.');

        new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, -0.1);
    }

    public function testThrowsExceptionForMaxBoostBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max boost must be between 1.0 and 1000.0, got 0.50.');

        new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, 1.0, BoostMode::MULTIPLY, 0.5);
    }

    public function testThrowsExceptionForMaxBoostAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max boost must be between 1.0 and 1000.0, got 1000.01.');

        new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, 1.0, BoostMode::MULTIPLY, 1000.01);
    }

    public function testAcceptsValidFactorBoundaries(): void
    {
        $configMin = new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 0.01);
        $this->assertEquals(0.01, $configMin->factor);

        $configMax = new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 100.0);
        $this->assertEquals(100.0, $configMax->factor);
    }

    public function testAcceptsValidMaxBoostBoundaries(): void
    {
        $configMin = new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, 1.0, BoostMode::MULTIPLY, 1.0);
        $this->assertEquals(1.0, $configMin->maxBoost);

        $configMax = new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, 1.0, BoostMode::MULTIPLY, 1000.0);
        $this->assertEquals(1000.0, $configMax->maxBoost);
    }

    public function testWithFieldReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withField('new_field');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('field', $config->field);
        $this->assertEquals('new_field', $newConfig->field);
    }

    public function testWithModifierReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withModifier(FunctionScoreModifier::SQUARE);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(FunctionScoreModifier::LOG1P, $config->modifier);
        $this->assertEquals(FunctionScoreModifier::SQUARE, $newConfig->modifier);
    }

    public function testWithFactorReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withFactor(2.5);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(1.0, $config->factor);
        $this->assertEquals(2.5, $newConfig->factor);
    }

    public function testWithMissingReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withMissing(0.5);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(1.0, $config->missing);
        $this->assertEquals(0.5, $newConfig->missing);
    }

    public function testWithBoostModeReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withBoostMode(BoostMode::REPLACE);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(BoostMode::MULTIPLY, $config->boostMode);
        $this->assertEquals(BoostMode::REPLACE, $newConfig->boostMode);
    }

    public function testWithMaxBoostReturnsNewInstance(): void
    {
        $config = new FunctionScoreConfig('field');
        $newConfig = $config->withMaxBoost(50.0);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->maxBoost);
        $this->assertEquals(50.0, $newConfig->maxBoost);
    }

    public function testAllModifiersAreValid(): void
    {
        $modifiers = [
            FunctionScoreModifier::NONE,
            FunctionScoreModifier::LOG,
            FunctionScoreModifier::LOG1P,
            FunctionScoreModifier::LOG2P,
            FunctionScoreModifier::LN,
            FunctionScoreModifier::LN1P,
            FunctionScoreModifier::LN2P,
            FunctionScoreModifier::SQUARE,
            FunctionScoreModifier::SQRT,
            FunctionScoreModifier::RECIPROCAL,
        ];

        foreach ($modifiers as $modifier) {
            $config = new FunctionScoreConfig('field', $modifier);
            $this->assertEquals($modifier, $config->modifier);
        }
    }

    public function testAllBoostModesAreValid(): void
    {
        $boostModes = [
            BoostMode::MULTIPLY,
            BoostMode::REPLACE,
            BoostMode::SUM,
            BoostMode::AVG,
            BoostMode::MAX,
            BoostMode::MIN,
        ];

        foreach ($boostModes as $boostMode) {
            $config = new FunctionScoreConfig('field', FunctionScoreModifier::LOG1P, 1.0, 1.0, $boostMode);
            $this->assertEquals($boostMode, $config->boostMode);
        }
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new FunctionScoreConfig('');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('field', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }
}
