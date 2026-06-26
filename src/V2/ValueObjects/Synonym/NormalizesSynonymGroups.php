<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Synonym;

/**
 * Normalizes synonym groups from API payloads into term arrays.
 *
 * The API returns each group as a Solr-format equivalence string
 * ("laptop, notebook"); this trait converts those into term arrays.
 */
trait NormalizesSynonymGroups
{
    /**
     * Normalizes a single synonym group into trimmed string terms: Solr-format
     * strings are split on commas; arrays have their string elements trimmed and
     * non-string elements discarded; anything else collapses to an empty group.
     *
     * @return array<int, string>
     */
    private static function normalizeGroup(mixed $group): array
    {
        if (is_string($group)) {
            return self::trimTerms(explode(',', $group));
        }

        if (!is_array($group)) {
            return [];
        }

        return self::trimTerms(array_map(
            static fn(mixed $term): string => is_string($term) ? $term : '',
            $group
        ));
    }

    /**
     * Trims terms and discards any that are empty after trimming.
     *
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private static function trimTerms(array $terms): array
    {
        return array_values(array_filter(
            array_map('trim', $terms),
            static fn(string $term): bool => $term !== ''
        ));
    }
}
