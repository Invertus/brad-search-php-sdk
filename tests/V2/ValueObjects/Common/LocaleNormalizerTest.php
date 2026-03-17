<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Common;

use BradSearch\SyncSdk\V2\ValueObjects\Common\LocaleNormalizer;
use PHPUnit\Framework\TestCase;

class LocaleNormalizerTest extends TestCase
{
    /**
     * @dataProvider shortCodeProvider
     */
    public function testNormalizeShortCode(string $short, string $expected): void
    {
        $this->assertEquals($expected, LocaleNormalizer::normalize($short));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function shortCodeProvider(): array
    {
        return [
            'lithuanian' => ['lt', 'lt-LT'],
            'english' => ['en', 'en-US'],
            'danish' => ['da', 'da-DK'],
            'swedish' => ['sv', 'sv-SE'],
            'latvian' => ['lv', 'lv-LV'],
            'estonian' => ['et', 'et-EE'],
            'finnish' => ['fi', 'fi-FI'],
            'norwegian bokmål' => ['nb', 'nb-NO'],
            'norwegian nynorsk' => ['nn', 'nn-NO'],
            'dutch' => ['nl', 'nl-NL'],
            'french' => ['fr', 'fr-FR'],
            'german' => ['de', 'de-DE'],
            'italian' => ['it', 'it-IT'],
            'portuguese' => ['pt', 'pt-PT'],
            'spanish' => ['es', 'es-ES'],
            'basque' => ['eu', 'eu-ES'],
            'catalan' => ['ca', 'ca-ES'],
            'galician' => ['gl', 'gl-ES'],
            'irish' => ['ga', 'ga-IE'],
            'bulgarian' => ['bg', 'bg-BG'],
            'czech' => ['cs', 'cs-CZ'],
            'hungarian' => ['hu', 'hu-HU'],
            'romanian' => ['ro', 'ro-RO'],
            'russian' => ['ru', 'ru-RU'],
            'greek' => ['el', 'el-GR'],
            'turkish' => ['tr', 'tr-TR'],
        ];
    }

    public function testNormalizeAlreadyFull(): void
    {
        $this->assertEquals('lt-LT', LocaleNormalizer::normalize('lt-LT'));
        $this->assertEquals('en-US', LocaleNormalizer::normalize('en-US'));
        $this->assertEquals('de-DE', LocaleNormalizer::normalize('de-DE'));
    }

    public function testNormalizeUnknownPassesThrough(): void
    {
        $this->assertEquals('xx', LocaleNormalizer::normalize('xx'));
        $this->assertEquals('pl-PL', LocaleNormalizer::normalize('pl-PL'));
    }

    public function testNormalizeNorwegianAlias(): void
    {
        $this->assertEquals('nb-NO', LocaleNormalizer::normalize('no'));
    }

    public function testNormalizeAll(): void
    {
        $input = ['lt', 'en-US', 'de'];
        $expected = ['lt-LT', 'en-US', 'de-DE'];

        $this->assertEquals($expected, LocaleNormalizer::normalizeAll($input));
    }

    public function testNormalizeAllEmpty(): void
    {
        $this->assertEquals([], LocaleNormalizer::normalizeAll([]));
    }
}
