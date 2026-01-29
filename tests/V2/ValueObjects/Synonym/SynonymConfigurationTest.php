<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Synonym;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Synonym\SynonymConfiguration;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SynonymConfigurationTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $synonyms = [
            ['laptop', 'notebook', 'computer'],
            ['phone', 'mobile', 'smartphone'],
        ];

        $config = new SynonymConfiguration('en', $synonyms);

        $this->assertEquals('en', $config->language);
        $this->assertEquals($synonyms, $config->synonyms);
    }

    public function testExtendsValueObject(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->assertInstanceOf(ValueObject::class, $config);
    }

    public function testImplementsJsonSerializable(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->assertInstanceOf(JsonSerializable::class, $config);
    }

    /**
     * @dataProvider validLanguageDataProvider
     */
    public function testAcceptsValidLanguageCodes(string $language): void
    {
        $config = new SynonymConfiguration($language, [['test', 'example']]);

        $this->assertEquals($language, $config->language);
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
            'italian' => ['it'],
            'polish' => ['pl'],
            'russian' => ['ru'],
            'chinese' => ['zh'],
            'japanese' => ['ja'],
        ];
    }

    /**
     * @dataProvider invalidLanguageDataProvider
     */
    public function testRejectsInvalidLanguageCodes(string $language): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Language must be a valid ISO 639-1 code (2 lowercase letters)');

        new SynonymConfiguration($language, [['test', 'example']]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLanguageDataProvider(): array
    {
        return [
            'uppercase' => ['EN'],
            'mixed_case' => ['En'],
            'three_letters' => ['eng'],
            'one_letter' => ['e'],
            'empty' => [''],
            'numbers' => ['12'],
            'with_numbers' => ['e1'],
            'with_hyphen' => ['en-US'],
            'with_underscore' => ['en_US'],
            'special_characters' => ['e!'],
        ];
    }

    public function testRejectsEmptySynonymsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonyms array cannot be empty.');

        new SynonymConfiguration('en', []);
    }

    public function testRejectsEmptySynonymGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym group at index 0 cannot be empty.');

        new SynonymConfiguration('en', [[]]);
    }

    public function testRejectsEmptySynonymGroupAtLaterIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym group at index 1 cannot be empty.');

        new SynonymConfiguration('en', [['valid', 'group'], []]);
    }

    public function testRejectsEmptyStringInSynonymGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym term at index [0][1] cannot be empty.');

        new SynonymConfiguration('en', [['laptop', '']]);
    }

    public function testRejectsWhitespaceOnlyStringInSynonymGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym term at index [0][0] cannot be empty.');

        new SynonymConfiguration('en', [['   ', 'laptop']]);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $synonyms = [
            ['laptop', 'notebook'],
            ['phone', 'mobile'],
        ];

        $config = new SynonymConfiguration('en', $synonyms);

        $expected = [
            'language' => 'en',
            'synonyms' => $synonyms,
        ];

        $this->assertEquals($expected, $config->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->assertEquals($config->jsonSerialize(), $config->toArray());
    }

    public function testWithLanguageReturnsNewInstance(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);
        $newConfig = $config->withLanguage('lt');

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals('en', $config->language);
        $this->assertEquals('lt', $newConfig->language);
        $this->assertEquals($config->synonyms, $newConfig->synonyms);
    }

    public function testWithLanguageValidatesNewValue(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Language must be a valid ISO 639-1 code (2 lowercase letters)');

        $config->withLanguage('ENG');
    }

    public function testWithSynonymsReturnsNewInstance(): void
    {
        $originalSynonyms = [['laptop', 'notebook']];
        $newSynonyms = [['phone', 'mobile'], ['tablet', 'pad']];

        $config = new SynonymConfiguration('en', $originalSynonyms);
        $newConfig = $config->withSynonyms($newSynonyms);

        $this->assertNotSame($config, $newConfig);
        $this->assertEquals($originalSynonyms, $config->synonyms);
        $this->assertEquals($newSynonyms, $newConfig->synonyms);
        $this->assertEquals($config->language, $newConfig->language);
    }

    public function testWithSynonymsValidatesNewValue(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonyms array cannot be empty.');

        $config->withSynonyms([]);
    }

    public function testAddSynonymReturnsNewInstance(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);
        $newConfig = $config->addSynonym(['phone', 'mobile']);

        $this->assertNotSame($config, $newConfig);
        $this->assertCount(1, $config->synonyms);
        $this->assertCount(2, $newConfig->synonyms);
        $this->assertEquals(['laptop', 'notebook'], $newConfig->synonyms[0]);
        $this->assertEquals(['phone', 'mobile'], $newConfig->synonyms[1]);
    }

    public function testAddSynonymValidatesNewGroup(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym group at index 1 cannot be empty.');

        $config->addSynonym([]);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $synonyms = [
            ['laptop', 'notebook', 'computer'],
            ['phone', 'mobile', 'smartphone'],
        ];

        $config = new SynonymConfiguration('en', $synonyms);

        $json = json_encode($config);
        $decoded = json_decode($json, true);

        $this->assertEquals('en', $decoded['language']);
        $this->assertEquals($synonyms, $decoded['synonyms']);
    }

    public function testChainedWithMethods(): void
    {
        $config = new SynonymConfiguration('en', [['test', 'example']])
            ->withLanguage('lt')
            ->addSynonym(['laptop', 'notebook'])
            ->addSynonym(['phone', 'mobile']);

        $this->assertEquals('lt', $config->language);
        $this->assertCount(3, $config->synonyms);
    }

    public function testExceptionContainsArgumentNameForLanguage(): void
    {
        try {
            new SynonymConfiguration('ENG', [['test', 'example']]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('language', $e->argumentName);
            $this->assertEquals('ENG', $e->invalidValue);
        }
    }

    public function testExceptionContainsArgumentNameForSynonyms(): void
    {
        try {
            new SynonymConfiguration('en', []);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('synonyms', $e->argumentName);
            $this->assertEquals([], $e->invalidValue);
        }
    }

    /**
     * Test output matches OpenAPI SynonymConfiguration schema structure.
     */
    public function testMatchesSynonymConfigurationSchema(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $serialized = $config->jsonSerialize();

        $this->assertArrayHasKey('language', $serialized);
        $this->assertArrayHasKey('synonyms', $serialized);

        $this->assertIsString($serialized['language']);
        $this->assertIsArray($serialized['synonyms']);
    }

    /**
     * Test against OpenAPI 'ecommerce-en' example.
     *
     * This test verifies that the SynonymConfiguration produces output
     * matching the documented API example.
     */
    public function testMatchesOpenApiEcommerceEnExample(): void
    {
        $config = new SynonymConfiguration('en', [
            ['laptop', 'notebook', 'computer'],
            ['phone', 'mobile', 'smartphone'],
            ['shoes', 'footwear', 'sneakers'],
        ]);

        $serialized = $config->jsonSerialize();

        $expected = [
            'language' => 'en',
            'synonyms' => [
                ['laptop', 'notebook', 'computer'],
                ['phone', 'mobile', 'smartphone'],
                ['shoes', 'footwear', 'sneakers'],
            ],
        ];

        $this->assertEquals($expected, $serialized);

        $json = json_encode($serialized, JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true);

        $this->assertEquals($expected, $decoded);
    }

    public function testAcceptsSingleSynonymGroup(): void
    {
        $config = new SynonymConfiguration('en', [['laptop', 'notebook']]);

        $this->assertCount(1, $config->synonyms);
        $this->assertEquals(['laptop', 'notebook'], $config->synonyms[0]);
    }

    public function testAcceptsTwoTermSynonymGroup(): void
    {
        $config = new SynonymConfiguration('en', [['yes', 'yep']]);

        $this->assertCount(1, $config->synonyms);
        $this->assertEquals(['yes', 'yep'], $config->synonyms[0]);
    }

    public function testAcceptsLargeSynonymGroup(): void
    {
        $synonymGroup = ['red', 'crimson', 'scarlet', 'ruby', 'cherry', 'maroon', 'burgundy'];
        $config = new SynonymConfiguration('en', [$synonymGroup]);

        $this->assertCount(7, $config->synonyms[0]);
    }

    public function testAcceptsManySynonymGroups(): void
    {
        $synonyms = [];
        for ($i = 0; $i < 100; $i++) {
            $synonyms[] = ["term{$i}a", "term{$i}b"];
        }

        $config = new SynonymConfiguration('en', $synonyms);

        $this->assertCount(100, $config->synonyms);
    }

    public function testSynonymsWithSpecialCharacters(): void
    {
        $config = new SynonymConfiguration('en', [
            ['café', 'coffee', 'espresso'],
            ['naïve', 'naive'],
            ['über', 'uber', 'over'],
        ]);

        $this->assertEquals('café', $config->synonyms[0][0]);
        $this->assertEquals('naïve', $config->synonyms[1][0]);
        $this->assertEquals('über', $config->synonyms[2][0]);
    }

    public function testSynonymsWithNumbers(): void
    {
        $config = new SynonymConfiguration('en', [
            ['iphone', 'iphone15', 'iphone 15'],
            ['4k', '4K', 'ultra hd', 'uhd'],
        ]);

        $this->assertCount(2, $config->synonyms);
    }
}
