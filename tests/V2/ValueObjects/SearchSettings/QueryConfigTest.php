<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryField;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryFieldType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchType;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class QueryConfigTest extends TestCase
{
    public function testConstructorWithNoParameters(): void
    {
        $config = new QueryConfig();

        $this->assertEquals([], $config->fields);
        $this->assertEquals([], $config->crossFieldsMatching);
    }

    public function testConstructorWithAllParameters(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');

        $config = new QueryConfig(
            [$field],
            ['name', 'description']
        );

        $this->assertCount(1, $config->fields);
        $this->assertEquals(['name', 'description'], $config->crossFieldsMatching);
    }

    public function testExtendsValueObject(): void
    {
        $config = new QueryConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new QueryConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithEmptyConfig(): void
    {
        $config = new QueryConfig();

        $this->assertEquals([], $config->jsonSerialize());
    }

    public function testJsonSerializeWithFieldsOnly(): void
    {
        $field = new QueryField(
            type: QueryFieldType::TEXT,
            name: 'product_name',
            searchTypes: [SearchType::MATCH]
        );
        $config = new QueryConfig([$field]);

        $expected = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'product_name',
                    'search_types' => ['match'],
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithCrossFieldsMatchingOnly(): void
    {
        $config = new QueryConfig([], ['name', 'brand']);

        $expected = [
            'cross_fields_matching' => ['name', 'brand'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $config = new QueryConfig([$field], ['name', 'brand']);

        $expected = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'name',
                ],
            ],
            'cross_fields_matching' => ['name', 'brand'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $config = QueryConfig::fromArray([]);

        $this->assertEquals([], $config->fields);
        $this->assertEquals([], $config->crossFieldsMatching);
    }

    public function testFromArrayWithFields(): void
    {
        $data = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'product_name',
                    'search_types' => ['match', 'autocomplete'],
                ],
                [
                    'type' => 'text',
                    'name' => 'brand',
                ],
            ],
        ];

        $config = QueryConfig::fromArray($data);

        $this->assertCount(2, $config->fields);
        $this->assertEquals('product_name', $config->fields[0]->name);
        $this->assertEquals([SearchType::MATCH, SearchType::AUTOCOMPLETE], $config->fields[0]->searchTypes);
        $this->assertEquals('brand', $config->fields[1]->name);
    }

    public function testFromArrayWithCrossFieldsMatching(): void
    {
        $data = [
            'cross_fields_matching' => ['name', 'description', 'brand'],
        ];

        $config = QueryConfig::fromArray($data);

        $this->assertEquals(['name', 'description', 'brand'], $config->crossFieldsMatching);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'locale_suffix' => 'lt-LT',
                    'search_types' => ['match', 'autocomplete'],
                ],
            ],
            'cross_fields_matching' => ['name', 'brand'],
        ];

        $config = QueryConfig::fromArray($data);

        $this->assertCount(1, $config->fields);
        $this->assertEquals('name', $config->fields[0]->name);
        $this->assertEquals('lt-LT', $config->fields[0]->localeSuffix);
        $this->assertEquals(['name', 'brand'], $config->crossFieldsMatching);
    }

    public function testThrowsExceptionForInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 0 must be an instance of QueryField.');

        new QueryConfig(['invalid']);
    }

    public function testThrowsExceptionForNonStringCrossFieldsMatching(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-fields matching entry at index 1 must be a string.');

        new QueryConfig([], ['valid', 123]);
    }

    public function testThrowsExceptionForEmptyCrossFieldsMatching(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-fields matching entry at index 0 cannot be empty.');

        new QueryConfig([], ['']);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $config = new QueryConfig();
        $field = new QueryField(QueryFieldType::TEXT, 'name');
        $newConfig = $config->withFields([$field]);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->fields);
        $this->assertCount(1, $newConfig->fields);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $field1 = new QueryField(QueryFieldType::TEXT, 'name');
        $field2 = new QueryField(QueryFieldType::TEXT, 'brand');

        $config = new QueryConfig([$field1]);
        $newConfig = $config->withAddedField($field2);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->fields);
        $this->assertCount(2, $newConfig->fields);
    }

    public function testWithCrossFieldsMatchingReturnsNewInstance(): void
    {
        $config = new QueryConfig();
        $newConfig = $config->withCrossFieldsMatching(['name', 'brand']);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->crossFieldsMatching);
        $this->assertEquals(['name', 'brand'], $newConfig->crossFieldsMatching);
    }

    public function testWithAddedCrossFieldsMatchingReturnsNewInstance(): void
    {
        $config = new QueryConfig([], ['name']);
        $newConfig = $config->withAddedCrossFieldsMatching('brand');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['name'], $config->crossFieldsMatching);
        $this->assertEquals(['name', 'brand'], $newConfig->crossFieldsMatching);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'product_name',
                    'locale_suffix' => 'en-US',
                    'search_types' => ['match', 'autocomplete'],
                ],
                [
                    'type' => 'text',
                    'name' => 'brand',
                ],
            ],
            'cross_fields_matching' => ['product_name', 'brand'],
        ];

        $config = QueryConfig::fromArray($originalData);
        $serialized = $config->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
