<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a product variant within a product for bulk operations.
 *
 * This immutable ValueObject contains all variant-specific fields including
 * pricing, URLs, and variant attributes.
 */
final readonly class ProductVariant extends ValueObject
{
    /**
     * @param string $id Unique identifier for the variant
     * @param string $sku Stock Keeping Unit
     * @param ProductPricing $pricing Variant pricing information
     * @param string $productUrl URL to the product variant page
     * @param ImageUrl $imageUrl Image URLs for the variant
     * @param array<string, mixed> $attrs Variant-specific attributes (e.g., size, color)
     */
    public function __construct(
        public string $id,
        public string $sku,
        public ProductPricing $pricing,
        public string $productUrl,
        public ImageUrl $imageUrl,
        public array $attrs = []
    ) {
        $this->validateId($id);
        $this->validateSku($sku);
        $this->validateProductUrl($productUrl);
    }

    /**
     * Returns a new instance with a different ID.
     */
    public function withId(string $id): self
    {
        return new self(
            $id,
            $this->sku,
            $this->pricing,
            $this->productUrl,
            $this->imageUrl,
            $this->attrs
        );
    }

    /**
     * Returns a new instance with a different SKU.
     */
    public function withSku(string $sku): self
    {
        return new self(
            $this->id,
            $sku,
            $this->pricing,
            $this->productUrl,
            $this->imageUrl,
            $this->attrs
        );
    }

    /**
     * Returns a new instance with different pricing.
     */
    public function withPricing(ProductPricing $pricing): self
    {
        return new self(
            $this->id,
            $this->sku,
            $pricing,
            $this->productUrl,
            $this->imageUrl,
            $this->attrs
        );
    }

    /**
     * Returns a new instance with a different product URL.
     */
    public function withProductUrl(string $productUrl): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $productUrl,
            $this->imageUrl,
            $this->attrs
        );
    }

    /**
     * Returns a new instance with a different image URL.
     */
    public function withImageUrl(ImageUrl $imageUrl): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->productUrl,
            $imageUrl,
            $this->attrs
        );
    }

    /**
     * Returns a new instance with different attributes.
     *
     * @param array<string, mixed> $attrs
     */
    public function withAttrs(array $attrs): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->productUrl,
            $this->imageUrl,
            $attrs
        );
    }

    /**
     * Returns a new instance with an added attribute.
     */
    public function withAddedAttr(string $key, mixed $value): self
    {
        $attrs = $this->attrs;
        $attrs[$key] = $value;

        return $this->withAttrs($attrs);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->pricing->price,
            'basePrice' => $this->pricing->basePrice,
            'priceTaxExcluded' => $this->pricing->priceTaxExcluded,
            'basePriceTaxExcluded' => $this->pricing->basePriceTaxExcluded,
            'productUrl' => $this->productUrl,
            'imageUrl' => $this->imageUrl->jsonSerialize(),
            'attrs' => $this->attrs,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateId(string $id): void
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException(
                'The variant ID cannot be empty.',
                'id',
                $id
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateSku(string $sku): void
    {
        if (trim($sku) === '') {
            throw new InvalidArgumentException(
                'The variant SKU cannot be empty.',
                'sku',
                $sku
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateProductUrl(string $productUrl): void
    {
        if (trim($productUrl) === '') {
            throw new InvalidArgumentException(
                'The product URL cannot be empty.',
                'productUrl',
                $productUrl
            );
        }

        if (!preg_match('/^https?:\/\/.+/', $productUrl)) {
            throw new InvalidArgumentException(
                'The product URL must be a valid HTTP or HTTPS URL.',
                'productUrl',
                $productUrl
            );
        }
    }
}
