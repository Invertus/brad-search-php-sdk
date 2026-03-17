<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Common;

/**
 *
 * @todo https://invertus.atlassian.net/browse/BRD-886 . Currently safer appraoch to normalize here instead of directly
 * in search service.
 *
 * Normalizes short locale codes (e.g., "lt") to full BCP 47 format (e.g., "lt-LT").
 *
 * The normalization map mirrors Go's language_registry.go to ensure the PHP SDK
 * sends locale codes that the Go search service can use for generating
 * language-specific analyzers (stemmers, stop words).
 */
final class LocaleNormalizer
{
    private const NORMALIZATION_MAP = [
        'lt' => 'lt-LT', 'lv' => 'lv-LV', 'et' => 'et-EE',
        'en' => 'en-US',
        'da' => 'da-DK', 'fi' => 'fi-FI', 'nb' => 'nb-NO', 'nn' => 'nn-NO', 'sv' => 'sv-SE',
        'nl' => 'nl-NL', 'fr' => 'fr-FR', 'de' => 'de-DE', 'it' => 'it-IT', 'pt' => 'pt-PT', 'es' => 'es-ES',
        'eu' => 'eu-ES', 'ca' => 'ca-ES', 'gl' => 'gl-ES', 'ga' => 'ga-IE',
        'bg' => 'bg-BG', 'cs' => 'cs-CZ', 'hu' => 'hu-HU', 'ro' => 'ro-RO', 'ru' => 'ru-RU',
        'el' => 'el-GR', 'tr' => 'tr-TR',
        'no' => 'nb-NO',
    ];

    public static function normalize(string $locale): string
    {
        return self::NORMALIZATION_MAP[$locale] ?? $locale;
    }

    /**
     * @param array<string> $locales
     * @return array<string>
     */
    public static function normalizeAll(array $locales): array
    {
        return array_map(self::normalize(...), $locales);
    }
}
