<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightField;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class HighlightConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new HighlightConfig();

        $this->assertFalse($config->enabled);
        $this->assertEquals([], $config->fields);
    }

    public function testConstructorWithAllParameters(): void
    {
        $field = new HighlightField('name', 'en-US', ['<em>'], ['</em>']);

        $config = new HighlightConfig(
            enabled: true,
            fields: [$field]
        );

        $this->assertTrue($config->enabled);
        $this->assertCount(1, $config->fields);
        $this->assertEquals('name', $config->fields[0]->fieldName);
    }

    public function testExtendsValueObject(): void
    {
        $config = new HighlightConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new HighlightConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithDefaults(): void
    {
        $config = new HighlightConfig();

        $expected = [
            'enabled' => false,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $field = new HighlightField('name', 'en-US', ['<mark>'], ['</mark>']);
        $config = new HighlightConfig(true, [$field]);

        $expected = [
            'enabled' => true,
            'fields' => [
                [
                    'field_name' => 'name',
                    'locale_suffix' => 'en-US',
                    'pre_tags' => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $config = HighlightConfig::fromArray([]);

        $this->assertFalse($config->enabled);
        $this->assertEquals([], $config->fields);
    }

    public function testFromArrayWithEnabledOnly(): void
    {
        $data = [
            'enabled' => true,
        ];

        $config = HighlightConfig::fromArray($data);

        $this->assertTrue($config->enabled);
        $this->assertEquals([], $config->fields);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'enabled' => true,
            'fields' => [
                [
                    'field_name' => 'product_name',
                    'locale_suffix' => 'lt-LT',
                    'pre_tags' => ['<em>'],
                    'post_tags' => ['</em>'],
                ],
                [
                    'field_name' => 'description',
                ],
            ],
        ];

        $config = HighlightConfig::fromArray($data);

        $this->assertTrue($config->enabled);
        $this->assertCount(2, $config->fields);
        $this->assertEquals('product_name', $config->fields[0]->fieldName);
        $this->assertEquals('lt-LT', $config->fields[0]->localeSuffix);
        $this->assertEquals('description', $config->fields[1]->fieldName);
        $this->assertNull($config->fields[1]->localeSuffix);
    }

    public function testThrowsExceptionForInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 0 must be an instance of HighlightField.');

        new HighlightConfig(true, ['invalid']);
    }

    public function testWithEnabledReturnsNewInstance(): void
    {
        $config = new HighlightConfig(false);
        $newConfig = $config->withEnabled(true);

        $this->assertNotSame($config, $newConfig);
        $this->assertFalse($config->enabled);
        $this->assertTrue($newConfig->enabled);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $field = new HighlightField('name');
        $config = new HighlightConfig();
        $newConfig = $config->withFields([$field]);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->fields);
        $this->assertCount(1, $newConfig->fields);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $field1 = new HighlightField('name');
        $field2 = new HighlightField('description');

        $config = new HighlightConfig(true, [$field1]);
        $newConfig = $config->withAddedField($field2);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->fields);
        $this->assertCount(2, $newConfig->fields);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'enabled' => true,
            'fields' => [
                [
                    'field_name' => 'product_name',
                    'locale_suffix' => 'en-US',
                    'pre_tags' => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ],
            ],
        ];

        $config = HighlightConfig::fromArray($originalData);
        $serialized = $config->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
