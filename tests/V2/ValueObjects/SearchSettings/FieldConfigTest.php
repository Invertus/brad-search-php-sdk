<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehavior;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehaviorType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class FieldConfigTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $config = new FieldConfig('name_field', 'name');

        $this->assertEquals('name_field', $config->id);
        $this->assertEquals('name', $config->fieldName);
        $this->assertNull($config->localeSuffix);
        $this->assertEquals([], $config->searchBehaviors);
    }

    public function testConstructorWithAllParameters(): void
    {
        $behaviors = [
            new SearchBehavior(SearchBehaviorType::FUZZY, null, null, 2.0),
            new SearchBehavior(SearchBehaviorType::EXACT),
        ];

        $config = new FieldConfig('name_field', 'name', 'en', $behaviors);

        $this->assertEquals('name_field', $config->id);
        $this->assertEquals('name', $config->fieldName);
        $this->assertEquals('en', $config->localeSuffix);
        $this->assertCount(2, $config->searchBehaviors);
    }

    public function testExtendsValueObject(): void
    {
        $config = new FieldConfig('id', 'field');
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new FieldConfig('id', 'field');
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $config = new FieldConfig('name_field', 'name');

        $expected = [
            'id' => 'name_field',
            'field_name' => 'name',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $behaviors = [
            new SearchBehavior(SearchBehaviorType::FUZZY, 'keyword', 'and', 2.0),
        ];

        $config = new FieldConfig('name_field', 'name', 'en', $behaviors);

        $expected = [
            'id' => 'name_field',
            'field_name' => 'name',
            'locale_suffix' => 'en',
            'search_behaviors' => [
                [
                    'type' => 'fuzzy',
                    'subfield' => 'keyword',
                    'operator' => 'and',
                    'boost' => 2.0,
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsEmptySearchBehaviors(): void
    {
        $config = new FieldConfig('id', 'field', 'en');

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('search_behaviors', $serialized);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field config id cannot be empty.');

        new FieldConfig('', 'field');
    }

    public function testThrowsExceptionForEmptyFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new FieldConfig('id', '');
    }

    public function testThrowsExceptionForInvalidSearchBehavior(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search behavior at index 1 must be an instance of SearchBehavior.');

        new FieldConfig('id', 'field', null, [
            new SearchBehavior(SearchBehaviorType::FUZZY),
            'invalid',
        ]);
    }

    public function testWithIdReturnsNewInstance(): void
    {
        $config = new FieldConfig('id', 'field');
        $newConfig = $config->withId('new_id');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('id', $config->id);
        $this->assertEquals('new_id', $newConfig->id);
    }

    public function testWithFieldNameReturnsNewInstance(): void
    {
        $config = new FieldConfig('id', 'field');
        $newConfig = $config->withFieldName('new_field');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('field', $config->fieldName);
        $this->assertEquals('new_field', $newConfig->fieldName);
    }

    public function testWithLocaleSuffixReturnsNewInstance(): void
    {
        $config = new FieldConfig('id', 'field');
        $newConfig = $config->withLocaleSuffix('en');

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->localeSuffix);
        $this->assertEquals('en', $newConfig->localeSuffix);
    }

    public function testWithSearchBehaviorsReturnsNewInstance(): void
    {
        $config = new FieldConfig('id', 'field');
        $behaviors = [new SearchBehavior(SearchBehaviorType::EXACT)];
        $newConfig = $config->withSearchBehaviors($behaviors);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(0, $config->searchBehaviors);
        $this->assertCount(1, $newConfig->searchBehaviors);
    }

    public function testWithAddedSearchBehaviorReturnsNewInstance(): void
    {
        $config = new FieldConfig('id', 'field', null, [
            new SearchBehavior(SearchBehaviorType::FUZZY),
        ]);
        $newConfig = $config->withAddedSearchBehavior(new SearchBehavior(SearchBehaviorType::EXACT));

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->searchBehaviors);
        $this->assertCount(2, $newConfig->searchBehaviors);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new FieldConfig('', 'field');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('id', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new FieldConfig('id', 'field', 'en');
        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }
}
