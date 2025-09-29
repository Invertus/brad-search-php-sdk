<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Enums;

enum BulkOperationType: string
{
    case INDEX_PRODUCTS = 'index_products';
    case UPDATE_PRODUCTS = 'update_products';
    case DELETE_PRODUCTS = 'delete_products';
}