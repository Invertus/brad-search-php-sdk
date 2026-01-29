<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidLocaleException;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class IndexCreateRequestTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $locales = ['lt-LT', 'en-US'];
        $fields = [
            new FieldDefinition('id', FieldType::KEYWORD),
            new FieldDefinition('name', FieldType::TEXT),
        ];

        $request = new IndexCreateRequest($locales, $fields);

        $this->assertEquals($locales, $request->locales);
        $this->assertEquals($fields, $request->fields);
    }

    public function testExtendsValueObject(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertInstanceOf(ValueObject::class, $request);
    }

    public function testImplementsJsonSerializable(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertInstanceOf(JsonSerializable::class, $request);
    }

    public function testThrowsExceptionForEmptyLocales(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one locale is required.');

        new IndexCreateRequest(
            [],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );
    }

    public function testThrowsExceptionForEmptyFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one field is required.');

        new IndexCreateRequest(
            ['lt-LT'],
            []
        );
    }

    /**
     * @dataProvider invalidLocaleDataProvider
     */
    public function testThrowsExceptionForInvalidLocale(string $invalidLocale): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessageMatches('/Invalid locale/');

        new IndexCreateRequest(
            [$invalidLocale],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLocaleDataProvider(): array
    {
        return [
            'lowercase only' => ['ltlt'],
            'uppercase only' => ['LTLT'],
            'wrong case first' => ['LT-lt'],
            'wrong case second' => ['lt-lt'],
            'missing hyphen' => ['ltLT'],
            'extra characters' => ['lt-LT-'],
            'too short' => ['l-L'],
            'too long' => ['ltt-LTT'],
            'numbers' => ['12-34'],
            'special characters' => ['lt_LT'],
            'empty string' => [''],
            'single character' => ['l'],
        ];
    }

    /**
     * @dataProvider validLocaleDataProvider
     */
    public function testAcceptsValidLocale(string $validLocale): void
    {
        $request = new IndexCreateRequest(
            [$validLocale],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertContains($validLocale, $request->locales);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validLocaleDataProvider(): array
    {
        return [
            'lithuanian' => ['lt-LT'],
            'english US' => ['en-US'],
            'english UK' => ['en-GB'],
            'german' => ['de-DE'],
            'french' => ['fr-FR'],
            'spanish' => ['es-ES'],
            'polish' => ['pl-PL'],
            'latvian' => ['lv-LV'],
            'estonian' => ['et-EE'],
        ];
    }

    public function testThrowsExceptionForNonStringLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale at index 1 must be a string.');

        new IndexCreateRequest(
            ['lt-LT', 123],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );
    }

    public function testThrowsExceptionForInvalidFieldType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 1 must be an instance of FieldDefinition.');

        new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                'invalid',
            ]
        );
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT', 'en-US'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('name', FieldType::TEXT),
            ]
        );

        $expected = [
            'locales' => ['lt-LT', 'en-US'],
            'fields' => [
                ['name' => 'id', 'type' => 'keyword'],
                ['name' => 'name', 'type' => 'text'],
            ],
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertEquals($request->jsonSerialize(), $request->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertEquals(['lt-LT'], $decoded['locales']);
        $this->assertCount(1, $decoded['fields']);
        $this->assertEquals('id', $decoded['fields'][0]['name']);
    }

    public function testWithLocalesReturnsNewInstance(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $newRequest = $request->withLocales(['en-US', 'de-DE']);

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals(['lt-LT'], $request->locales);
        $this->assertEquals(['en-US', 'de-DE'], $newRequest->locales);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $originalField = new FieldDefinition('id', FieldType::KEYWORD);
        $newField = new FieldDefinition('name', FieldType::TEXT);

        $request = new IndexCreateRequest(['lt-LT'], [$originalField]);
        $newRequest = $request->withFields([$newField]);

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals([$originalField], $request->fields);
        $this->assertEquals([$newField], $newRequest->fields);
    }

    public function testWithAddedLocaleReturnsNewInstance(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $newRequest = $request->withAddedLocale('en-US');

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals(['lt-LT'], $request->locales);
        $this->assertEquals(['lt-LT', 'en-US'], $newRequest->locales);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $originalField = new FieldDefinition('id', FieldType::KEYWORD);
        $newField = new FieldDefinition('name', FieldType::TEXT);

        $request = new IndexCreateRequest(['lt-LT'], [$originalField]);
        $newRequest = $request->withAddedField($newField);

        $this->assertNotSame($request, $newRequest);
        $this->assertCount(1, $request->fields);
        $this->assertCount(2, $newRequest->fields);
        $this->assertSame($originalField, $newRequest->fields[0]);
        $this->assertSame($newField, $newRequest->fields[1]);
    }

    /**
     * Test output matches OpenAPI IndexCreateRequestV2App schema for Darbo drabuziai example.
     * This verifies the SDK produces the exact structure documented in the API.
     */
    public function testDarboDrabuziaiExampleMatchesOpenApiSchema(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
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
            ]
        );

        $expected = [
            'locales' => ['lt-LT'],
            'fields' => [
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
            ],
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testMultipleLocales(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT', 'en-US', 'de-DE'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $serialized = $request->jsonSerialize();

        $this->assertCount(3, $serialized['locales']);
        $this->assertEquals(['lt-LT', 'en-US', 'de-DE'], $serialized['locales']);
    }

    public function testMultipleFields(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('name', FieldType::TEXT),
                new FieldDefinition('price', FieldType::DOUBLE),
                new FieldDefinition('active', FieldType::BOOLEAN),
            ]
        );

        $serialized = $request->jsonSerialize();

        $this->assertCount(4, $serialized['fields']);
    }

    public function testFieldsWithVariantAttributes(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('variants', FieldType::VARIANTS, [
                    new VariantAttribute('size', FieldType::KEYWORD, true),
                    new VariantAttribute('color', FieldType::KEYWORD, true),
                    new VariantAttribute('material', FieldType::TEXT, false),
                ]),
            ]
        );

        $serialized = $request->jsonSerialize();

        $this->assertArrayHasKey('attributes', $serialized['fields'][0]);
        $this->assertCount(3, $serialized['fields'][0]['attributes']);
    }

    public function testSingleLocaleValidation(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertCount(1, $request->locales);
        $this->assertEquals('lt-LT', $request->locales[0]);
    }

    public function testSingleFieldValidation(): void
    {
        $request = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $this->assertCount(1, $request->fields);
        $this->assertEquals('id', $request->fields[0]->name);
    }

    public function testLocaleValidationOccursAtConstruction(): void
    {
        $validLocales = ['lt-LT', 'en-US'];
        $fields = [new FieldDefinition('id', FieldType::KEYWORD)];

        $request = new IndexCreateRequest($validLocales, $fields);

        $this->assertInstanceOf(IndexCreateRequest::class, $request);
    }

    public function testInvalidLocaleInMiddleOfArray(): void
    {
        $this->expectException(InvalidLocaleException::class);

        new IndexCreateRequest(
            ['lt-LT', 'invalid', 'en-US'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );
    }

    public function testInvalidFieldInMiddleOfArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 1 must be an instance of FieldDefinition.');

        new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                ['name' => 'invalid', 'type' => 'text'],
                new FieldDefinition('price', FieldType::DOUBLE),
            ]
        );
    }
}
