<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\LastWordSearch;
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
            localeSuffix: true,
            searchTypes: [SearchType::MATCH, SearchType::MATCH_FUZZY],
            lastWordSearch: true,
            nestedPath: 'variants',
            scoreMode: ScoreMode::MAX,
            nestedFields: [$nestedField],
            localeAware: true
        );

        $this->assertEquals(QueryFieldType::NESTED, $field->type);
        $this->assertEquals('variants', $field->name);
        $this->assertTrue($field->localeSuffix);
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
            localeSuffix: true,
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
            'locale_suffix' => true,
            'searchTypes' => ['match', 'autocomplete'],
            'lastWordSearch' => true,
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

    /**
     * The engine (models/query_config.go) reads `searchTypes` and `lastWordSearch` in
     * camelCase - unlike every other QueryField key, which is snake_case. Serialising
     * these two as snake_case is silently dropped by the engine's JSON decoder.
     */
    public function testJsonSerializeUsesCamelCaseForSearchTypesAndLastWordSearch(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'name',
            searchTypes: [SearchType::MATCH],
            lastWordSearch: true
        );

        $result = $field->jsonSerialize();

        $this->assertArrayHasKey('searchTypes', $result);
        $this->assertArrayHasKey('lastWordSearch', $result);
        $this->assertArrayNotHasKey('search_types', $result);
        $this->assertArrayNotHasKey('last_word_search', $result);
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
            'locale_suffix' => true,
            'searchTypes' => ['match', 'match-fuzzy', 'autocomplete'],
            'lastWordSearch' => true,
            'nested_path' => 'variants',
            'score_mode' => 'max',
            'nested_fields' => [
                [
                    'type' => 'text',
                    'name' => 'sku',
                    'searchTypes' => ['exact'],
                ],
            ],
            'locale_aware' => true,
        ];

        $field = QueryField::fromArray($data);

        $this->assertEquals(QueryFieldType::NESTED, $field->type);
        $this->assertEquals('variants', $field->name);
        $this->assertTrue($field->localeSuffix);
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
        $this->expectExceptionMessage('Search type at index 0 must be a SearchType instance or a non-empty string.');

        new QueryField(
            type: QueryFieldType::TEXT,
            name: 'test',
            searchTypes: [123]
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
        $newField = $field->withLocaleSuffix(true);

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->localeSuffix);
        $this->assertTrue($newField->localeSuffix);
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
            'locale_suffix' => true,
            'searchTypes' => ['match', 'autocomplete'],
            'lastWordSearch' => true,
            'nested_path' => 'variants',
            'score_mode' => 'max',
            'nested_fields' => [
                [
                    'type' => 'text',
                    'name' => 'sku',
                    'searchTypes' => ['exact'],
                ],
            ],
            'locale_aware' => true,
        ];

        $field = QueryField::fromArray($originalData);
        $serialized = $field->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }

    public function testConstructorDefaultsPositionAndPriorityToNull(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');

        $this->assertNull($field->position);
        $this->assertNull($field->priority);
    }

    public function testJsonSerializeEmitsPositionAndPriorityWhenSet(): void
    {
        // Mirrors the BRD-1305 preset: main_word shares position 1 with name and is
        // ordered above it by priority 3 (×3.0 on the bucket boost).
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'main_word',
            localeSuffix: true,
            searchTypes: [SearchType::EXACT, SearchType::MATCH],
            position: 1,
            priority: 3
        );

        $expected = [
            'type' => 'text',
            'name' => 'main_word',
            'locale_suffix' => true,
            'searchTypes' => ['exact', 'match'],
            'position' => 1,
            'priority' => 3,
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testJsonSerializeOmitsPositionAndPriorityWhenNull(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $serialized = $field->jsonSerialize();

        $this->assertArrayNotHasKey('position', $serialized);
        $this->assertArrayNotHasKey('priority', $serialized);
    }

    public function testJsonSerializeKeepsPriorityZeroAndPositionZero(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'sku', position: 0, priority: 0);
        $serialized = $field->jsonSerialize();

        $this->assertSame(0, $serialized['position']);
        $this->assertSame(0, $serialized['priority']);
    }

    public function testFromArrayReadsPositionAndPriority(): void
    {
        $field = QueryField::fromArray([
            'type' => 'text',
            'name' => 'main_word_base',
            'position' => 1,
            'priority' => 2,
        ]);

        $this->assertSame(1, $field->position);
        $this->assertSame(2, $field->priority);
    }

    public function testFromArrayLeavesPositionAndPriorityNullWhenAbsent(): void
    {
        $field = QueryField::fromArray(['type' => 'text', 'name' => 'name']);

        $this->assertNull($field->position);
        $this->assertNull($field->priority);
    }

    public function testPositionAndPriorityRoundTripThroughFromArray(): void
    {
        $original = new QueryField(QueryFieldType::TEXT, 'also_known_as', position: 1, priority: 1);
        $roundTripped = QueryField::fromArray($original->jsonSerialize());

        $this->assertEquals($original->jsonSerialize(), $roundTripped->jsonSerialize());
    }

    public function testThrowsExceptionForPriorityAboveLadder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 4, got 5.');

        new QueryField(QueryFieldType::TEXT, 'name', priority: 5);
    }

    public function testThrowsExceptionForNegativePriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 4, got -1.');

        new QueryField(QueryFieldType::TEXT, 'name', priority: -1);
    }

    public function testThrowsExceptionForPositionOutsideBuckets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be between 0 and 3, got 4.');

        new QueryField(QueryFieldType::TEXT, 'name', position: 4);
    }

    public function testWithPriorityReturnsNewInstance(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name', position: 1);
        $newField = $field->withPriority(3);

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->priority);
        $this->assertSame(3, $newField->priority);
        $this->assertSame(1, $newField->position);
    }

    public function testWithPositionReturnsNewInstance(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name', priority: 2);
        $newField = $field->withPosition(0);

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->position);
        $this->assertSame(0, $newField->position);
        $this->assertSame(2, $newField->priority);
    }

    public function testExistingWithMethodsPreservePositionAndPriority(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name', position: 1, priority: 3);

        $this->assertSame(1, $field->withName('other')->position);
        $this->assertSame(3, $field->withName('other')->priority);
        $this->assertSame(3, $field->withSearchTypes([SearchType::MATCH])->priority);
        $this->assertSame(3, $field->withAddedSearchType(SearchType::MATCH)->priority);
        $this->assertSame(3, $field->withLocaleSuffix(true)->priority);
    }

    /**
     * Payloads written by an older SDK use snake_case. Reading only the new key would
     * turn the silent drop this PR fixes into a silent drop on the read path.
     */
    public function testFromArrayReadsLegacySnakeCaseKeys(): void
    {
        $field = QueryField::fromArray([
            'type' => 'text',
            'name' => 'name',
            'search_types' => ['match', 'autocomplete'],
            'last_word_search' => true,
        ]);

        $this->assertEquals([SearchType::MATCH, SearchType::AUTOCOMPLETE], $field->searchTypes);
        $this->assertTrue($field->lastWordSearch);
    }

    public function testFromArrayEmitsCamelCaseForLegacyInput(): void
    {
        $serialized = QueryField::fromArray([
            'type' => 'text',
            'name' => 'name',
            'search_types' => ['match'],
            'last_word_search' => true,
        ])->jsonSerialize();

        $this->assertSame(['match'], $serialized['searchTypes']);
        $this->assertTrue($serialized['lastWordSearch']);
        $this->assertArrayNotHasKey('search_types', $serialized);
        $this->assertArrayNotHasKey('last_word_search', $serialized);
    }

    public function testCamelCaseKeyWinsOverLegacyKey(): void
    {
        $field = QueryField::fromArray([
            'type' => 'text',
            'name' => 'name',
            'searchTypes' => ['exact'],
            'search_types' => ['match'],
        ]);

        $this->assertEquals([SearchType::EXACT], $field->searchTypes);
    }

    /**
     * The engine marshals lastWordSearch as an object. Casting that array to bool would
     * read a disabled, hand-tuned field back as `true`, which the engine then expands
     * into enabled with hardcoded autocomplete types.
     */
    public function testLastWordSearchObjectRoundTripsLosslessly(): void
    {
        $data = [
            'type' => 'text',
            'name' => 'name',
            'searchTypes' => ['match'],
            'lastWordSearch' => [
                'enabled' => false,
                'searchTypes' => [
                    'first' => ['match', 'match-fuzzy'],
                    'last' => ['stemmed', 'stemmed-fuzzy'],
                    'full' => ['phrase-prefix'],
                ],
                'fuzzy_config' => [
                    'last' => [
                        'stemmed-fuzzy' => ['fuzziness' => '2', 'prefix_length' => 2],
                    ],
                ],
            ],
        ];

        $field = QueryField::fromArray($data);

        $this->assertInstanceOf(LastWordSearch::class, $field->lastWordSearch);
        $this->assertFalse($field->lastWordSearch->enabled);
        $this->assertEquals($data, $field->jsonSerialize());
    }

    public function testLastWordSearchBoolStaysBool(): void
    {
        // The engine expands a bare `true` into its hardcoded set; re-emitting an object
        // with no searchTypes instead would skip that expansion.
        $field = QueryField::fromArray([
            'type' => 'text',
            'name' => 'name',
            'lastWordSearch' => true,
        ]);

        $this->assertTrue($field->lastWordSearch);
        $this->assertTrue($field->jsonSerialize()['lastWordSearch']);
    }

    /**
     * The engine declares locale_suffix as bool; a string 400s the whole save, so the
     * SDK refuses it rather than coercing "lt-LT" to true.
     */
    public function testFromArrayRejectsNonBooleanLocaleSuffix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('locale_suffix must be a boolean, string given.');

        QueryField::fromArray([
            'type' => 'text',
            'name' => 'name',
            'locale_suffix' => 'lt-LT',
        ]);
    }

    /**
     * The engine accepts boost-modifier search types the enum cannot represent. Throwing
     * would make the SDK unable to read a valid tenant configuration, so the raw string
     * is kept and re-emitted unchanged.
     */
    public function testUnknownSearchTypeIsKeptAsString(): void
    {
        $field = QueryField::fromArray([
            'type' => 'text',
            'name' => 'sku',
            'searchTypes' => ['exact_300', 'match'],
        ]);

        $this->assertSame('exact_300', $field->searchTypes[0]);
        $this->assertEquals(SearchType::MATCH, $field->searchTypes[1]);
        $this->assertSame(['exact_300', 'match'], $field->jsonSerialize()['searchTypes']);
    }

    /**
     * A real engine field (brad-search tests/bdd/testdata/clients) must survive
     * fromArray -> jsonSerialize untouched, including the keys the value object used to
     * have no property for.
     */
    public function testRoundTripsRealisticEngineField(): void
    {
        $data = [
            'type' => 'text',
            'name' => 'main_word',
            'locale_suffix' => true,
            'position' => 1,
            'priority' => 3,
            'searchTypes' => ['exact', 'stemmed', 'match-fuzzy'],
            'fuzzy_config' => [
                'match-fuzzy' => ['fuzziness' => 'AUTO', 'prefix_length' => 2],
            ],
            'boosts' => ['match' => 1.0, 'exact' => 300],
            'cross_field_only' => true,
            'lastWordSearch' => [
                'enabled' => true,
                'searchTypes' => [
                    'first' => ['match', 'match-fuzzy'],
                    'last' => ['autocomplete', 'match-fuzzy'],
                    'full' => ['autocomplete'],
                ],
            ],
        ];

        $this->assertEquals($data, QueryField::fromArray($data)->jsonSerialize());
    }

    public function testJsonSerializeOmitsPassthroughKeysWhenNull(): void
    {
        $serialized = (new QueryField(QueryFieldType::TEXT, 'name'))->jsonSerialize();

        $this->assertArrayNotHasKey('fuzzy_config', $serialized);
        $this->assertArrayNotHasKey('boosts', $serialized);
        $this->assertArrayNotHasKey('cross_field_only', $serialized);
    }

    public function testExistingWithMethodsPreservePassthroughKeys(): void
    {
        $field = new QueryField(
            QueryFieldType::TEXT,
            'name',
            fuzzyConfig: ['match-fuzzy' => ['fuzziness' => 'AUTO']],
            boosts: ['exact' => 300],
            crossFieldOnly: true
        );

        $renamed = $field->withName('other');

        $this->assertSame($field->fuzzyConfig, $renamed->fuzzyConfig);
        $this->assertSame($field->boosts, $renamed->boosts);
        $this->assertTrue($renamed->crossFieldOnly);
    }

    public function testWithLastWordSearchReplacesTheObject(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name', lastWordSearch: true);
        $newField = $field->withLastWordSearch(new LastWordSearch(enabled: false));

        $this->assertTrue($field->lastWordSearch);
        $this->assertInstanceOf(LastWordSearch::class, $newField->lastWordSearch);
        $this->assertFalse($newField->lastWordSearch->enabled);
    }
}
