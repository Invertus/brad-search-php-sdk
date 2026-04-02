<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Common;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidLocaleException;
use BradSearch\SyncSdk\V2\ValueObjects\Common\LocalizedField;
use PHPUnit\Framework\TestCase;

class LocalizedFieldTest extends TestCase
{
    public function testConstructorWithValidParameters(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->assertEquals('name', $field->getBaseName());
        $this->assertEquals('lt-LT', $field->getLocale());
    }

    public function testToStringReturnsLocaleSuffixedName(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->assertEquals('name_lt-LT', $field->toString());
    }

    public function testMagicToStringReturnsLocaleSuffixedName(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->assertEquals('name_lt-LT', (string) $field);
    }

    public function testGetBaseNameReturnsBaseName(): void
    {
        $field = new LocalizedField('description', 'en-US');

        $this->assertEquals('description', $field->getBaseName());
    }

    public function testGetLocaleReturnsLocale(): void
    {
        $field = new LocalizedField('title', 'de-DE');

        $this->assertEquals('de-DE', $field->getLocale());
    }

    public function testWithLocaleReturnsNewInstanceWithDifferentLocale(): void
    {
        $field = new LocalizedField('name', 'lt-LT');
        $newField = $field->withLocale('en-US');

        $this->assertEquals('name', $newField->getBaseName());
        $this->assertEquals('en-US', $newField->getLocale());
        $this->assertEquals('name_en-US', $newField->toString());
    }

    public function testWithLocaleDoesNotModifyOriginalInstance(): void
    {
        $field = new LocalizedField('name', 'lt-LT');
        $field->withLocale('en-US');

        $this->assertEquals('lt-LT', $field->getLocale());
        $this->assertEquals('name_lt-LT', $field->toString());
    }

    public function testWithLocaleReturnsNewInstance(): void
    {
        $field = new LocalizedField('name', 'lt-LT');
        $newField = $field->withLocale('en-US');

        $this->assertNotSame($field, $newField);
    }

    public function testThrowsExceptionForEmptyBaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base field name cannot be empty.');

        new LocalizedField('', 'lt-LT');
    }

    public function testThrowsExceptionForInvalidLocaleFormat(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'invalid'. Locale must match pattern 'xx' or 'xx-XX'");

        new LocalizedField('name', 'invalid');
    }

    public function testThrowsExceptionForEmptyLocale(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: ''");

        new LocalizedField('name', '');
    }

    public function testThrowsExceptionForLocaleWithLowercaseCountry(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'en-us'");

        new LocalizedField('name', 'en-us');
    }

    public function testThrowsExceptionForLocaleWithUppercaseLanguage(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'EN-US'");

        new LocalizedField('name', 'EN-US');
    }

    public function testThrowsExceptionForLocaleWithUnderscore(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'en_US'");

        new LocalizedField('name', 'en_US');
    }

    public function testThrowsExceptionForLocaleWithExtraCharacters(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'en-USA'");

        new LocalizedField('name', 'en-USA');
    }

    public function testThrowsExceptionForLocaleTooShort(): void
    {
        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'e-US'");

        new LocalizedField('name', 'e-US');
    }

    public function testWithLocaleThrowsExceptionForInvalidLocale(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage("Invalid locale: 'invalid'");

        $field->withLocale('invalid');
    }

    /**
     * @dataProvider validLocalesProvider
     */
    public function testAcceptsValidLocales(string $locale, string $expectedLocale): void
    {
        $field = new LocalizedField('name', $locale);

        $this->assertEquals($expectedLocale, $field->getLocale());
        $this->assertEquals('name_' . $expectedLocale, $field->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validLocalesProvider(): array
    {
        return [
            'Lithuanian' => ['lt-LT', 'lt-LT'],
            'English US' => ['en-US', 'en-US'],
            'English GB' => ['en-GB', 'en-GB'],
            'German' => ['de-DE', 'de-DE'],
            'French' => ['fr-FR', 'fr-FR'],
            'Spanish' => ['es-ES', 'es-ES'],
            'Polish' => ['pl-PL', 'pl-PL'],
            'Russian' => ['ru-RU', 'ru-RU'],
            'Chinese' => ['zh-CN', 'zh-CN'],
            'Japanese' => ['ja-JP', 'ja-JP'],
            'Shorthand English' => ['en', 'en'],
            'Shorthand Lithuanian' => ['lt', 'lt'],
            'Shorthand German' => ['de', 'de'],
            'Shorthand French' => ['fr', 'fr'],
            'Shorthand Spanish' => ['es', 'es'],
        ];
    }

    /**
     * @dataProvider invalidLocalesProvider
     */
    public function testRejectsInvalidLocales(string $locale): void
    {
        $this->expectException(InvalidLocaleException::class);

        new LocalizedField('name', $locale);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLocalesProvider(): array
    {
        return [
            'empty' => [''],
            'lowercase country' => ['en-us'],
            'uppercase language' => ['EN-US'],
            'underscore separator' => ['en_US'],
            'no separator' => ['enUS'],
            'single letter language' => ['e-US'],
            'single letter country' => ['en-U'],
            'three letter country' => ['en-USA'],
            'three letter language' => ['eng-US'],
            'numbers' => ['e1-US'],
            'special characters' => ['en@US'],
            'too short' => ['e-U'],
            'wrong format' => ['english-US'],
        ];
    }

    public function testMultipleFieldsCanBeCreated(): void
    {
        $nameField = new LocalizedField('name', 'lt-LT');
        $descField = new LocalizedField('description', 'lt-LT');
        $titleField = new LocalizedField('title', 'en-US');

        $this->assertEquals('name_lt-LT', $nameField->toString());
        $this->assertEquals('description_lt-LT', $descField->toString());
        $this->assertEquals('title_en-US', $titleField->toString());
    }

    public function testFieldWithComplexBaseName(): void
    {
        $field = new LocalizedField('long_description', 'lt-LT');

        $this->assertEquals('long_description', $field->getBaseName());
        $this->assertEquals('long_description_lt-LT', $field->toString());
    }

    public function testFieldImplementsStringable(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->assertInstanceOf(\Stringable::class, $field);
    }

    public function testShortLocaleIsNormalized(): void
    {
        $field = new LocalizedField('name', 'lt');

        $this->assertEquals('lt', $field->getLocale());
        $this->assertEquals('name_lt', $field->toString());
    }

    public function testShortLocaleNormalizationInStringContext(): void
    {
        $field = new LocalizedField('description', 'en');

        $this->assertEquals('en', $field->getLocale());
        $this->assertEquals('description_en', (string) $field);
    }

    public function testFullLocaleRemainsUnchanged(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $this->assertEquals('lt-LT', $field->getLocale());
        $this->assertEquals('name_lt-LT', $field->toString());
    }

    public function testCanUseInStringContext(): void
    {
        $field = new LocalizedField('name', 'lt-LT');

        $result = "Field: {$field}";

        $this->assertEquals('Field: name_lt-LT', $result);
    }
}
