<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

/**
 * Enum representing the types of bulk operations supported by the API.
 *
 * Currently supports index_products with extensibility for future operation types.
 */
enum BulkOperationType: string
{
    case INDEX_PRODUCTS = 'index_products';
}
