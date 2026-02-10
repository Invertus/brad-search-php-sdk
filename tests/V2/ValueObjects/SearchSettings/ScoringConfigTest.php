<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoringConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ScoringConfigTest extends TestCase
{
    public function testConstructorWithNoParameters(): void
    {
        $config = new ScoringConfig();

        $this->assertNull($config->functionScore);
        $this->assertNull($config->minScore);
    }

    public function testConstructorWithAllParameters(): void
    {
        $functionScore = new FunctionScoreConfig('sales_count');

        $config = new ScoringConfig($functionScore, 0.5);

        $this->assertSame($functionScore, $config->functionScore);
        $this->assertEquals(0.5, $config->minScore);
    }

    public function testExtendsValueObject(): void
    {
        $config = new ScoringConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new ScoringConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithEmptyConfig(): void
    {
        $config = new ScoringConfig();

        $this->assertEquals([], $config->jsonSerialize());
    }

    public function testJsonSerializeWithFunctionScoreOnly(): void
    {
        $functionScore = new FunctionScoreConfig('sales_count');
        $config = new ScoringConfig($functionScore);

        $expected = [
            'function_score' => [
                'field' => 'sales_count',
                'modifier' => 'log1p',
                'factor' => 1.0,
                'missing' => 1.0,
                'boost_mode' => 'multiply',
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithMinScoreOnly(): void
    {
        $config = new ScoringConfig(null, 0.3);

        $expected = [
            'min_score' => 0.3,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $functionScore = new FunctionScoreConfig('sales_count');
        $config = new ScoringConfig($functionScore, 0.5);

        $expected = [
            'function_score' => [
                'field' => 'sales_count',
                'modifier' => 'log1p',
                'factor' => 1.0,
                'missing' => 1.0,
                'boost_mode' => 'multiply',
            ],
            'min_score' => 0.5,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testThrowsExceptionForMinScoreBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0, got -0.10.');

        new ScoringConfig(null, -0.1);
    }

    public function testThrowsExceptionForMinScoreAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum score must be between 0.0 and 1.0, got 1.10.');

        new ScoringConfig(null, 1.1);
    }

    public function testAcceptsValidMinScoreBoundaries(): void
    {
        $configMin = new ScoringConfig(null, 0.0);
        $this->assertEquals(0.0, $configMin->minScore);

        $configMax = new ScoringConfig(null, 1.0);
        $this->assertEquals(1.0, $configMax->minScore);
    }

    public function testWithFunctionScoreReturnsNewInstance(): void
    {
        $config = new ScoringConfig();
        $functionScore = new FunctionScoreConfig('field');
        $newConfig = $config->withFunctionScore($functionScore);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->functionScore);
        $this->assertSame($functionScore, $newConfig->functionScore);
    }

    public function testWithMinScoreReturnsNewInstance(): void
    {
        $config = new ScoringConfig();
        $newConfig = $config->withMinScore(0.75);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->minScore);
        $this->assertEquals(0.75, $newConfig->minScore);
    }

    public function testWithMinScoreCanSetToNull(): void
    {
        $config = new ScoringConfig(null, 0.5);
        $newConfig = $config->withMinScore(null);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(0.5, $config->minScore);
        $this->assertNull($newConfig->minScore);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new ScoringConfig(null, -0.5);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('min_score', $e->argumentName);
            $this->assertEquals(-0.5, $e->invalidValue);
        }
    }

    /**
     * @dataProvider validMinScoreDataProvider
     */
    public function testAcceptsValidMinScores(float $minScore): void
    {
        $config = new ScoringConfig(null, $minScore);
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

        new ScoringConfig(null, $minScore);
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
}
