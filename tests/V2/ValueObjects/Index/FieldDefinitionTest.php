<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\SearchAnalysis;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $this->assertEquals('name', $field->name);
        $this->assertEquals(FieldType::TEXT, $field->type);
        $this->assertEquals([], $field->attributes);
    }

    public function testConstructorWithAttributes(): void
    {
        $attributes = [
            new VariantAttribute('size', FieldType::KEYWORD),
            new VariantAttribute('color', FieldType::TEXT, true),
        ];

        $field = new FieldDefinition('variants', FieldType::VARIANTS, $attributes);

        $this->assertEquals('variants', $field->name);
        $this->assertEquals(FieldType::VARIANTS, $field->type);
        $this->assertCount(2, $field->attributes);
    }

    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new FieldDefinition('', FieldType::TEXT);
    }

    public function testThrowsExceptionForInvalidAttributeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute at index 1 must be an instance of VariantAttribute.');

        new FieldDefinition('variants', FieldType::VARIANTS, [
            new VariantAttribute('size', FieldType::KEYWORD),
            'invalid',
        ]);
    }

    public function testExtendsValueObject(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $this->assertInstanceOf(ValueObject::class, $field);
    }

    public function testImplementsJsonSerializable(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $this->assertInstanceOf(JsonSerializable::class, $field);
    }

    public function testJsonSerializeWithoutAttributes(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $expected = [
            'name' => 'name',
            'type' => 'text',
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testJsonSerializeWithAttributes(): void
    {
        $attributes = [
            new VariantAttribute('size', FieldType::KEYWORD, false),
            new VariantAttribute('color', FieldType::TEXT, true),
        ];

        $field = new FieldDefinition('variants', FieldType::VARIANTS, $attributes);

        $expected = [
            'name' => 'variants',
            'type' => 'variants',
            'attributes' => [
                [
                    'id' => 'size',
                    'type' => 'keyword',
                    'locale_aware' => false,
                ],
                [
                    'id' => 'color',
                    'type' => 'text',
                    'locale_aware' => true,
                ],
            ],
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testAttributesOmittedWhenEmpty(): void
    {
        $field = new FieldDefinition('price', FieldType::DOUBLE);

        $serialized = $field->jsonSerialize();

        $this->assertArrayNotHasKey('attributes', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $this->assertEquals($field->jsonSerialize(), $field->toArray());
    }

    public function testWithNameReturnsNewInstance(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);
        $newField = $field->withName('title');

        $this->assertNotSame($field, $newField);
        $this->assertEquals('name', $field->name);
        $this->assertEquals('title', $newField->name);
        $this->assertEquals($field->type, $newField->type);
    }

    public function testWithTypeReturnsNewInstance(): void
    {
        $field = new FieldDefinition('price', FieldType::DOUBLE);
        $newField = $field->withType(FieldType::INTEGER);

        $this->assertNotSame($field, $newField);
        $this->assertEquals(FieldType::DOUBLE, $field->type);
        $this->assertEquals(FieldType::INTEGER, $newField->type);
        $this->assertEquals($field->name, $newField->name);
    }

    public function testWithAttributesReturnsNewInstance(): void
    {
        $field = new FieldDefinition('variants', FieldType::VARIANTS);
        $attributes = [new VariantAttribute('size', FieldType::KEYWORD)];
        $newField = $field->withAttributes($attributes);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([], $field->attributes);
        $this->assertCount(1, $newField->attributes);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $json = json_encode($field);
        $decoded = json_decode($json, true);

        $this->assertEquals('name', $decoded['name']);
        $this->assertEquals('text', $decoded['type']);
    }

    /**
     * Test output matches OpenAPI example for Darbo drabuziai fields.
     * This verifies the SDK produces the exact structure documented in the API.
     */
    public function testDarboDrabuziaiFieldsMatchOpenApiExample(): void
    {
        // Build field definitions matching the Darbo drabuziai client example
        $fields = [
            new FieldDefinition('id', FieldType::KEYWORD),
            new FieldDefinition('name_lt-LT', FieldType::TEXT),
            new FieldDefinition('brand_lt-LT', FieldType::TEXT),
            new FieldDefinition('sku', FieldType::KEYWORD),
            new FieldDefinition('imageUrl', FieldType::IMAGE_URL),
            new FieldDefinition('description_lt-LT', FieldType::TEXT),
            new FieldDefinition('categories_lt-LT', FieldType::TEXT),
            new FieldDefinition('price', FieldType::DOUBLE),
            new FieldDefinition('variants', FieldType::VARIANTS, [
                new VariantAttribute('size', FieldType::KEYWORD, true),
                new VariantAttribute('color', FieldType::KEYWORD, true),
            ]),
        ];

        // Expected structure from OpenAPI documentation
        $expectedFields = [
            ['name' => 'id', 'type' => 'keyword'],
            ['name' => 'name_lt-LT', 'type' => 'text'],
            ['name' => 'brand_lt-LT', 'type' => 'text'],
            ['name' => 'sku', 'type' => 'keyword'],
            ['name' => 'imageUrl', 'type' => 'image_url'],
            ['name' => 'description_lt-LT', 'type' => 'text'],
            ['name' => 'categories_lt-LT', 'type' => 'text'],
            ['name' => 'price', 'type' => 'double'],
            [
                'name' => 'variants',
                'type' => 'variants',
                'attributes' => [
                    ['id' => 'size', 'type' => 'keyword', 'locale_aware' => true],
                    ['id' => 'color', 'type' => 'keyword', 'locale_aware' => true],
                ],
            ],
        ];

        $actualFields = array_map(fn(FieldDefinition $f) => $f->jsonSerialize(), $fields);

        $this->assertEquals($expectedFields, $actualFields);
    }

    /**
     * @dataProvider fieldTypeDataProvider
     */
    public function testSupportsAllFieldTypes(FieldType $type): void
    {
        $field = new FieldDefinition('test_field', $type);

        $this->assertEquals($type, $field->type);
        $this->assertEquals($type->value, $field->jsonSerialize()['type']);
    }

    /**
     * @return array<string, array{FieldType}>
     */
    public static function fieldTypeDataProvider(): array
    {
        return [
            'text' => [FieldType::TEXT],
            'keyword' => [FieldType::KEYWORD],
            'double' => [FieldType::DOUBLE],
            'integer' => [FieldType::INTEGER],
            'boolean' => [FieldType::BOOLEAN],
            'image_url' => [FieldType::IMAGE_URL],
            'variants' => [FieldType::VARIANTS],
        ];
    }

    public function testFieldWithLocaleSuffixedName(): void
    {
        $field = new FieldDefinition('name_lt-LT', FieldType::TEXT);

        $this->assertEquals('name_lt-LT', $field->name);
        $this->assertEquals('name_lt-LT', $field->jsonSerialize()['name']);
    }

    public function testMultipleVariantAttributes(): void
    {
        $field = new FieldDefinition('variants', FieldType::VARIANTS, [
            new VariantAttribute('size', FieldType::KEYWORD, true),
            new VariantAttribute('color', FieldType::KEYWORD, true),
            new VariantAttribute('material', FieldType::TEXT, false),
        ]);

        $serialized = $field->jsonSerialize();

        $this->assertCount(3, $serialized['attributes']);
        $this->assertEquals('size', $serialized['attributes'][0]['id']);
        $this->assertEquals('color', $serialized['attributes'][1]['id']);
        $this->assertEquals('material', $serialized['attributes'][2]['id']);
    }

    public function testConstructorWithSearchAnalysis(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT, [], SearchAnalysis::FULL);

        $this->assertEquals(SearchAnalysis::FULL, $field->searchAnalysis);
    }

    public function testConstructorWithoutSearchAnalysisDefaultsToNull(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $this->assertNull($field->searchAnalysis);
    }

    public function testWithSearchAnalysisReturnsNewInstance(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);
        $newField = $field->withSearchAnalysis(SearchAnalysis::FULL);

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->searchAnalysis);
        $this->assertEquals(SearchAnalysis::FULL, $newField->searchAnalysis);
    }

    public function testWithSearchAnalysisCanSetToNull(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT, [], SearchAnalysis::BASIC);
        $newField = $field->withSearchAnalysis(null);

        $this->assertEquals(SearchAnalysis::BASIC, $field->searchAnalysis);
        $this->assertNull($newField->searchAnalysis);
    }

    public function testJsonSerializeIncludesSearchAnalysis(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT, [], SearchAnalysis::BASIC);

        $result = $field->jsonSerialize();

        $this->assertArrayHasKey('search_analysis', $result);
        $this->assertEquals('basic', $result['search_analysis']);
    }

    public function testJsonSerializeIncludesSearchAnalysisFull(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT, [], SearchAnalysis::FULL);

        $result = $field->jsonSerialize();

        $this->assertArrayHasKey('search_analysis', $result);
        $this->assertEquals('full', $result['search_analysis']);
    }

    public function testJsonSerializeOmitsSearchAnalysisWhenNull(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT);

        $result = $field->jsonSerialize();

        $this->assertArrayNotHasKey('search_analysis', $result);
    }

    public function testImmutableMethodsPreserveSearchAnalysis(): void
    {
        $field = new FieldDefinition('name', FieldType::TEXT, [], SearchAnalysis::FULL);

        $renamed = $field->withName('title');
        $this->assertEquals(SearchAnalysis::FULL, $renamed->searchAnalysis);

        $retyped = $field->withType(FieldType::KEYWORD);
        $this->assertEquals(SearchAnalysis::FULL, $retyped->searchAnalysis);

        $withAttrs = $field->withAttributes([new VariantAttribute('size', FieldType::KEYWORD)]);
        $this->assertEquals(SearchAnalysis::FULL, $withAttrs->searchAnalysis);
    }
}
