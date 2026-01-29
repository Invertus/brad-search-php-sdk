<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class MultiMatchConfigTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $config = new MultiMatchConfig('multi_name', ['field1', 'field2']);

        $this->assertEquals('multi_name', $config->id);
        $this->assertEquals(['field1', 'field2'], $config->fieldIds);
        $this->assertEquals(MultiMatchType::BEST_FIELDS, $config->type);
        $this->assertNull($config->operator);
        $this->assertNull($config->boost);
    }

    public function testConstructorWithAllParameters(): void
    {
        $config = new MultiMatchConfig(
            'multi_name',
            ['field1', 'field2'],
            MultiMatchType::CROSS_FIELDS,
            'and',
            2.5
        );

        $this->assertEquals('multi_name', $config->id);
        $this->assertEquals(['field1', 'field2'], $config->fieldIds);
        $this->assertEquals(MultiMatchType::CROSS_FIELDS, $config->type);
        $this->assertEquals('and', $config->operator);
        $this->assertEquals(2.5, $config->boost);
    }

    public function testExtendsValueObject(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $config = new MultiMatchConfig('multi_name', ['field1', 'field2']);

        $expected = [
            'id' => 'multi_name',
            'field_ids' => ['field1', 'field2'],
            'type' => 'best_fields',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $config = new MultiMatchConfig(
            'multi_name',
            ['field1', 'field2'],
            MultiMatchType::PHRASE,
            'or',
            3.0
        );

        $expected = [
            'id' => 'multi_name',
            'field_ids' => ['field1', 'field2'],
            'type' => 'phrase',
            'operator' => 'or',
            'boost' => 3.0,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsNullValues(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('operator', $serialized);
        $this->assertArrayNotHasKey('boost', $serialized);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multi-match config id cannot be empty.');

        new MultiMatchConfig('', ['field1']);
    }

    public function testThrowsExceptionForEmptyFieldIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one field ID is required for multi-match config.');

        new MultiMatchConfig('id', []);
    }

    public function testThrowsExceptionForNonStringFieldId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field ID at index 1 must be a string.');

        new MultiMatchConfig('id', ['valid', 123]);
    }

    public function testThrowsExceptionForEmptyFieldId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field ID at index 1 cannot be empty.');

        new MultiMatchConfig('id', ['valid', '']);
    }

    public function testThrowsExceptionForBoostBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost must be between 0.01 and 100.00, got 0.00.');

        new MultiMatchConfig('id', ['field1'], MultiMatchType::BEST_FIELDS, null, 0.0);
    }

    public function testThrowsExceptionForBoostAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boost must be between 0.01 and 100.00, got 100.01.');

        new MultiMatchConfig('id', ['field1'], MultiMatchType::BEST_FIELDS, null, 100.01);
    }

    public function testWithIdReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withId('new_id');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('id', $config->id);
        $this->assertEquals('new_id', $newConfig->id);
    }

    public function testWithFieldIdsReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withFieldIds(['field2', 'field3']);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['field1'], $config->fieldIds);
        $this->assertEquals(['field2', 'field3'], $newConfig->fieldIds);
    }

    public function testWithAddedFieldIdReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withAddedFieldId('field2');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['field1'], $config->fieldIds);
        $this->assertEquals(['field1', 'field2'], $newConfig->fieldIds);
    }

    public function testWithTypeReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withType(MultiMatchType::PHRASE_PREFIX);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(MultiMatchType::BEST_FIELDS, $config->type);
        $this->assertEquals(MultiMatchType::PHRASE_PREFIX, $newConfig->type);
    }

    public function testWithOperatorReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withOperator('and');

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->operator);
        $this->assertEquals('and', $newConfig->operator);
    }

    public function testWithBoostReturnsNewInstance(): void
    {
        $config = new MultiMatchConfig('id', ['field1']);
        $newConfig = $config->withBoost(2.5);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->boost);
        $this->assertEquals(2.5, $newConfig->boost);
    }

    public function testAllMultiMatchTypesAreValid(): void
    {
        $types = [
            MultiMatchType::BEST_FIELDS,
            MultiMatchType::MOST_FIELDS,
            MultiMatchType::CROSS_FIELDS,
            MultiMatchType::PHRASE,
            MultiMatchType::PHRASE_PREFIX,
            MultiMatchType::BOOL_PREFIX,
        ];

        foreach ($types as $type) {
            $config = new MultiMatchConfig('id', ['field1'], $type);
            $this->assertEquals($type, $config->type);
        }
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new MultiMatchConfig('id', []);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('field_ids', $e->argumentName);
            $this->assertEquals([], $e->invalidValue);
        }
    }
}
