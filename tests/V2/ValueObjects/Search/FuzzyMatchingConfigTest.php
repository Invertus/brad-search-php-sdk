<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Search\FuzzyMatchingConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\FuzzyMode;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class FuzzyMatchingConfigTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->assertTrue($config->enabled);
        $this->assertEquals(FuzzyMode::AUTO, $config->mode);
        $this->assertEquals(2, $config->minSimilarity);
    }

    public function testConstructorWithCustomValues(): void
    {
        $config = new FuzzyMatchingConfig(false, FuzzyMode::FIXED, 1);

        $this->assertFalse($config->enabled);
        $this->assertEquals(FuzzyMode::FIXED, $config->mode);
        $this->assertEquals(1, $config->minSimilarity);
    }

    public function testThrowsExceptionForMinSimilarityBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum similarity must be between 0 and 2, got -1.');

        new FuzzyMatchingConfig(true, FuzzyMode::AUTO, -1);
    }

    public function testThrowsExceptionForMinSimilarityAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum similarity must be between 0 and 2, got 3.');

        new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 3);
    }

    public function testAcceptsMinimumMinSimilarity(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 0);

        $this->assertEquals(0, $config->minSimilarity);
    }

    public function testAcceptsMaximumMinSimilarity(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2);

        $this->assertEquals(2, $config->minSimilarity);
    }

    public function testExtendsValueObject(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $config = new FuzzyMatchingConfig(false, FuzzyMode::FIXED, 1);

        $expected = [
            'enabled' => false,
            'mode' => 'fixed',
            'min_similarity' => 1,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithDefaultValues(): void
    {
        $config = new FuzzyMatchingConfig();

        $serialized = $config->jsonSerialize();

        $this->assertTrue($serialized['enabled']);
        $this->assertEquals('auto', $serialized['mode']);
        $this->assertEquals(2, $serialized['min_similarity']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithEnabledReturnsNewInstance(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2);
        $newConfig = $config->withEnabled(false);

        $this->assertNotSame($config, $newConfig);
        $this->assertTrue($config->enabled);
        $this->assertFalse($newConfig->enabled);
        $this->assertEquals($config->mode, $newConfig->mode);
        $this->assertEquals($config->minSimilarity, $newConfig->minSimilarity);
    }

    public function testWithModeReturnsNewInstance(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2);
        $newConfig = $config->withMode(FuzzyMode::FIXED);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(FuzzyMode::AUTO, $config->mode);
        $this->assertEquals(FuzzyMode::FIXED, $newConfig->mode);
        $this->assertEquals($config->enabled, $newConfig->enabled);
        $this->assertEquals($config->minSimilarity, $newConfig->minSimilarity);
    }

    public function testWithMinSimilarityReturnsNewInstance(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, 2);
        $newConfig = $config->withMinSimilarity(0);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(2, $config->minSimilarity);
        $this->assertEquals(0, $newConfig->minSimilarity);
        $this->assertEquals($config->enabled, $newConfig->enabled);
        $this->assertEquals($config->mode, $newConfig->mode);
    }

    public function testWithMinSimilarityValidatesNewValue(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum similarity must be between 0 and 2, got 5.');

        $config->withMinSimilarity(5);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $config = new FuzzyMatchingConfig(false, FuzzyMode::FIXED, 1);

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertFalse($decoded['enabled']);
        $this->assertEquals('fixed', $decoded['mode']);
        $this->assertEquals(1, $decoded['min_similarity']);
    }

    /**
     * @dataProvider fuzzyModeDataProvider
     */
    public function testSupportsAllFuzzyModes(FuzzyMode $mode, string $expectedValue): void
    {
        $config = new FuzzyMatchingConfig(true, $mode, 1);

        $this->assertEquals($mode, $config->mode);
        $this->assertEquals($expectedValue, $config->jsonSerialize()['mode']);
    }

    /**
     * @return array<string, array{FuzzyMode, string}>
     */
    public static function fuzzyModeDataProvider(): array
    {
        return [
            'auto' => [FuzzyMode::AUTO, 'auto'],
            'fixed' => [FuzzyMode::FIXED, 'fixed'],
        ];
    }

    /**
     * @dataProvider validMinSimilarityDataProvider
     */
    public function testAcceptsValidMinSimilarityValues(int $minSimilarity): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::AUTO, $minSimilarity);

        $this->assertEquals($minSimilarity, $config->minSimilarity);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validMinSimilarityDataProvider(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'two' => [2],
        ];
    }

    /**
     * @dataProvider invalidMinSimilarityDataProvider
     */
    public function testRejectsInvalidMinSimilarityValues(int $minSimilarity): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FuzzyMatchingConfig(true, FuzzyMode::AUTO, $minSimilarity);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidMinSimilarityDataProvider(): array
    {
        return [
            'negative_one' => [-1],
            'negative_ten' => [-10],
            'three' => [3],
            'ten' => [10],
            'hundred' => [100],
        ];
    }

    /**
     * Test output matches OpenAPI FuzzyMatchingConfig schema structure.
     */
    public function testMatchesFuzzyMatchingConfigSchema(): void
    {
        $config = new FuzzyMatchingConfig(true, FuzzyMode::FIXED, 1);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('enabled', $serialized);
        $this->assertArrayHasKey('mode', $serialized);
        $this->assertArrayHasKey('min_similarity', $serialized);

        $this->assertIsBool($serialized['enabled']);
        $this->assertIsString($serialized['mode']);
        $this->assertIsInt($serialized['min_similarity']);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new FuzzyMatchingConfig(true, FuzzyMode::AUTO, -5);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('min_similarity', $e->argumentName);
            $this->assertEquals(-5, $e->invalidValue);
        }
    }

    public function testChainedWithMethods(): void
    {
        $config = new FuzzyMatchingConfig()
            ->withEnabled(false)
            ->withMode(FuzzyMode::FIXED)
            ->withMinSimilarity(0);

        $this->assertFalse($config->enabled);
        $this->assertEquals(FuzzyMode::FIXED, $config->mode);
        $this->assertEquals(0, $config->minSimilarity);
    }

    public function testDefaultValuesMatchAcceptanceCriteria(): void
    {
        $config = new FuzzyMatchingConfig();

        $this->assertTrue($config->enabled, 'Default enabled should be true');
        $this->assertEquals(FuzzyMode::AUTO, $config->mode, 'Default mode should be auto');
        $this->assertEquals(2, $config->minSimilarity, 'Default min_similarity should be 2');
    }
}
