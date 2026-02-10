<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the supported function score modifiers.
 *
 * These values define how the field value should be modified before being used as a score factor.
 */
enum FunctionScoreModifier: string
{
    case NONE = 'none';
    case LOG = 'log';
    case LOG1P = 'log1p';
    case LOG2P = 'log2p';
    case LN = 'ln';
    case LN1P = 'ln1p';
    case LN2P = 'ln2p';
    case SQUARE = 'square';
    case SQRT = 'sqrt';
    case RECIPROCAL = 'reciprocal';
}
