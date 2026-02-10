<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

/**
 * Enum representing the types of bulk operations supported by the API.
 */
enum BulkOperationType: string
{
    case INDEX_PRODUCTS = 'index_products';
    case UPDATE_PRODUCTS = 'update_products';
    case DELETE_PRODUCTS = 'delete_products';
}
