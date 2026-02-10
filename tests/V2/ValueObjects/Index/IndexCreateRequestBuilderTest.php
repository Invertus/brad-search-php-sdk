<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequestBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use PHPUnit\Framework\TestCase;

class IndexCreateRequestBuilderTest extends TestCase
{
    public function testBuildCreatesIndexCreateRequest(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $this->assertInstanceOf(IndexCreateRequest::class, $request);
        $this->assertEquals(['lt-LT'], $request->locales);
        $this->assertCount(1, $request->fields);
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $this->assertSame($builder, $builder->addLocale('lt-LT'));
        $this->assertSame($builder, $builder->addField(new FieldDefinition('id', FieldType::KEYWORD)));
    }

    public function testBuildWithMultipleLocales(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addLocale('en-US')
            ->addLocale('de-DE')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $this->assertEquals(['lt-LT', 'en-US', 'de-DE'], $request->locales);
    }

    public function testBuildWithMultipleFields(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->addField(new FieldDefinition('name', FieldType::TEXT))
            ->addField(new FieldDefinition('price', FieldType::DOUBLE))
            ->build();

        $this->assertCount(3, $request->fields);
        $this->assertEquals('id', $request->fields[0]->name);
        $this->assertEquals('name', $request->fields[1]->name);
        $this->assertEquals('price', $request->fields[2]->name);
    }

    public function testThrowsExceptionWhenNoLocalesAdded(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one locale is required.');

        $builder
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();
    }

    public function testThrowsExceptionWhenNoFieldsAdded(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one field is required.');

        $builder
            ->addLocale('lt-LT')
            ->build();
    }

    public function testResetClearsAllValues(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one locale is required.');

        $builder->build();
    }

    public function testResetReturnsBuilder(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $this->assertSame($builder, $builder->reset());
    }

    public function testCanReuseBuilderAfterReset(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request1 = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $builder->reset();

        $request2 = $builder
            ->addLocale('en-US')
            ->addField(new FieldDefinition('name', FieldType::TEXT))
            ->build();

        $this->assertEquals(['lt-LT'], $request1->locales);
        $this->assertEquals(['en-US'], $request2->locales);
        $this->assertEquals('id', $request1->fields[0]->name);
        $this->assertEquals('name', $request2->fields[0]->name);
        $this->assertNotSame($request1, $request2);
    }

    public function testBuildWithFieldContainingVariantAttributes(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('variants', FieldType::VARIANTS, [
                new VariantAttribute('size', FieldType::KEYWORD, true),
                new VariantAttribute('color', FieldType::KEYWORD, true),
            ]))
            ->build();

        $serialized = $request->jsonSerialize();

        $this->assertArrayHasKey('attributes', $serialized['fields'][0]);
        $this->assertCount(2, $serialized['fields'][0]['attributes']);
    }

    /**
     * Test building Darbo drabuziai index request using the builder.
     * This verifies the builder produces the exact structure documented in the API.
     */
    public function testBuildingDarboDrabuziaiRequestWithBuilder(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->addField(new FieldDefinition('name_lt-LT', FieldType::TEXT))
            ->addField(new FieldDefinition('brand_lt-LT', FieldType::TEXT))
            ->addField(new FieldDefinition('sku', FieldType::KEYWORD))
            ->addField(new FieldDefinition('imageUrl', FieldType::IMAGE_URL))
            ->addField(new FieldDefinition('description_lt-LT', FieldType::TEXT))
            ->addField(new FieldDefinition('categories_lt-LT', FieldType::TEXT))
            ->addField(new FieldDefinition('price', FieldType::DOUBLE))
            ->addField(new FieldDefinition('variants', FieldType::VARIANTS, [
                new VariantAttribute('size', FieldType::KEYWORD, true),
                new VariantAttribute('color', FieldType::KEYWORD, true),
            ]))
            ->build();

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

    public function testBuilderCanBuildMultipleRequestsSequentially(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request1 = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $builder->reset();

        $request2 = $builder
            ->addLocale('en-US')
            ->addLocale('de-DE')
            ->addField(new FieldDefinition('name', FieldType::TEXT))
            ->addField(new FieldDefinition('price', FieldType::DOUBLE))
            ->build();

        $this->assertCount(1, $request1->locales);
        $this->assertCount(1, $request1->fields);

        $this->assertCount(2, $request2->locales);
        $this->assertCount(2, $request2->fields);
    }

    /**
     * @dataProvider validLocaleDataProvider
     */
    public function testBuilderAcceptsValidLocales(string $locale): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale($locale)
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $this->assertContains($locale, $request->locales);
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
        ];
    }

    public function testOrderOfLocalesIsPreserved(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('en-US')
            ->addLocale('lt-LT')
            ->addLocale('de-DE')
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->build();

        $this->assertEquals(['en-US', 'lt-LT', 'de-DE'], $request->locales);
    }

    public function testOrderOfFieldsIsPreserved(): void
    {
        $builder = new IndexCreateRequestBuilder();

        $request = $builder
            ->addLocale('lt-LT')
            ->addField(new FieldDefinition('price', FieldType::DOUBLE))
            ->addField(new FieldDefinition('id', FieldType::KEYWORD))
            ->addField(new FieldDefinition('name', FieldType::TEXT))
            ->build();

        $this->assertEquals('price', $request->fields[0]->name);
        $this->assertEquals('id', $request->fields[1]->name);
        $this->assertEquals('name', $request->fields[2]->name);
    }
}
