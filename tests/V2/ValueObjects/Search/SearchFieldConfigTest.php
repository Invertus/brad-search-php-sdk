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
        $config = new SearchFieldConfig('name', 1, MatchMode::EXACT);

        $this->assertEquals('name', $config->field);
        $this->assertEquals(1, $config->position);
        $this->assertEquals(MatchMode::EXACT, $config->matchMode);
    }

    public function testConstructorWithDefaultMatchMode(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
    }

    public function testThrowsExceptionForEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new SearchFieldConfig('', 1);
    }

    public function testThrowsExceptionForPositionLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got 0.');

        new SearchFieldConfig('name', 0);
    }

    public function testThrowsExceptionForNegativePosition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got -5.');

        new SearchFieldConfig('name', -5);
    }

    public function testAcceptsMinimumPosition(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->assertEquals(1, $config->position);
    }

    public function testExtendsValueObject(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $config = new SearchFieldConfig('name', 1, MatchMode::PHRASE_PREFIX);

        $expected = [
            'field' => 'name',
            'position' => 1,
            'match_mode' => 'phrase_prefix',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithDefaultMatchMode(): void
    {
        $config = new SearchFieldConfig('title', 2);

        $serialized = $config->jsonSerialize();

        $this->assertEquals('fuzzy', $serialized['match_mode']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithFieldReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, MatchMode::EXACT);
        $newConfig = $config->withField('title');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('name', $config->field);
        $this->assertEquals('title', $newConfig->field);
        $this->assertEquals($config->position, $newConfig->position);
        $this->assertEquals($config->matchMode, $newConfig->matchMode);
    }

    public function testWithPositionReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1);
        $newConfig = $config->withPosition(5);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(1, $config->position);
        $this->assertEquals(5, $newConfig->position);
        $this->assertEquals($config->field, $newConfig->field);
    }

    public function testWithMatchModeReturnsNewInstance(): void
    {
        $config = new SearchFieldConfig('name', 1, MatchMode::FUZZY);
        $newConfig = $config->withMatchMode(MatchMode::EXACT);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(MatchMode::FUZZY, $config->matchMode);
        $this->assertEquals(MatchMode::EXACT, $newConfig->matchMode);
        $this->assertEquals($config->field, $newConfig->field);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $config = new SearchFieldConfig('name', 1, MatchMode::EXACT);

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertEquals('name', $decoded['field']);
        $this->assertEquals(1, $decoded['position']);
        $this->assertEquals('exact', $decoded['match_mode']);
    }

    /**
     * @dataProvider matchModeDataProvider
     */
    public function testSupportsAllMatchModes(MatchMode $mode, string $expectedValue): void
    {
        $config = new SearchFieldConfig('name', 1, $mode);

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
     * Test output matches OpenAPI SearchFieldConfigV2 schema structure.
     */
    public function testMatchesSearchFieldConfigV2Schema(): void
    {
        $config = new SearchFieldConfig('name_lt-LT', 1, MatchMode::PHRASE_PREFIX);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('field', $serialized);
        $this->assertArrayHasKey('position', $serialized);
        $this->assertArrayHasKey('match_mode', $serialized);

        $this->assertIsString($serialized['field']);
        $this->assertIsInt($serialized['position']);
        $this->assertIsString($serialized['match_mode']);
    }

    public function testWithFieldValidatesNewField(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        $config->withField('');
    }

    public function testWithPositionValidatesNewPosition(): void
    {
        $config = new SearchFieldConfig('name', 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be at least 1, got 0.');

        $config->withPosition(0);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new SearchFieldConfig('', 1);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('field', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testExceptionContainsInvalidValue(): void
    {
        try {
            new SearchFieldConfig('name', 0);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('position', $e->argumentName);
            $this->assertEquals(0, $e->invalidValue);
        }
    }
}
