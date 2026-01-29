<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ResponseConfigTest extends TestCase
{
    public function testConstructorWithNoParameters(): void
    {
        $config = new ResponseConfig();

        $this->assertEquals([], $config->sourceFields);
        $this->assertEquals([], $config->sortableFields);
    }

    public function testConstructorWithAllParameters(): void
    {
        $config = new ResponseConfig(
            ['name', 'price', 'description'],
            ['price', 'created_at']
        );

        $this->assertEquals(['name', 'price', 'description'], $config->sourceFields);
        $this->assertEquals(['price', 'created_at'], $config->sortableFields);
    }

    public function testExtendsValueObject(): void
    {
        $config = new ResponseConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new ResponseConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithEmptyConfig(): void
    {
        $config = new ResponseConfig();

        $this->assertEquals([], $config->jsonSerialize());
    }

    public function testJsonSerializeWithSourceFieldsOnly(): void
    {
        $config = new ResponseConfig(['name', 'price']);

        $expected = [
            'source_fields' => ['name', 'price'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithSortableFieldsOnly(): void
    {
        $config = new ResponseConfig([], ['price', 'date']);

        $expected = [
            'sortable_fields' => ['price', 'date'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $config = new ResponseConfig(['name', 'price'], ['price', 'date']);

        $expected = [
            'source_fields' => ['name', 'price'],
            'sortable_fields' => ['price', 'date'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testThrowsExceptionForNonStringSourceField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source field at index 1 must be a string.');

        new ResponseConfig(['valid', 123]);
    }

    public function testThrowsExceptionForEmptySourceField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source field at index 1 cannot be empty.');

        new ResponseConfig(['valid', '']);
    }

    public function testThrowsExceptionForNonStringSortableField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sortable field at index 0 must be a string.');

        new ResponseConfig([], [123]);
    }

    public function testThrowsExceptionForEmptySortableField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sortable field at index 0 cannot be empty.');

        new ResponseConfig([], ['']);
    }

    public function testWithSourceFieldsReturnsNewInstance(): void
    {
        $config = new ResponseConfig();
        $newConfig = $config->withSourceFields(['name', 'price']);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->sourceFields);
        $this->assertEquals(['name', 'price'], $newConfig->sourceFields);
    }

    public function testWithAddedSourceFieldReturnsNewInstance(): void
    {
        $config = new ResponseConfig(['name']);
        $newConfig = $config->withAddedSourceField('price');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['name'], $config->sourceFields);
        $this->assertEquals(['name', 'price'], $newConfig->sourceFields);
    }

    public function testWithSortableFieldsReturnsNewInstance(): void
    {
        $config = new ResponseConfig();
        $newConfig = $config->withSortableFields(['price', 'date']);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->sortableFields);
        $this->assertEquals(['price', 'date'], $newConfig->sortableFields);
    }

    public function testWithAddedSortableFieldReturnsNewInstance(): void
    {
        $config = new ResponseConfig([], ['price']);
        $newConfig = $config->withAddedSortableField('date');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['price'], $config->sortableFields);
        $this->assertEquals(['price', 'date'], $newConfig->sortableFields);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new ResponseConfig([123]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('source_fields', $e->argumentName);
            $this->assertEquals(123, $e->invalidValue);
        }
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new ResponseConfig(['name'], ['price']);
        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }
}
