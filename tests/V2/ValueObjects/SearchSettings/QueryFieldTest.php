<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryField;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryFieldType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoreMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class QueryFieldTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'product_name'
        );

        $this->assertEquals(QueryFieldType::TEXT, $field->type);
        $this->assertEquals('product_name', $field->name);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals([], $field->searchTypes);
        $this->assertNull($field->lastWordSearch);
        $this->assertNull($field->nestedPath);
        $this->assertNull($field->scoreMode);
        $this->assertEquals([], $field->nestedFields);
        $this->assertNull($field->localeAware);
    }

    public function testConstructorWithAllParameters(): void
    {
        $nestedField = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'sku'
        );

        $field = new QueryField(
            type: QueryFieldType::NESTED,
            name: 'variants',
            localeSuffix: 'lt-LT',
            searchTypes: [SearchType::MATCH, SearchType::MATCH_FUZZY],
            lastWordSearch: true,
            nestedPath: 'variants',
            scoreMode: ScoreMode::MAX,
            nestedFields: [$nestedField],
            localeAware: true
        );

        $this->assertEquals(QueryFieldType::NESTED, $field->type);
        $this->assertEquals('variants', $field->name);
        $this->assertEquals('lt-LT', $field->localeSuffix);
        $this->assertEquals([SearchType::MATCH, SearchType::MATCH_FUZZY], $field->searchTypes);
        $this->assertTrue($field->lastWordSearch);
        $this->assertEquals('variants', $field->nestedPath);
        $this->assertEquals(ScoreMode::MAX, $field->scoreMode);
        $this->assertCount(1, $field->nestedFields);
        $this->assertTrue($field->localeAware);
    }

    public function testExtendsValueObject(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $this->assertInstanceOf(ValueObject::class, $field);
    }

    public function testImplementsJsonSerializable(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $this->assertInstanceOf(JsonSerializable::class, $field);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');

        $expected = [
            'type' => 'text',
            'name' => 'name',
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $nestedField = new QueryField(QueryFieldType::TEXT, 'sku');

        $field = new QueryField(
            type: QueryFieldType::NESTED,
            name: 'variants',
            localeSuffix: 'lt-LT',
            searchTypes: [SearchType::MATCH, SearchType::AUTOCOMPLETE],
            lastWordSearch: true,
            nestedPath: 'variants',
            scoreMode: ScoreMode::MAX,
            nestedFields: [$nestedField],
            localeAware: true
        );

        $expected = [
            'type' => 'nested',
            'name' => 'variants',
            'locale_suffix' => 'lt-LT',
            'search_types' => ['match', 'autocomplete'],
            'last_word_search' => true,
            'nested_path' => 'variants',
            'score_mode' => 'max',
            'nested_fields' => [
                [
                    'type' => 'text',
                    'name' => 'sku',
                ],
            ],
            'locale_aware' => true,
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'type' => 'text',
            'name' => 'product_name',
        ];

        $field = QueryField::fromArray($data);

        $this->assertEquals(QueryFieldType::TEXT, $field->type);
        $this->assertEquals('product_name', $field->name);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals([], $field->searchTypes);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'type' => 'nested',
            'name' => 'variants',
            'locale_suffix' => 'en-US',
            'search_types' => ['match', 'match-fuzzy', 'autocomplete'],
            'last_word_search' => true,
            'nested_path' => 'variants',
            'score_mode' => 'max',
            'nested_fields' => [
                [
                    'type' => 'text',
                    'name' => 'sku',
                    'search_types' => ['exact'],
                ],
            ],
            'locale_aware' => true,
        ];

        $field = QueryField::fromArray($data);

        $this->assertEquals(QueryFieldType::NESTED, $field->type);
        $this->assertEquals('variants', $field->name);
        $this->assertEquals('en-US', $field->localeSuffix);
        $this->assertEquals([SearchType::MATCH, SearchType::MATCH_FUZZY, SearchType::AUTOCOMPLETE], $field->searchTypes);
        $this->assertTrue($field->lastWordSearch);
        $this->assertEquals('variants', $field->nestedPath);
        $this->assertEquals(ScoreMode::MAX, $field->scoreMode);
        $this->assertCount(1, $field->nestedFields);
        $this->assertEquals('sku', $field->nestedFields[0]->name);
        $this->assertTrue($field->localeAware);
    }

    public function testFromArrayThrowsExceptionForMissingType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: type');

        QueryField::fromArray(['name' => 'test']);
    }

    public function testFromArrayThrowsExceptionForMissingName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: name');

        QueryField::fromArray(['type' => 'text']);
    }

    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new QueryField(QueryFieldType::TEXT, '');
    }

    public function testThrowsExceptionForNestedTypeWithoutNestedPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested fields require a nested_path to be specified.');

        new QueryField(QueryFieldType::NESTED, 'variants');
    }

    public function testThrowsExceptionForInvalidSearchType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search type at index 0 must be an instance of SearchType.');

        new QueryField(
            type: QueryFieldType::TEXT,
            name: 'test',
            searchTypes: ['invalid']
        );
    }

    public function testThrowsExceptionForInvalidNestedField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested field at index 0 must be an instance of QueryField.');

        new QueryField(
            type: QueryFieldType::NESTED,
            name: 'variants',
            nestedPath: 'variants',
            nestedFields: ['invalid']
        );
    }

    public function testWithNameReturnsNewInstance(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'original');
        $newField = $field->withName('updated');

        $this->assertNotSame($field, $newField);
        $this->assertEquals('original', $field->name);
        $this->assertEquals('updated', $newField->name);
    }

    public function testWithLocaleSuffixReturnsNewInstance(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $newField = $field->withLocaleSuffix('en-US');

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals('en-US', $newField->localeSuffix);
    }

    public function testWithSearchTypesReturnsNewInstance(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $newField = $field->withSearchTypes([SearchType::MATCH, SearchType::MATCH_FUZZY]);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([], $field->searchTypes);
        $this->assertEquals([SearchType::MATCH, SearchType::MATCH_FUZZY], $newField->searchTypes);
    }

    public function testWithAddedSearchTypeReturnsNewInstance(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'name',
            searchTypes: [SearchType::MATCH]
        );
        $newField = $field->withAddedSearchType(SearchType::AUTOCOMPLETE);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([SearchType::MATCH], $field->searchTypes);
        $this->assertEquals([SearchType::MATCH, SearchType::AUTOCOMPLETE], $newField->searchTypes);
    }

    public function testWithNestedFieldsReturnsNewInstance(): void
    {
        $nestedField = new QueryField(QueryFieldType::TEXT, 'sku');

        $field = new QueryField(
            type: QueryFieldType::NESTED,
            name: 'variants',
            nestedPath: 'variants'
        );
        $newField = $field->withNestedFields([$nestedField]);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([], $field->nestedFields);
        $this->assertCount(1, $newField->nestedFields);
    }

    public function testConstructorWithBooleanLocaleSuffix(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'name',
            localeSuffix: true
        );

        $this->assertTrue($field->localeSuffix);
    }

    public function testFromArrayWithBooleanLocaleSuffix(): void
    {
        $data = [
            'type' => 'text',
            'name' => 'name',
            'locale_suffix' => true,
        ];

        $field = QueryField::fromArray($data);

        $this->assertTrue($field->localeSuffix);
    }

    public function testJsonSerializePreservesBooleanLocaleSuffix(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'name',
            localeSuffix: true
        );

        $serialized = $field->jsonSerialize();

        $this->assertArrayHasKey('locale_suffix', $serialized);
        $this->assertTrue($serialized['locale_suffix']);
        $this->assertIsBool($serialized['locale_suffix']);
    }

    public function testWithLocaleSuffixAcceptsBool(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $newField = $field->withLocaleSuffix(true);

        $this->assertTrue($newField->localeSuffix);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'type' => 'nested',
            'name' => 'variants',
            'locale_suffix' => 'lt-LT',
            'search_types' => ['match', 'autocomplete'],
            'last_word_search' => true,
            'nested_path' => 'variants',
            'score_mode' => 'max',
            'nested_fields' => [
                [
                    'type' => 'text',
                    'name' => 'sku',
                    'search_types' => ['exact'],
                ],
            ],
            'locale_aware' => true,
        ];

        $field = QueryField::fromArray($originalData);
        $serialized = $field->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
