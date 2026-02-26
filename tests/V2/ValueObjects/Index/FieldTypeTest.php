<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Index;

use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use PHPUnit\Framework\TestCase;

class FieldTypeTest extends TestCase
{
    public function testAllExpectedValuesExist(): void
    {
        $expectedValues = ['text', 'keyword', 'double', 'integer', 'boolean', 'image_url', 'variants', 'date'];

        $actualValues = array_map(fn(FieldType $case) => $case->value, FieldType::cases());

        $this->assertEquals($expectedValues, $actualValues);
    }

    public function testTextCaseHasCorrectValue(): void
    {
        $this->assertEquals('text', FieldType::TEXT->value);
    }

    public function testKeywordCaseHasCorrectValue(): void
    {
        $this->assertEquals('keyword', FieldType::KEYWORD->value);
    }

    public function testDoubleCaseHasCorrectValue(): void
    {
        $this->assertEquals('double', FieldType::DOUBLE->value);
    }

    public function testIntegerCaseHasCorrectValue(): void
    {
        $this->assertEquals('integer', FieldType::INTEGER->value);
    }

    public function testBooleanCaseHasCorrectValue(): void
    {
        $this->assertEquals('boolean', FieldType::BOOLEAN->value);
    }

    public function testImageUrlCaseHasCorrectValue(): void
    {
        $this->assertEquals('image_url', FieldType::IMAGE_URL->value);
    }

    public function testVariantsCaseHasCorrectValue(): void
    {
        $this->assertEquals('variants', FieldType::VARIANTS->value);
    }

    public function testDateCaseHasCorrectValue(): void
    {
        $this->assertEquals('date', FieldType::DATE->value);
    }

    public function testCanCreateFromValidString(): void
    {
        $this->assertEquals(FieldType::TEXT, FieldType::from('text'));
        $this->assertEquals(FieldType::KEYWORD, FieldType::from('keyword'));
        $this->assertEquals(FieldType::DOUBLE, FieldType::from('double'));
        $this->assertEquals(FieldType::INTEGER, FieldType::from('integer'));
        $this->assertEquals(FieldType::BOOLEAN, FieldType::from('boolean'));
        $this->assertEquals(FieldType::IMAGE_URL, FieldType::from('image_url'));
        $this->assertEquals(FieldType::VARIANTS, FieldType::from('variants'));
        $this->assertEquals(FieldType::DATE, FieldType::from('date'));
    }

    public function testThrowsExceptionForInvalidString(): void
    {
        $this->expectException(\ValueError::class);

        FieldType::from('invalid_type');
    }

    public function testTryFromReturnsNullForInvalidString(): void
    {
        $this->assertNull(FieldType::tryFrom('invalid_type'));
    }

    public function testTryFromReturnsEnumForValidString(): void
    {
        $this->assertEquals(FieldType::TEXT, FieldType::tryFrom('text'));
    }
}
