<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

/**
 * Enum representing the supported fuzzy matching modes.
 *
 * These values correspond to the OpenAPI FuzzyMatchingConfig schema.
 */
enum FuzzyMode: string
{
    case AUTO = 'auto';
    case FIXED = 'fixed';
}
