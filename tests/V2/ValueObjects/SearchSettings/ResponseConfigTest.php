<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\HighlightField;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\VariantEnrichmentConfig;
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

    public function testConstructorWithHighlightConfig(): void
    {
        $highlightField = new HighlightField('name', null, ['<em>'], ['</em>']);
        $highlightConfig = new HighlightConfig(true, [$highlightField]);

        $config = new ResponseConfig(
            ['name', 'price'],
            ['price'],
            $highlightConfig
        );

        $this->assertSame($highlightConfig, $config->highlightConfig);
    }

    public function testConstructorWithVariantEnrichment(): void
    {
        $variantEnrichment = new VariantEnrichmentConfig(['price', 'imageUrl']);

        $config = new ResponseConfig(
            ['name', 'price'],
            ['price'],
            null,
            $variantEnrichment
        );

        $this->assertSame($variantEnrichment, $config->variantEnrichment);
    }

    public function testConstructorWithSortableFieldsMap(): void
    {
        $config = new ResponseConfig(
            ['name', 'price'],
            ['price'],
            null,
            null,
            ['price' => 'asc', 'name' => 'desc']
        );

        $this->assertEquals(['price' => 'asc', 'name' => 'desc'], $config->sortableFieldsMap);
    }

    public function testJsonSerializeWithHighlightConfig(): void
    {
        $highlightField = new HighlightField('name', null, ['<em>'], ['</em>']);
        $highlightConfig = new HighlightConfig(true, [$highlightField]);

        $config = new ResponseConfig(['name'], [], $highlightConfig);

        $expected = [
            'source_fields' => ['name'],
            'highlight_config' => [
                'enabled' => true,
                'fields' => [
                    [
                        'field_name' => 'name',
                        'pre_tags' => ['<em>'],
                        'post_tags' => ['</em>'],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithVariantEnrichment(): void
    {
        $variantEnrichment = new VariantEnrichmentConfig(['price', 'imageUrl']);

        $config = new ResponseConfig(['name'], [], null, $variantEnrichment);

        $expected = [
            'source_fields' => ['name'],
            'variant_enrichment' => [
                'replace_fields' => ['price', 'imageUrl'],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithSortableFieldsMap(): void
    {
        $config = new ResponseConfig(
            ['name'],
            ['price'], // This should be overridden by the map
            null,
            null,
            ['price' => 'asc', 'name' => 'desc']
        );

        $expected = [
            'source_fields' => ['name'],
            'sortable_fields' => ['price' => 'asc', 'name' => 'desc'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testFromArrayWithBasicData(): void
    {
        $data = [
            'source_fields' => ['name', 'price'],
            'sortable_fields' => ['price', 'date'],
        ];

        $config = ResponseConfig::fromArray($data);

        $this->assertEquals(['name', 'price'], $config->sourceFields);
        $this->assertEquals(['price', 'date'], $config->sortableFields);
        $this->assertNull($config->highlightConfig);
        $this->assertNull($config->variantEnrichment);
    }

    public function testFromArrayWithHighlightConfig(): void
    {
        $data = [
            'source_fields' => ['name'],
            'highlight_config' => [
                'enabled' => true,
                'fields' => [
                    [
                        'field_name' => 'name',
                        'pre_tags' => ['<mark>'],
                        'post_tags' => ['</mark>'],
                    ],
                ],
            ],
        ];

        $config = ResponseConfig::fromArray($data);

        $this->assertNotNull($config->highlightConfig);
        $this->assertTrue($config->highlightConfig->enabled);
        $this->assertCount(1, $config->highlightConfig->fields);
        $this->assertEquals('name', $config->highlightConfig->fields[0]->fieldName);
    }

    public function testFromArrayWithVariantEnrichment(): void
    {
        $data = [
            'source_fields' => ['name'],
            'variant_enrichment' => [
                'replace_fields' => ['price', 'imageUrl'],
            ],
        ];

        $config = ResponseConfig::fromArray($data);

        $this->assertNotNull($config->variantEnrichment);
        $this->assertEquals(['price', 'imageUrl'], $config->variantEnrichment->replaceFields);
    }

    public function testFromArrayWithSortableFieldsMap(): void
    {
        $data = [
            'source_fields' => ['name'],
            'sortable_fields' => [
                'price' => 'asc',
                'name' => 'desc',
            ],
        ];

        $config = ResponseConfig::fromArray($data);

        $this->assertEquals(['price', 'name'], $config->sortableFields);
        $this->assertEquals(['price' => 'asc', 'name' => 'desc'], $config->sortableFieldsMap);
    }

    public function testWithHighlightConfigReturnsNewInstance(): void
    {
        $config = new ResponseConfig();
        $highlightConfig = new HighlightConfig(true);
        $newConfig = $config->withHighlightConfig($highlightConfig);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->highlightConfig);
        $this->assertSame($highlightConfig, $newConfig->highlightConfig);
    }

    public function testWithVariantEnrichmentReturnsNewInstance(): void
    {
        $config = new ResponseConfig();
        $variantEnrichment = new VariantEnrichmentConfig(['price']);
        $newConfig = $config->withVariantEnrichment($variantEnrichment);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->variantEnrichment);
        $this->assertSame($variantEnrichment, $newConfig->variantEnrichment);
    }

    public function testWithSortableFieldsMapReturnsNewInstance(): void
    {
        $config = new ResponseConfig();
        $newConfig = $config->withSortableFieldsMap(['price' => 'asc']);

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->sortableFieldsMap);
        $this->assertEquals(['price' => 'asc'], $newConfig->sortableFieldsMap);
    }

    public function testRoundTripJsonSerializationWithAllFields(): void
    {
        $originalData = [
            'source_fields' => ['name', 'price'],
            'sortable_fields' => ['price' => 'asc', 'name' => 'desc'],
            'highlight_config' => [
                'enabled' => true,
                'fields' => [
                    [
                        'field_name' => 'name',
                        'locale_suffix' => 'en-US',
                        'pre_tags' => ['<mark>'],
                        'post_tags' => ['</mark>'],
                    ],
                ],
            ],
            'variant_enrichment' => [
                'replace_fields' => ['price', 'imageUrl'],
            ],
        ];

        $config = ResponseConfig::fromArray($originalData);
        $serialized = $config->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
