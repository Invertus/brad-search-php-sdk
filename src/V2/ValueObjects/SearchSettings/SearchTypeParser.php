<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Turns a raw search type entry into a SearchType case, or keeps it as a string.
 *
 * The engine accepts boost-modifier forms the enum cannot express ("exact_300",
 * "match-fuzzy_50"), so an unknown-but-plausible string is kept verbatim instead of
 * throwing: refusing to read a configuration the engine happily serves would make the
 * SDK unusable on real tenants, and the string round-trips unchanged.
 */
final class SearchTypeParser
{
    /**
     * @throws InvalidArgumentException If the entry is not a non-empty string
     */
    public static function parse(mixed $value, string $field = 'searchTypes'): SearchType|string
    {
        if ($value instanceof SearchType) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(
                sprintf('Search type must be a non-empty string, %s given.', get_debug_type($value)),
                $field,
                $value
            );
        }

        return SearchType::tryFrom($value) ?? $value;
    }
}
