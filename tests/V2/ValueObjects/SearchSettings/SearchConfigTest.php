<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\NestedFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SearchConfigTest extends TestCase
{
    public function testConstructorWithNoParameters(): void
    {
        $config = new SearchConfig();

        $this->assertEquals([], $config->fields);
        $this->assertEquals([], $config->nestedFields);
        $this->assertEquals([], $config->multiMatchConfigs);
    }

    public function testConstructorWithAllParameters(): void
    {
        $fields = [new FieldConfig('name', 'name')];
        $nestedFields = [new NestedFieldConfig('variants', 'variants')];
        $multiMatchConfigs = [new MultiMatchConfig('multi', ['name'])];

        $config = new SearchConfig($fields, $nestedFields, $multiMatchConfigs);

        $this->assertCount(1, $config->fields);
        $this->assertCount(1, $config->nestedFields);
        $this->assertCount(1, $config->multiMatchConfigs);
    }

    public function testExtendsValueObject(): void
    {
        $config = new SearchConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new SearchConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithEmptyConfig(): void
    {
        $config = new SearchConfig();

        $this->assertEquals([], $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $fields = [new FieldConfig('name_field', 'name', 'en')];
        $nestedFields = [new NestedFieldConfig('variants_config', 'variants')];
        $multiMatchConfigs = [new MultiMatchConfig('multi_name', ['name_field'])];

        $config = new SearchConfig($fields, $nestedFields, $multiMatchConfigs);

        $expected = [
            'fields' => [
                [
                    'id' => 'name_field',
                    'field_name' => 'name',
                    'locale_suffix' => 'en',
                ],
            ],
            'nested_fields' => [
                [
                    'id' => 'variants_config',
                    'path' => 'variants',
                    'score_mode' => 'avg',
                ],
            ],
            'multi_match_configs' => [
                [
                    'id' => 'multi_name',
                    'field_ids' => ['name_field'],
                    'type' => 'best_fields',
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsEmptyArrays(): void
    {
        $fields = [new FieldConfig('name', 'name')];
        $config = new SearchConfig($fields);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('fields', $serialized);
        $this->assertArrayNotHasKey('nested_fields', $serialized);
        $this->assertArrayNotHasKey('multi_match_configs', $serialized);
    }

    public function testThrowsExceptionForInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 1 must be an instance of FieldConfig.');

        new SearchConfig([
            new FieldConfig('valid', 'field'),
            'invalid',
        ]);
    }

    public function testThrowsExceptionForInvalidNestedField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested field at index 0 must be an instance of NestedFieldConfig.');

        new SearchConfig([], ['invalid']);
    }

    public function testThrowsExceptionForInvalidMultiMatchConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multi-match config at index 0 must be an instance of MultiMatchConfig.');

        new SearchConfig([], [], ['invalid']);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $config = new SearchConfig();
        $fields = [new FieldConfig('id', 'name')];
        $newConfig = $config->withFields($fields);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(0, $config->fields);
        $this->assertCount(1, $newConfig->fields);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $config = new SearchConfig([new FieldConfig('field1', 'name1')]);
        $newConfig = $config->withAddedField(new FieldConfig('field2', 'name2'));

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->fields);
        $this->assertCount(2, $newConfig->fields);
    }

    public function testWithNestedFieldsReturnsNewInstance(): void
    {
        $config = new SearchConfig();
        $nestedFields = [new NestedFieldConfig('id', 'path')];
        $newConfig = $config->withNestedFields($nestedFields);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(0, $config->nestedFields);
        $this->assertCount(1, $newConfig->nestedFields);
    }

    public function testWithAddedNestedFieldReturnsNewInstance(): void
    {
        $config = new SearchConfig([], [new NestedFieldConfig('nested1', 'path1')]);
        $newConfig = $config->withAddedNestedField(new NestedFieldConfig('nested2', 'path2'));

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->nestedFields);
        $this->assertCount(2, $newConfig->nestedFields);
    }

    public function testWithMultiMatchConfigsReturnsNewInstance(): void
    {
        $config = new SearchConfig();
        $multiMatchConfigs = [new MultiMatchConfig('id', ['field1'])];
        $newConfig = $config->withMultiMatchConfigs($multiMatchConfigs);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(0, $config->multiMatchConfigs);
        $this->assertCount(1, $newConfig->multiMatchConfigs);
    }

    public function testWithAddedMultiMatchConfigReturnsNewInstance(): void
    {
        $config = new SearchConfig([], [], [new MultiMatchConfig('multi1', ['field1'])]);
        $newConfig = $config->withAddedMultiMatchConfig(new MultiMatchConfig('multi2', ['field2']));

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->multiMatchConfigs);
        $this->assertCount(2, $newConfig->multiMatchConfigs);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new SearchConfig(['invalid']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('fields', $e->argumentName);
        }
    }
}
