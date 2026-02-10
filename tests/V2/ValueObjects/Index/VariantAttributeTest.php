<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class VariantAttributeTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD, true);

        $this->assertEquals('size', $attribute->id);
        $this->assertEquals(FieldType::KEYWORD, $attribute->type);
        $this->assertTrue($attribute->localeAware);
    }

    public function testConstructorWithDefaultLocaleAware(): void
    {
        $attribute = new VariantAttribute('color', FieldType::TEXT);

        $this->assertEquals('color', $attribute->id);
        $this->assertEquals(FieldType::TEXT, $attribute->type);
        $this->assertFalse($attribute->localeAware);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant attribute id cannot be empty.');

        new VariantAttribute('', FieldType::KEYWORD);
    }

    public function testExtendsValueObject(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD);

        $this->assertInstanceOf(ValueObject::class, $attribute);
    }

    public function testImplementsJsonSerializable(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD);

        $this->assertInstanceOf(JsonSerializable::class, $attribute);
    }

    public function testJsonSerializeOutputsCorrectStructure(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD, true);

        $expected = [
            'id' => 'size',
            'type' => 'keyword',
            'locale_aware' => true,
        ];

        $this->assertEquals($expected, $attribute->jsonSerialize());
    }

    public function testJsonSerializeWithLocaleAwareFalse(): void
    {
        $attribute = new VariantAttribute('color', FieldType::TEXT, false);

        $expected = [
            'id' => 'color',
            'type' => 'text',
            'locale_aware' => false,
        ];

        $this->assertEquals($expected, $attribute->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $attribute = new VariantAttribute('material', FieldType::KEYWORD);

        $this->assertEquals($attribute->jsonSerialize(), $attribute->toArray());
    }

    public function testWithLocaleAwareReturnsNewInstance(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD, false);
        $newAttribute = $attribute->withLocaleAware(true);

        $this->assertNotSame($attribute, $newAttribute);
        $this->assertFalse($attribute->localeAware);
        $this->assertTrue($newAttribute->localeAware);
        $this->assertEquals('size', $newAttribute->id);
        $this->assertEquals(FieldType::KEYWORD, $newAttribute->type);
    }

    public function testWithTypeReturnsNewInstance(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD);
        $newAttribute = $attribute->withType(FieldType::TEXT);

        $this->assertNotSame($attribute, $newAttribute);
        $this->assertEquals(FieldType::KEYWORD, $attribute->type);
        $this->assertEquals(FieldType::TEXT, $newAttribute->type);
        $this->assertEquals('size', $newAttribute->id);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $attribute = new VariantAttribute('size', FieldType::KEYWORD, true);

        $json = json_encode($attribute);
        $decoded = json_decode($json, true);

        $this->assertEquals('size', $decoded['id']);
        $this->assertEquals('keyword', $decoded['type']);
        $this->assertTrue($decoded['locale_aware']);
    }

    /**
     * @dataProvider validAttributeDataProvider
     */
    public function testAcceptsVariousValidConfigurations(
        string $id,
        FieldType $type,
        bool $localeAware
    ): void {
        $attribute = new VariantAttribute($id, $type, $localeAware);

        $this->assertEquals($id, $attribute->id);
        $this->assertEquals($type, $attribute->type);
        $this->assertEquals($localeAware, $attribute->localeAware);
    }

    /**
     * @return array<string, array{string, FieldType, bool}>
     */
    public static function validAttributeDataProvider(): array
    {
        return [
            'size keyword not locale aware' => ['size', FieldType::KEYWORD, false],
            'color text locale aware' => ['color', FieldType::TEXT, true],
            'price double not locale aware' => ['price', FieldType::DOUBLE, false],
            'stock integer not locale aware' => ['stock', FieldType::INTEGER, false],
            'available boolean not locale aware' => ['available', FieldType::BOOLEAN, false],
        ];
    }
}
