<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a product for bulk indexing operations.
 *
 * This immutable ValueObject contains all product fields including
 * localized fields, pricing, categories, and variants collection.
 */
final readonly class Product extends ValueObject
{
    /**
     * @param string $id Unique product identifier
     * @param float $price Current product price
     * @param ImageUrl $imageUrl Product image URLs
     * @param array<int, ProductVariant> $variants Product variants collection
     * @param array<string, mixed> $additionalFields Localized and other dynamic fields
     */
    public function __construct(
        public string $id,
        public float $price,
        public ImageUrl $imageUrl,
        public array $variants = [],
        public array $additionalFields = []
    ) {
        $this->validateId($id);
        $this->validatePrice($price);
        $this->validateVariants($variants);
    }

    /**
     * Returns a new instance with a different ID.
     */
    public function withId(string $id): self
    {
        return new self(
            $id,
            $this->price,
            $this->imageUrl,
            $this->variants,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with a different price.
     */
    public function withPrice(float $price): self
    {
        return new self(
            $this->id,
            $price,
            $this->imageUrl,
            $this->variants,
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
            $this->price,
            $imageUrl,
            $this->variants,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with different variants.
     *
     * @param array<int, ProductVariant> $variants
     */
    public function withVariants(array $variants): self
    {
        return new self(
            $this->id,
            $this->price,
            $this->imageUrl,
            $variants,
            $this->additionalFields
        );
    }

    /**
     * Returns a new instance with an added variant.
     */
    public function withAddedVariant(ProductVariant $variant): self
    {
        $variants = $this->variants;
        $variants[] = $variant;

        return $this->withVariants($variants);
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
            $this->price,
            $this->imageUrl,
            $this->variants,
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
            'price' => $this->price,
            'imageUrl' => $this->imageUrl->jsonSerialize(),
        ];

        // Add localized and dynamic fields
        foreach ($this->additionalFields as $key => $value) {
            $result[$key] = $value;
        }

        // Add variants if present
        if (count($this->variants) > 0) {
            $result['variants'] = array_map(
                fn(ProductVariant $variant) => $variant->jsonSerialize(),
                $this->variants
            );
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
    private function validatePrice(float $price): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException(
                'The product price cannot be negative.',
                'price',
                $price
            );
        }
    }

    /**
     * @param array<int, ProductVariant> $variants
     * @throws InvalidArgumentException
     */
    private function validateVariants(array $variants): void
    {
        foreach ($variants as $index => $variant) {
            if (!$variant instanceof ProductVariant) {
                throw new InvalidArgumentException(
                    sprintf('Variant at index %d must be an instance of ProductVariant.', $index),
                    'variants',
                    $variant
                );
            }
        }
    }
}
