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
     * Normalizes a single synonym group: Solr-format strings are split on
     * commas, arrays pass through, anything else collapses to an empty group.
     *
     * @return array<int, string>
     */
    private static function normalizeGroup(mixed $group): array
    {
        if (is_string($group)) {
            return array_map('trim', explode(',', $group));
        }

        return is_array($group) ? array_values($group) : [];
    }
}
