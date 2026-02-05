<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the payload for a delete_products bulk operation.
 *
 * This immutable ValueObject contains the product IDs array to be deleted.
 */
final readonly class DeleteProductsPayload extends ValueObject
{
    /**
     * @param array<int, string> $productIds Product IDs to delete
     */
    public function __construct(
        public array $productIds
    ) {
        $this->validateProductIds($productIds);
    }

    /**
     * Returns a new instance with different product IDs.
     *
     * @param array<int, string> $productIds
     */
    public function withProductIds(array $productIds): self
    {
        return new self($productIds);
    }

    /**
     * Returns a new instance with an added product ID.
     */
    public function withAddedProductId(string $productId): self
    {
        $productIds = $this->productIds;
        $productIds[] = $productId;

        return new self($productIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'product_ids' => $this->productIds,
        ];
    }

    /**
     * @param array<int, string> $productIds
     * @throws InvalidArgumentException
     */
    private function validateProductIds(array $productIds): void
    {
        if (count($productIds) === 0) {
            throw new InvalidArgumentException(
                'At least one product ID is required in the payload.',
                'productIds',
                $productIds
            );
        }

        foreach ($productIds as $index => $productId) {
            if (!is_string($productId)) {
                throw new InvalidArgumentException(
                    sprintf('Product ID at index %d must be a string.', $index),
                    'productIds',
                    $productId
                );
            }

            if ($productId === '') {
                throw new InvalidArgumentException(
                    sprintf('Product ID at index %d cannot be empty.', $index),
                    'productIds',
                    $productId
                );
            }
        }
    }
}
