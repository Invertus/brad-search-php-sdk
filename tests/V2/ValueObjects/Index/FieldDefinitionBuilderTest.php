<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinitionBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\SearchAnalysis;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use PHPUnit\Framework\TestCase;

class FieldDefinitionBuilderTest extends TestCase
{
    public function testBuildCreatesFieldDefinition(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('name')
            ->type(FieldType::TEXT)
            ->build();

        $this->assertInstanceOf(FieldDefinition::class, $field);
        $this->assertEquals('name', $field->name);
        $this->assertEquals(FieldType::TEXT, $field->type);
    }

    public function testFluentApiReturnsBuilder(): void
    {
        $builder = new FieldDefinitionBuilder();

        $this->assertSame($builder, $builder->name('test'));
        $this->assertSame($builder, $builder->type(FieldType::TEXT));
        $this->assertSame($builder, $builder->addAttribute(new VariantAttribute('size', FieldType::KEYWORD)));
    }

    public function testBuildWithAttributes(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('variants')
            ->type(FieldType::VARIANTS)
            ->addAttribute(new VariantAttribute('size', FieldType::KEYWORD))
            ->addAttribute(new VariantAttribute('color', FieldType::TEXT, true))
            ->build();

        $this->assertEquals('variants', $field->name);
        $this->assertEquals(FieldType::VARIANTS, $field->type);
        $this->assertCount(2, $field->attributes);
    }

    public function testThrowsExceptionWhenNameIsMissing(): void
    {
        $builder = new FieldDefinitionBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name is required.');

        $builder->type(FieldType::TEXT)->build();
    }

    public function testThrowsExceptionWhenTypeIsMissing(): void
    {
        $builder = new FieldDefinitionBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field type is required.');

        $builder->name('test')->build();
    }

    public function testResetClearsAllValues(): void
    {
        $builder = new FieldDefinitionBuilder();

        $builder
            ->name('test')
            ->type(FieldType::TEXT)
            ->addAttribute(new VariantAttribute('size', FieldType::KEYWORD))
            ->reset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name is required.');

        $builder->build();
    }

    public function testResetReturnsBuilder(): void
    {
        $builder = new FieldDefinitionBuilder();

        $this->assertSame($builder, $builder->reset());
    }

    public function testCanReuseBuilderAfterReset(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field1 = $builder
            ->name('field1')
            ->type(FieldType::TEXT)
            ->build();

        $builder->reset();

        $field2 = $builder
            ->name('field2')
            ->type(FieldType::KEYWORD)
            ->build();

        $this->assertEquals('field1', $field1->name);
        $this->assertEquals('field2', $field2->name);
        $this->assertNotSame($field1, $field2);
    }

    public function testMultipleAddAttributeCalls(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('variants')
            ->type(FieldType::VARIANTS)
            ->addAttribute(new VariantAttribute('size', FieldType::KEYWORD))
            ->addAttribute(new VariantAttribute('color', FieldType::TEXT))
            ->addAttribute(new VariantAttribute('material', FieldType::KEYWORD))
            ->build();

        $this->assertCount(3, $field->attributes);
        $this->assertEquals('size', $field->attributes[0]->id);
        $this->assertEquals('color', $field->attributes[1]->id);
        $this->assertEquals('material', $field->attributes[2]->id);
    }

    public function testBuildWithoutAttributesCreatesEmptyArray(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('price')
            ->type(FieldType::DOUBLE)
            ->build();

        $this->assertEquals([], $field->attributes);
    }

    /**
     * Test building Darbo drabuziai fields using the builder.
     */
    public function testBuildingDarboDrabuziaiFieldsWithBuilder(): void
    {
        $builder = new FieldDefinitionBuilder();

        // Build the 'variants' field with attributes
        $variantsField = $builder
            ->name('variants')
            ->type(FieldType::VARIANTS)
            ->addAttribute(new VariantAttribute('size', FieldType::KEYWORD, true))
            ->addAttribute(new VariantAttribute('color', FieldType::KEYWORD, true))
            ->build();

        $expected = [
            'name' => 'variants',
            'type' => 'variants',
            'attributes' => [
                ['id' => 'size', 'type' => 'keyword', 'locale_aware' => true],
                ['id' => 'color', 'type' => 'keyword', 'locale_aware' => true],
            ],
        ];

        $this->assertEquals($expected, $variantsField->jsonSerialize());
    }

    public function testBuilderCanBuildMultipleFieldsSequentially(): void
    {
        $builder = new FieldDefinitionBuilder();

        $idField = $builder
            ->name('id')
            ->type(FieldType::KEYWORD)
            ->build();

        $builder->reset();

        $nameField = $builder
            ->name('name_lt-LT')
            ->type(FieldType::TEXT)
            ->build();

        $builder->reset();

        $priceField = $builder
            ->name('price')
            ->type(FieldType::DOUBLE)
            ->build();

        $this->assertEquals('id', $idField->name);
        $this->assertEquals('keyword', $idField->jsonSerialize()['type']);

        $this->assertEquals('name_lt-LT', $nameField->name);
        $this->assertEquals('text', $nameField->jsonSerialize()['type']);

        $this->assertEquals('price', $priceField->name);
        $this->assertEquals('double', $priceField->jsonSerialize()['type']);
    }

    public function testSearchAnalysisMethodReturnsBuilder(): void
    {
        $builder = new FieldDefinitionBuilder();

        $this->assertSame($builder, $builder->searchAnalysis(SearchAnalysis::FULL));
    }

    public function testBuildWithSearchAnalysis(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('name')
            ->type(FieldType::TEXT)
            ->searchAnalysis(SearchAnalysis::BASIC)
            ->build();

        $this->assertEquals(SearchAnalysis::BASIC, $field->searchAnalysis);
        $this->assertEquals('basic', $field->jsonSerialize()['search_analysis']);
    }

    public function testBuildWithoutSearchAnalysisDefaultsToNull(): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('name')
            ->type(FieldType::TEXT)
            ->build();

        $this->assertNull($field->searchAnalysis);
        $this->assertArrayNotHasKey('search_analysis', $field->jsonSerialize());
    }

    public function testResetClearsSearchAnalysis(): void
    {
        $builder = new FieldDefinitionBuilder();

        $builder
            ->name('name')
            ->type(FieldType::TEXT)
            ->searchAnalysis(SearchAnalysis::FULL)
            ->build();

        $builder->reset();

        $field = $builder
            ->name('name')
            ->type(FieldType::TEXT)
            ->build();

        $this->assertNull($field->searchAnalysis);
    }

    /**
     * @dataProvider fieldTypeDataProvider
     */
    public function testCanBuildAllFieldTypes(FieldType $type, string $expectedValue): void
    {
        $builder = new FieldDefinitionBuilder();

        $field = $builder
            ->name('test')
            ->type($type)
            ->build();

        $this->assertEquals($expectedValue, $field->jsonSerialize()['type']);
    }

    /**
     * @return array<string, array{FieldType, string}>
     */
    public static function fieldTypeDataProvider(): array
    {
        return [
            'text' => [FieldType::TEXT, 'text'],
            'keyword' => [FieldType::KEYWORD, 'keyword'],
            'double' => [FieldType::DOUBLE, 'double'],
            'integer' => [FieldType::INTEGER, 'integer'],
            'boolean' => [FieldType::BOOLEAN, 'boolean'],
            'image_url' => [FieldType::IMAGE_URL, 'image_url'],
            'variants' => [FieldType::VARIANTS, 'variants'],
        ];
    }
}
