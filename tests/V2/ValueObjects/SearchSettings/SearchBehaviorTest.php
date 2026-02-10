<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehavior;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehaviorType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SearchBehaviorTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);

        $this->assertEquals(SearchBehaviorType::FUZZY, $behavior->type);
        $this->assertNull($behavior->subfield);
        $this->assertNull($behavior->operator);
        $this->assertNull($behavior->boost);
        $this->assertNull($behavior->fuzziness);
        $this->assertNull($behavior->prefixLength);
    }

    public function testConstructorWithAllParameters(): void
    {
        $behavior = new SearchBehavior(
            SearchBehaviorType::FUZZY,
            'keyword',
            'and',
            2.0,
            1,
            2
        );

        $this->assertEquals(SearchBehaviorType::FUZZY, $behavior->type);
        $this->assertEquals('keyword', $behavior->subfield);
        $this->assertEquals('and', $behavior->operator);
        $this->assertEquals(2.0, $behavior->boost);
        $this->assertEquals(1, $behavior->fuzziness);
        $this->assertEquals(2, $behavior->prefixLength);
    }

    public function testExtendsValueObject(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::EXACT);
        $this->assertInstanceOf(ValueObject::class, $behavior);
    }

    public function testImplementsJsonSerializable(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::EXACT);
        $this->assertInstanceOf(JsonSerializable::class, $behavior);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::EXACT);

        $expected = [
            'type' => 'exact',
        ];

        $this->assertEquals($expected, $behavior->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $behavior = new SearchBehavior(
            SearchBehaviorType::FUZZY,
            'keyword',
            'and',
            2.5,
            1,
            3
        );

        $expected = [
            'type' => 'fuzzy',
            'subfield' => 'keyword',
            'operator' => 'and',
            'boost' => 2.5,
            'fuzziness' => 1,
            'prefix_length' => 3,
        ];

        $this->assertEquals($expected, $behavior->jsonSerialize());
    }

    public function testJsonSerializeOmitsNullValues(): void
    {
        $behavior = new SearchBehavior(
            SearchBehaviorType::MATCH,
            null,
            'or',
            null,
            null,
            null
        );

        $serialized = $behavior->jsonSerialize();

        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('operator', $serialized);
        $this->assertArrayNotHasKey('subfield', $serialized);
        $this->assertArrayNotHasKey('boost', $serialized);
        $this->assertArrayNotHasKey('fuzziness', $serialized);
        $this->assertArrayNotHasKey('prefix_length', $serialized);
    }

    public function testThrowsExceptionForBoostBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost must be between 0.01 and 100.00, got 0.00.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, 0.0);
    }

    public function testThrowsExceptionForBoostAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost must be between 0.01 and 100.00, got 100.01.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, 100.01);
    }

    public function testThrowsExceptionForFuzzinessBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fuzziness must be between 0 and 2, got -1.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, -1);
    }

    public function testThrowsExceptionForFuzzinessAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fuzziness must be between 0 and 2, got 3.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, 3);
    }

    public function testThrowsExceptionForPrefixLengthBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix length must be between 0 and 10, got -1.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, null, -1);
    }

    public function testThrowsExceptionForPrefixLengthAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix length must be between 0 and 10, got 11.');

        new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, null, 11);
    }

    public function testAcceptsValidBoostBoundaries(): void
    {
        $behaviorMin = new SearchBehavior(SearchBehaviorType::FUZZY, null, null, 0.01);
        $this->assertEquals(0.01, $behaviorMin->boost);

        $behaviorMax = new SearchBehavior(SearchBehaviorType::FUZZY, null, null, 100.0);
        $this->assertEquals(100.0, $behaviorMax->boost);
    }

    public function testAcceptsValidFuzzinessBoundaries(): void
    {
        $behaviorMin = new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, 0);
        $this->assertEquals(0, $behaviorMin->fuzziness);

        $behaviorMax = new SearchBehavior(SearchBehaviorType::FUZZY, null, null, null, 2);
        $this->assertEquals(2, $behaviorMax->fuzziness);
    }

    public function testWithTypeReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withType(SearchBehaviorType::EXACT);

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertEquals(SearchBehaviorType::FUZZY, $behavior->type);
        $this->assertEquals(SearchBehaviorType::EXACT, $newBehavior->type);
    }

    public function testWithSubfieldReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withSubfield('keyword');

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertNull($behavior->subfield);
        $this->assertEquals('keyword', $newBehavior->subfield);
    }

    public function testWithOperatorReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withOperator('and');

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertNull($behavior->operator);
        $this->assertEquals('and', $newBehavior->operator);
    }

    public function testWithBoostReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withBoost(2.5);

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertNull($behavior->boost);
        $this->assertEquals(2.5, $newBehavior->boost);
    }

    public function testWithFuzzinessReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withFuzziness(1);

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertNull($behavior->fuzziness);
        $this->assertEquals(1, $newBehavior->fuzziness);
    }

    public function testWithPrefixLengthReturnsNewInstance(): void
    {
        $behavior = new SearchBehavior(SearchBehaviorType::FUZZY);
        $newBehavior = $behavior->withPrefixLength(3);

        $this->assertNotSame($behavior, $newBehavior);
        $this->assertNull($behavior->prefixLength);
        $this->assertEquals(3, $newBehavior->prefixLength);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new SearchBehavior(SearchBehaviorType::FUZZY, null, null, -1.0);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('boost', $e->argumentName);
            $this->assertEquals(-1.0, $e->invalidValue);
        }
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $behavior = new SearchBehavior(
            SearchBehaviorType::FUZZY,
            'keyword',
            'and',
            2.0,
            1,
            2
        );

        $this->assertEquals($behavior->jsonSerialize(), $behavior->toArray());
    }
}
