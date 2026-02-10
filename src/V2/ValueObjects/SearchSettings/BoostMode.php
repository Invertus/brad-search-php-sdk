<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the supported boost modes for function score.
 *
 * These values define how the function score should be combined with the query score.
 */
enum BoostMode: string
{
    case MULTIPLY = 'multiply';
    case REPLACE = 'replace';
    case SUM = 'sum';
    case AVG = 'avg';
    case MAX = 'max';
    case MIN = 'min';
}
