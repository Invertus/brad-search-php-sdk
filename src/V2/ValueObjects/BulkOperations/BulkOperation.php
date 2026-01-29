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
     * @param IndexProductsPayload $payload The operation payload
     */
    public function __construct(
        public BulkOperationType $type,
        public IndexProductsPayload $payload
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
     * Returns a new instance with a different type.
     */
    public function withType(BulkOperationType $type): self
    {
        return new self($type, $this->payload);
    }

    /**
     * Returns a new instance with a different payload.
     */
    public function withPayload(IndexProductsPayload $payload): self
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
