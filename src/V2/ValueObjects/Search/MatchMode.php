<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

/**
 * Enum representing the supported match modes for search field configurations.
 *
 * These values correspond to the OpenAPI SearchFieldConfigV2 schema.
 */
enum MatchMode: string
{
    case EXACT = 'exact';
    case FUZZY = 'fuzzy';
    case PHRASE_PREFIX = 'phrase_prefix';
}
