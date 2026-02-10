<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the payload for an index_products bulk operation.
 *
 * This immutable ValueObject contains the products array to be indexed.
 */
final readonly class IndexProductsPayload extends ValueObject
{
    /**
     * @param array<int, Product> $products Products to index
     */
    public function __construct(
        public array $products
    ) {
        $this->validateProducts($products);
    }

    /**
     * Returns a new instance with different products.
     *
     * @param array<int, Product> $products
     */
    public function withProducts(array $products): self
    {
        return new self($products);
    }

    /**
     * Returns a new instance with an added product.
     */
    public function withAddedProduct(Product $product): self
    {
        $products = $this->products;
        $products[] = $product;

        return new self($products);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'products' => array_map(
                fn(Product $product) => $product->jsonSerialize(),
                $this->products
            ),
        ];
    }

    /**
     * @param array<int, Product> $products
     * @throws InvalidArgumentException
     */
    private function validateProducts(array $products): void
    {
        if (count($products) === 0) {
            throw new InvalidArgumentException(
                'At least one product is required in the payload.',
                'products',
                $products
            );
        }

        foreach ($products as $index => $product) {
            if (!$product instanceof Product) {
                throw new InvalidArgumentException(
                    sprintf('Product at index %d must be an instance of Product.', $index),
                    'products',
                    $product
                );
            }
        }
    }
}
