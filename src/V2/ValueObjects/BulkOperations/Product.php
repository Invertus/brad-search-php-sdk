<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a product for bulk indexing operations.
 *
 * This immutable ValueObject contains all product fields including
 * localized fields, pricing, categories, and optional metadata.
 */
final readonly class Product extends ValueObject
{
    /**
     * @param string $id Unique product identifier
     * @param string $sku Stock Keeping Unit
     * @param ProductPricing $pricing Product pricing information
     * @param ImageUrl $imageUrl Product image URLs
     * @param bool|null $inStock Whether the product is in stock
     * @param bool|null $isNew Whether the product is new
     * @param array<string, mixed> $additionalFields Localized and other dynamic fields
     */
    public function __construct(
        public string $id,
        public string $sku,
        public ProductPricing $pricing,
        public ImageUrl $imageUrl,
        public ?bool $inStock = null,
        public ?bool $isNew = null,
        public array $additionalFields = []
    ) {
        $this->validateId($id);
        $this->validateSku($sku);
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
            $this->imageUrl,
            $this->inStock,
            $this->isNew,
            $this->additionalFields
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
            $this->imageUrl,
            $this->inStock,
            $this->isNew,
            $this->additionalFields
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
            $this->imageUrl,
            $this->inStock,
            $this->isNew,
            $this->additionalFields
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
            $imageUrl,
            $this->inStock,
            $this->isNew,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with a different inStock value.
     */
    public function withInStock(?bool $inStock): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->imageUrl,
            $inStock,
            $this->isNew,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with a different isNew value.
     */
    public function withIsNew(?bool $isNew): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->imageUrl,
            $this->inStock,
            $isNew,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with different additional fields.
     *
     * @param array<string, mixed> $additionalFields
     */
    public function withAdditionalFields(array $additionalFields): self
    {
        return new self(
            $this->id,
            $this->sku,
            $this->pricing,
            $this->imageUrl,
            $this->inStock,
            $this->isNew,
            $additionalFields
        );
    }

    /**
     * Returns a new instance with an added additional field.
     */
    public function withAddedField(string $key, mixed $value): self
    {
        $additionalFields = $this->additionalFields;
        $additionalFields[$key] = $value;

        return $this->withAdditionalFields($additionalFields);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->pricing->price,
            'basePrice' => $this->pricing->basePrice,
            'priceTaxExcluded' => $this->pricing->priceTaxExcluded,
            'basePriceTaxExcluded' => $this->pricing->basePriceTaxExcluded,
            'imageUrl' => $this->imageUrl->jsonSerialize(),
        ];

        if ($this->inStock !== null) {
            $result['inStock'] = $this->inStock;
        }

        if ($this->isNew !== null) {
            $result['isNew'] = $this->isNew;
        }

        // Add localized and dynamic fields
        foreach ($this->additionalFields as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateId(string $id): void
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException(
                'The product ID cannot be empty.',
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
                'The product SKU cannot be empty.',
                'sku',
                $sku
            );
        }
    }
}
