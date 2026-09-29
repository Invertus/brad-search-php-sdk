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
    case STEMMED = 'stemmed';
    case STEMMED_FUZZY = 'stemmed-fuzzy';
    case SPLIT_ALPHA_NUM = 'split-alpha-num';
    case SPLIT_ALPHA_NUM_FUZZY = 'split-alpha-num-fuzzy';
    case EXACT = 'exact';
    case AUTOCOMPLETE = 'autocomplete';
    case AUTOCOMPLETE_NOSPACE = 'autocomplete-nospace';
    case SUBSTRING = 'substring';
    case SUBSTRING_NOSPACE = 'substring-nospace';
    case PHRASE_PREFIX = 'phrase-prefix';
    case PHRASE_PREFIX_STEMMED = 'phrase-prefix-stemmed';
    case PHONETIC = 'phonetic';
    case SYNONYM = 'synonym';
}
