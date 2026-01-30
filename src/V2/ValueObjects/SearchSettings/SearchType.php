<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the supported search types for query fields.
 *
 * These determine how the search query is matched against field values.
 */
enum SearchType: string
{
    case MATCH = 'match';
    case MATCH_FUZZY = 'match-fuzzy';
    case AUTOCOMPLETE = 'autocomplete';
    case EXACT = 'exact';
    case AUTOCOMPLETE_NOSPACE = 'autocomplete-nospace';
    case SUBSTRING = 'substring';
}
