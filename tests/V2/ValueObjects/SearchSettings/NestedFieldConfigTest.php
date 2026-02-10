<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\NestedFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoreMode;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class NestedFieldConfigTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $config = new NestedFieldConfig('variants_config', 'variants');

        $this->assertEquals('variants_config', $config->id);
        $this->assertEquals('variants', $config->path);
        $this->assertNull($config->localeSuffix);
        $this->assertEquals(ScoreMode::AVG, $config->scoreMode);
        $this->assertEquals([], $config->fields);
    }

    public function testConstructorWithAllParameters(): void
    {
        $fields = [
            new FieldConfig('variant_name', 'name'),
            new FieldConfig('variant_sku', 'sku'),
        ];

        $config = new NestedFieldConfig(
            'variants_config',
            'variants',
            'en',
            ScoreMode::MAX,
            $fields
        );

        $this->assertEquals('variants_config', $config->id);
        $this->assertEquals('variants', $config->path);
        $this->assertEquals('en', $config->localeSuffix);
        $this->assertEquals(ScoreMode::MAX, $config->scoreMode);
        $this->assertCount(2, $config->fields);
    }

    public function testExtendsValueObject(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithMinimalConfig(): void
    {
        $config = new NestedFieldConfig('variants_config', 'variants');

        $expected = [
            'id' => 'variants_config',
            'path' => 'variants',
            'score_mode' => 'avg',
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeWithFullConfig(): void
    {
        $fields = [
            new FieldConfig('variant_name', 'name', 'en'),
        ];

        $config = new NestedFieldConfig(
            'variants_config',
            'variants',
            'en',
            ScoreMode::SUM,
            $fields
        );

        $expected = [
            'id' => 'variants_config',
            'path' => 'variants',
            'score_mode' => 'sum',
            'locale_suffix' => 'en',
            'fields' => [
                [
                    'id' => 'variant_name',
                    'field_name' => 'name',
                    'locale_suffix' => 'en',
                ],
            ],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testJsonSerializeOmitsEmptyFields(): void
    {
        $config = new NestedFieldConfig('id', 'path', 'en');

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('fields', $serialized);
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested field config id cannot be empty.');

        new NestedFieldConfig('', 'path');
    }

    public function testThrowsExceptionForEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested field path cannot be empty.');

        new NestedFieldConfig('id', '');
    }

    public function testThrowsExceptionForInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field at index 1 must be an instance of FieldConfig.');

        new NestedFieldConfig('id', 'path', null, ScoreMode::AVG, [
            new FieldConfig('valid', 'field'),
            'invalid',
        ]);
    }

    public function testWithIdReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $newConfig = $config->withId('new_id');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('id', $config->id);
        $this->assertEquals('new_id', $newConfig->id);
    }

    public function testWithPathReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $newConfig = $config->withPath('new_path');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('path', $config->path);
        $this->assertEquals('new_path', $newConfig->path);
    }

    public function testWithLocaleSuffixReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $newConfig = $config->withLocaleSuffix('lt');

        $this->assertNotSame($config, $newConfig);
        $this->assertNull($config->localeSuffix);
        $this->assertEquals('lt', $newConfig->localeSuffix);
    }

    public function testWithScoreModeReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $newConfig = $config->withScoreMode(ScoreMode::MAX);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(ScoreMode::AVG, $config->scoreMode);
        $this->assertEquals(ScoreMode::MAX, $newConfig->scoreMode);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path');
        $fields = [new FieldConfig('field_id', 'field_name')];
        $newConfig = $config->withFields($fields);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(0, $config->fields);
        $this->assertCount(1, $newConfig->fields);
    }

    public function testWithAddedFieldReturnsNewInstance(): void
    {
        $config = new NestedFieldConfig('id', 'path', null, ScoreMode::AVG, [
            new FieldConfig('field1', 'name1'),
        ]);
        $newConfig = $config->withAddedField(new FieldConfig('field2', 'name2'));

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->fields);
        $this->assertCount(2, $newConfig->fields);
    }

    public function testAllScoreModesAreValid(): void
    {
        $scoreModes = [ScoreMode::AVG, ScoreMode::MAX, ScoreMode::MIN, ScoreMode::SUM, ScoreMode::NONE];

        foreach ($scoreModes as $scoreMode) {
            $config = new NestedFieldConfig('id', 'path', null, $scoreMode);
            $this->assertEquals($scoreMode, $config->scoreMode);
        }
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new NestedFieldConfig('', 'path');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('id', $e->argumentName);
            $this->assertEquals('', $e->invalidValue);
        }
    }
}
