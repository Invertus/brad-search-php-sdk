<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;

/**
 * Builder for constructing Product ValueObjects with a fluent API.
 *
 * This builder simplifies the creation of complex Product objects with
 * many fields including localized fields and variants.
 */
final class ProductBuilder
{
    private ?string $id = null;
    private ?float $price = null;
    private ?ImageUrl $imageUrl = null;

    /** @var array<int, ProductVariant> */
    private array $variants = [];

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
     * Sets the product price.
     */
    public function price(float $price): self
    {
        $this->price = $price;
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
     * Adds a variant to the product.
     */
    public function addVariant(ProductVariant $variant): self
    {
        $this->variants[] = $variant;
        return $this;
    }

    /**
     * Sets all variants at once.
     *
     * @param array<int, ProductVariant> $variants
     */
    public function variants(array $variants): self
    {
        $this->variants = $variants;
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
     * Sets the SKU field.
     */
    public function sku(string $sku): self
    {
        $this->additionalFields['sku'] = $sku;
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

        if ($this->price === null) {
            throw new InvalidArgumentException(
                'Product price is required.',
                'price',
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
            $this->price,
            $this->imageUrl,
            $this->variants,
            $this->additionalFields
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->id = null;
        $this->price = null;
        $this->imageUrl = null;
        $this->variants = [];
        $this->additionalFields = [];

        return $this;
    }
}
