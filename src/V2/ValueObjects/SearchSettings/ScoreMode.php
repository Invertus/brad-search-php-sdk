<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the supported score modes for nested field configurations.
 *
 * These values define how scores from nested documents should be combined.
 */
enum ScoreMode: string
{
    case AVG = 'avg';
    case MAX = 'max';
    case MIN = 'min';
    case SUM = 'sum';
    case NONE = 'none';
}
