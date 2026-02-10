<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

/**
 * Enum representing the types of query fields in search configuration.
 *
 * - TEXT: Standard text field for searching
 * - NESTED: Nested field containing sub-fields (e.g., variants)
 */
enum QueryFieldType: string
{
    case TEXT = 'text';
    case NESTED = 'nested';
}
