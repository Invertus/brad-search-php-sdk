<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;

/**
 * Builder for constructing Product ValueObjects with a fluent API.
 *
 * This builder simplifies the creation of complex Product objects with
 * many fields including localized fields and pricing.
 */
final class ProductBuilder
{
    private ?string $id = null;
    private ?string $sku = null;
    private ?ProductPricing $pricing = null;
    private ?ImageUrl $imageUrl = null;
    private ?bool $inStock = null;
    private ?bool $isNew = null;

    /** @var array<string, mixed> */
    private array $additionalFields = [];

    /**
     * Sets the product ID.
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Sets the product SKU.
     */
    public function sku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * Sets the product pricing.
     */
    public function pricing(ProductPricing $pricing): self
    {
        $this->pricing = $pricing;
        return $this;
    }

    /**
     * Sets the product image URL.
     */
    public function imageUrl(ImageUrl $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    /**
     * Sets the product in stock status.
     */
    public function inStock(?bool $inStock): self
    {
        $this->inStock = $inStock;
        return $this;
    }

    /**
     * Sets the product is new status.
     */
    public function isNew(?bool $isNew): self
    {
        $this->isNew = $isNew;
        return $this;
    }

    /**
     * Adds a localized or dynamic field.
     */
    public function field(string $key, mixed $value): self
    {
        $this->additionalFields[$key] = $value;
        return $this;
    }

    /**
     * Adds a localized name field.
     *
     * @param string $locale e.g., 'lt-LT', 'en-US'
     */
    public function name(string $value, string $locale): self
    {
        $this->additionalFields["name_{$locale}"] = $value;
        return $this;
    }

    /**
     * Adds a localized brand field.
     *
     * @param string $locale e.g., 'lt-LT', 'en-US'
     */
    public function brand(string $value, string $locale): self
    {
        $this->additionalFields["brand_{$locale}"] = $value;
        return $this;
    }

    /**
     * Adds a localized description field.
     *
     * @param string $locale e.g., 'lt-LT', 'en-US'
     */
    public function description(string $value, string $locale): self
    {
        $this->additionalFields["description_{$locale}"] = $value;
        return $this;
    }

    /**
     * Adds a localized categories field.
     *
     * @param array<int, string> $categories Hierarchical categories
     * @param string $locale e.g., 'lt-LT', 'en-US'
     */
    public function categories(array $categories, string $locale): self
    {
        $this->additionalFields["categories_{$locale}"] = $categories;
        return $this;
    }

    /**
     * Builds the Product ValueObject.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): Product
    {
        if ($this->id === null) {
            throw new InvalidArgumentException(
                'Product ID is required.',
                'id',
                null
            );
        }

        if ($this->sku === null) {
            throw new InvalidArgumentException(
                'Product SKU is required.',
                'sku',
                null
            );
        }

        if ($this->pricing === null) {
            throw new InvalidArgumentException(
                'Product pricing is required.',
                'pricing',
                null
            );
        }

        if ($this->imageUrl === null) {
            throw new InvalidArgumentException(
                'Product image URL is required.',
                'imageUrl',
                null
            );
        }

        return new Product(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->imageUrl,
            $this->inStock,
            $this->isNew,
            $this->additionalFields
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->id = null;
        $this->sku = null;
        $this->pricing = null;
        $this->imageUrl = null;
        $this->inStock = null;
        $this->isNew = null;
        $this->additionalFields = [];

        return $this;
    }
}
