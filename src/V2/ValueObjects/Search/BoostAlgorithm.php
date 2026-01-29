<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

/**
 * Enum representing the supported popularity boost algorithms.
 *
 * These values correspond to the OpenAPI PopularityBoostConfig schema.
 */
enum BoostAlgorithm: string
{
    case LOGARITHMIC = 'logarithmic';
    case LINEAR = 'linear';
    case SQUARE_ROOT = 'square_root';
}
