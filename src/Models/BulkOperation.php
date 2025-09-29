<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

use BradSearch\SyncSdk\Enums\BulkOperationType;

class BulkOperation
{
    public function __construct(
        public readonly BulkOperationType $type,
        public readonly array $payload
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => $this->payload
        ];
    }

    public static function indexProducts(string $indexName, array $products, array $subfields = [], array $embeddableFields = []): self
    {
        $payload = [
            'index_name' => $indexName,
            'products' => $products
        ];

        if (!empty($subfields)) {
            $payload['subfields'] = $subfields;
        }

        if (!empty($embeddableFields)) {
            $payload['embeddablefields'] = $embeddableFields;
        }

        return new self(BulkOperationType::INDEX_PRODUCTS, $payload);
    }

    public static function updateProducts(string $indexName, array $updates): self
    {
        return new self(BulkOperationType::UPDATE_PRODUCTS, [
            'index_name' => $indexName,
            'updates' => $updates
        ]);
    }

    public static function deleteProducts(string $indexName, array $productIds): self
    {
        return new self(BulkOperationType::DELETE_PRODUCTS, [
            'index_name' => $indexName,
            'product_ids' => $productIds
        ]);
    }
}