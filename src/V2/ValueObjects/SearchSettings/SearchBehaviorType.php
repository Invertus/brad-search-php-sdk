<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the supported search behavior types for search field configurations.
 *
 * These values define how the search engine should match queries against field values.
 */
enum SearchBehaviorType: string
{
    case EXACT = 'exact';
    case MATCH = 'match';
    case FUZZY = 'fuzzy';
    case NGRAM = 'ngram';
    case PHRASE_PREFIX = 'phrase_prefix';
    case PHRASE = 'phrase';
}
