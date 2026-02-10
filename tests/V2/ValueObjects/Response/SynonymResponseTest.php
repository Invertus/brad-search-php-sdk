<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\SynonymResponse;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SynonymResponseTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $response = new SynonymResponse(
            language: 'en',
            synonymCount: 5,
            requiresReindex: true
        );

        $this->assertEquals('en', $response->language);
        $this->assertEquals(5, $response->synonymCount);
        $this->assertTrue($response->requiresReindex);
        $this->assertNull($response->synonyms);
    }

    public function testConstructorWithSynonyms(): void
    {
        $synonyms = [
            ['laptop', 'notebook', 'computer'],
            ['phone', 'mobile', 'smartphone'],
        ];

        $response = new SynonymResponse(
            language: 'en',
            synonymCount: 2,
            requiresReindex: false,
            synonyms: $synonyms
        );

        $this->assertEquals($synonyms, $response->synonyms);
    }

    public function testExtendsValueObject(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'language' => 'lt',
            'synonym_count' => 10,
            'requires_reindex' => true,
        ];

        $response = SynonymResponse::fromArray($data);

        $this->assertEquals('lt', $response->language);
        $this->assertEquals(10, $response->synonymCount);
        $this->assertTrue($response->requiresReindex);
        $this->assertNull($response->synonyms);
    }

    public function testFromArrayWithSynonyms(): void
    {
        $synonyms = [['test', 'example']];
        $data = [
            'language' => 'en',
            'synonym_count' => 1,
            'requires_reindex' => false,
            'synonyms' => $synonyms,
        ];

        $response = SynonymResponse::fromArray($data);

        $this->assertEquals($synonyms, $response->synonyms);
    }

    public function testFromArrayThrowsOnMissingLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: language');

        SynonymResponse::fromArray([
            'synonym_count' => 5,
            'requires_reindex' => true,
        ]);
    }

    public function testFromArrayThrowsOnMissingSynonymCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: synonym_count');

        SynonymResponse::fromArray([
            'language' => 'en',
            'requires_reindex' => true,
        ]);
    }

    public function testFromArrayThrowsOnMissingRequiresReindex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: requires_reindex');

        SynonymResponse::fromArray([
            'language' => 'en',
            'synonym_count' => 5,
        ]);
    }

    /**
     * @dataProvider validLanguageDataProvider
     */
    public function testAcceptsValidLanguageCodes(string $language): void
    {
        $response = new SynonymResponse($language, 5, true);

        $this->assertEquals($language, $response->language);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validLanguageDataProvider(): array
    {
        return [
            'english' => ['en'],
            'lithuanian' => ['lt'],
            'german' => ['de'],
            'french' => ['fr'],
            'spanish' => ['es'],
        ];
    }

    /**
     * @dataProvider invalidLanguageDataProvider
     */
    public function testRejectsInvalidLanguageCodes(string $language): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Language must be a valid ISO 639-1 code');

        new SynonymResponse($language, 5, true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLanguageDataProvider(): array
    {
        return [
            'uppercase' => ['EN'],
            'three_letters' => ['eng'],
            'one_letter' => ['e'],
            'empty' => [''],
            'with_locale' => ['en-US'],
        ];
    }

    public function testRejectsNegativeSynonymCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('synonym_count must be non-negative');

        new SynonymResponse('en', -1, true);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $expected = [
            'language' => 'en',
            'synonym_count' => 5,
            'requires_reindex' => true,
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testJsonSerializeIncludesSynonyms(): void
    {
        $synonyms = [['test', 'example']];
        $response = new SynonymResponse('en', 1, false, $synonyms);

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('synonyms', $serialized);
        $this->assertEquals($synonyms, $serialized['synonyms']);
    }

    public function testJsonSerializeExcludesNullSynonyms(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $serialized = $response->jsonSerialize();

        $this->assertArrayNotHasKey('synonyms', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'language' => 'en',
            'synonym_count' => 3,
            'requires_reindex' => true,
            'synonyms' => [
                ['laptop', 'notebook', 'computer'],
                ['phone', 'mobile', 'smartphone'],
                ['shoes', 'footwear', 'sneakers'],
            ],
        ];

        $response = SynonymResponse::fromArray($apiResponse);

        $this->assertEquals('en', $response->language);
        $this->assertEquals(3, $response->synonymCount);
        $this->assertTrue($response->requiresReindex);
        $this->assertCount(3, $response->synonyms);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new SynonymResponse('en', 5, true);

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('en', $decoded['language']);
        $this->assertEquals(5, $decoded['synonym_count']);
        $this->assertTrue($decoded['requires_reindex']);
    }

    public function testAcceptsZeroSynonymCount(): void
    {
        $response = new SynonymResponse('en', 0, false);

        $this->assertEquals(0, $response->synonymCount);
    }

    public function testRequiresReindexFalse(): void
    {
        $response = new SynonymResponse('en', 5, false);

        $this->assertFalse($response->requiresReindex);
    }
}
