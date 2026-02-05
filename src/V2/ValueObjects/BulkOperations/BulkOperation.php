<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single bulk operation to be executed.
 *
 * This immutable ValueObject combines an operation type with its payload.
 */
final readonly class BulkOperation extends ValueObject
{
    /**
     * @param BulkOperationType $type The type of bulk operation
     * @param IndexProductsPayload|DeleteProductsPayload $payload The operation payload
     */
    public function __construct(
        public BulkOperationType $type,
        public IndexProductsPayload|DeleteProductsPayload $payload
    ) {
    }

    /**
     * Creates an index_products operation.
     *
     * @param array<int, Product> $products Products to index
     */
    public static function indexProducts(array $products): self
    {
        return new self(
            BulkOperationType::INDEX_PRODUCTS,
            new IndexProductsPayload($products)
        );
    }

    /**
     * Creates a delete_products operation.
     *
     * @param array<int, string> $productIds Product IDs to delete
     */
    public static function deleteProducts(array $productIds): self
    {
        return new self(
            BulkOperationType::DELETE_PRODUCTS,
            new DeleteProductsPayload($productIds)
        );
    }

    /**
     * Returns a new instance with a different type.
     */
    public function withType(BulkOperationType $type): self
    {
        return new self($type, $this->payload);
    }

    /**
     * Returns a new instance with a different payload.
     */
    public function withPayload(IndexProductsPayload|DeleteProductsPayload $payload): self
    {
        return new self($this->type, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => $this->payload->jsonSerialize(),
        ];
    }
}
