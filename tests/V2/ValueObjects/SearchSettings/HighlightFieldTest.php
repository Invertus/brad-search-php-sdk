<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightField;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class HighlightFieldTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $field = new HighlightField('product_name');

        $this->assertEquals('product_name', $field->fieldName);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals([], $field->preTags);
        $this->assertEquals([], $field->postTags);
    }

    public function testConstructorWithAllParameters(): void
    {
        $field = new HighlightField(
            fieldName: 'product_name',
            localeSuffix: 'lt-LT',
            preTags: ['<em>', '<strong>'],
            postTags: ['</em>', '</strong>']
        );

        $this->assertEquals('product_name', $field->fieldName);
        $this->assertEquals('lt-LT', $field->localeSuffix);
        $this->assertEquals(['<em>', '<strong>'], $field->preTags);
        $this->assertEquals(['</em>', '</strong>'], $field->postTags);
    }

    public function testExtendsValueObject(): void
    {
        $field = new HighlightField('name');
        $this->assertInstanceOf(ValueObject::class, $field);
    }

    public function testImplementsJsonSerializable(): void
    {
        $field = new HighlightField('name');
        $this->assertInstanceOf(JsonSerializable::class, $field);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $field = new HighlightField('name');

        $expected = [
            'field_name' => 'name',
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $field = new HighlightField(
            fieldName: 'name',
            localeSuffix: 'en-US',
            preTags: ['<mark>'],
            postTags: ['</mark>']
        );

        $expected = [
            'field_name' => 'name',
            'locale_suffix' => 'en-US',
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>'],
        ];

        $this->assertEquals($expected, $field->jsonSerialize());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'field_name' => 'product_name',
        ];

        $field = HighlightField::fromArray($data);

        $this->assertEquals('product_name', $field->fieldName);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals([], $field->preTags);
        $this->assertEquals([], $field->postTags);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'field_name' => 'product_name',
            'locale_suffix' => 'lt-LT',
            'pre_tags' => ['<em>'],
            'post_tags' => ['</em>'],
        ];

        $field = HighlightField::fromArray($data);

        $this->assertEquals('product_name', $field->fieldName);
        $this->assertEquals('lt-LT', $field->localeSuffix);
        $this->assertEquals(['<em>'], $field->preTags);
        $this->assertEquals(['</em>'], $field->postTags);
    }

    public function testFromArrayThrowsExceptionForMissingFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: field_name');

        HighlightField::fromArray([]);
    }

    public function testThrowsExceptionForEmptyFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        new HighlightField('');
    }

    public function testThrowsExceptionForNonStringPreTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag at index 0 in pre_tags must be a string.');

        new HighlightField('name', null, [123]);
    }

    public function testThrowsExceptionForNonStringPostTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag at index 0 in post_tags must be a string.');

        new HighlightField('name', null, [], [123]);
    }

    public function testWithFieldNameReturnsNewInstance(): void
    {
        $field = new HighlightField('original');
        $newField = $field->withFieldName('updated');

        $this->assertNotSame($field, $newField);
        $this->assertEquals('original', $field->fieldName);
        $this->assertEquals('updated', $newField->fieldName);
    }

    public function testWithLocaleSuffixReturnsNewInstance(): void
    {
        $field = new HighlightField('name');
        $newField = $field->withLocaleSuffix('en-US');

        $this->assertNotSame($field, $newField);
        $this->assertNull($field->localeSuffix);
        $this->assertEquals('en-US', $newField->localeSuffix);
    }

    public function testWithPreTagsReturnsNewInstance(): void
    {
        $field = new HighlightField('name');
        $newField = $field->withPreTags(['<mark>']);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([], $field->preTags);
        $this->assertEquals(['<mark>'], $newField->preTags);
    }

    public function testWithPostTagsReturnsNewInstance(): void
    {
        $field = new HighlightField('name');
        $newField = $field->withPostTags(['</mark>']);

        $this->assertNotSame($field, $newField);
        $this->assertEquals([], $field->postTags);
        $this->assertEquals(['</mark>'], $newField->postTags);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'field_name' => 'product_name',
            'locale_suffix' => 'lt-LT',
            'pre_tags' => ['<em>', '<strong>'],
            'post_tags' => ['</em>', '</strong>'],
        ];

        $field = HighlightField::fromArray($originalData);
        $serialized = $field->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
