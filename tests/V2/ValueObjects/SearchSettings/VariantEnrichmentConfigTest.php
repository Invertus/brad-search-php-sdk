<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\VariantEnrichmentConfig;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class VariantEnrichmentConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new VariantEnrichmentConfig();

        $this->assertEquals([], $config->replaceFields);
    }

    public function testConstructorWithReplaceFields(): void
    {
        $config = new VariantEnrichmentConfig(['price', 'imageUrl', 'productUrl']);

        $this->assertEquals(['price', 'imageUrl', 'productUrl'], $config->replaceFields);
    }

    public function testExtendsValueObject(): void
    {
        $config = new VariantEnrichmentConfig();
        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new VariantEnrichmentConfig();
        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    public function testJsonSerializeWithEmptyReplaceFields(): void
    {
        $config = new VariantEnrichmentConfig();

        $this->assertEquals([], $config->jsonSerialize());
    }

    public function testJsonSerializeWithReplaceFields(): void
    {
        $config = new VariantEnrichmentConfig(['price', 'imageUrl']);

        $expected = [
            'replace_fields' => ['price', 'imageUrl'],
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $config = VariantEnrichmentConfig::fromArray([]);

        $this->assertEquals([], $config->replaceFields);
    }

    public function testFromArrayWithReplaceFields(): void
    {
        $data = [
            'replace_fields' => ['price', 'imageUrl', 'productUrl'],
        ];

        $config = VariantEnrichmentConfig::fromArray($data);

        $this->assertEquals(['price', 'imageUrl', 'productUrl'], $config->replaceFields);
    }

    public function testThrowsExceptionForNonStringReplaceField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Replace field at index 1 must be a string.');

        new VariantEnrichmentConfig(['valid', 123]);
    }

    public function testThrowsExceptionForEmptyReplaceField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Replace field at index 1 cannot be empty.');

        new VariantEnrichmentConfig(['valid', '']);
    }

    public function testWithReplaceFieldsReturnsNewInstance(): void
    {
        $config = new VariantEnrichmentConfig();
        $newConfig = $config->withReplaceFields(['price', 'imageUrl']);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals([], $config->replaceFields);
        $this->assertEquals(['price', 'imageUrl'], $newConfig->replaceFields);
    }

    public function testWithAddedReplaceFieldReturnsNewInstance(): void
    {
        $config = new VariantEnrichmentConfig(['price']);
        $newConfig = $config->withAddedReplaceField('imageUrl');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals(['price'], $config->replaceFields);
        $this->assertEquals(['price', 'imageUrl'], $newConfig->replaceFields);
    }

    public function testRoundTripJsonSerialization(): void
    {
        $originalData = [
            'replace_fields' => ['price', 'imageUrl', 'productUrl'],
        ];

        $config = VariantEnrichmentConfig::fromArray($originalData);
        $serialized = $config->jsonSerialize();

        $this->assertEquals($originalData, $serialized);
    }
}
