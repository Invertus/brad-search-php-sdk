<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Validators;

use BradSearch\SyncSdk\Validators\DataValidator;
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\Enums\FieldType;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class DataValidatorTest extends TestCase
{
    public function testValidateDatetimeFieldWithValidValue(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
            'updatedAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => '2025-05-16 18:28:58',
            'updatedAt' => '2025-12-16 15:17:11',
        ];

        // Should not throw exception
        $validator->validateProduct($product);
        $this->assertTrue(true);
    }

    public function testValidateDatetimeFieldWithInvalidFormat(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => '2025/05/16 18:28:58', // Wrong format
        ];

        $this->expectException(ValidationException::class);
        $validator->validateProduct($product);
    }

    public function testValidateDatetimeFieldWithNonStringValue(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => 1234567890, // Integer instead of string
        ];

        $this->expectException(ValidationException::class);
        $validator->validateProduct($product);
    }

    public function testValidateDatetimeFieldWithInvalidDate(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => '2025-13-45 99:99:99', // Invalid date values
        ];

        $this->expectException(ValidationException::class);
        $validator->validateProduct($product);
    }

    public function testValidateDatetimeFieldNotRequired(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            // createdAt is not present, which is allowed
        ];

        // Should not throw exception
        $validator->validateProduct($product);
        $this->assertTrue(true);
    }

    public function testValidateDatetimeFieldWithMidnightTime(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => '2025-01-01 00:00:00',
        ];

        // Should not throw exception
        $validator->validateProduct($product);
        $this->assertTrue(true);
    }

    public function testValidateDatetimeFieldWithEndOfDayTime(): void
    {
        $fieldConfig = [
            'id' => FieldConfigBuilder::keyword(),
            'createdAt' => FieldConfigBuilder::datetime(),
        ];

        $validator = new DataValidator($fieldConfig);

        $product = [
            'id' => '123',
            'createdAt' => '2025-12-31 23:59:59',
        ];

        // Should not throw exception
        $validator->validateProduct($product);
        $this->assertTrue(true);
    }
}
