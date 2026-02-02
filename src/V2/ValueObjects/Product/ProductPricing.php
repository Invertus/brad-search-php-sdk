<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Product;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents pricing information for a product or variant.
 *
 * This immutable ValueObject contains all price-related fields:
 * - price: Current price
 * - basePrice: Original/base price (before discounts)
 * - priceTaxExcluded: Price without tax
 * - basePriceTaxExcluded: Base price without tax
 */
final readonly class ProductPricing extends ValueObject
{
    /**
     * @param float $price Current price
     * @param float $basePrice Original/base price
     * @param float $priceTaxExcluded Price without tax
     * @param float $basePriceTaxExcluded Base price without tax
     */
    public function __construct(
        public float $price,
        public float $basePrice,
        public float $priceTaxExcluded,
        public float $basePriceTaxExcluded,
    ) {
        $this->validatePrice($price, 'price');
        $this->validatePrice($basePrice, 'basePrice');
        $this->validatePrice($priceTaxExcluded, 'priceTaxExcluded');
        $this->validatePrice($basePriceTaxExcluded, 'basePriceTaxExcluded');
    }

    /**
     * Returns a new instance with a different price.
     */
    public function withPrice(float $price): self
    {
        return new self($price, $this->basePrice, $this->priceTaxExcluded, $this->basePriceTaxExcluded);
    }

    /**
     * Returns a new instance with a different base price.
     */
    public function withBasePrice(float $basePrice): self
    {
        return new self($this->price, $basePrice, $this->priceTaxExcluded, $this->basePriceTaxExcluded);
    }

    /**
     * Returns a new instance with a different price tax excluded.
     */
    public function withPriceTaxExcluded(float $priceTaxExcluded): self
    {
        return new self($this->price, $this->basePrice, $priceTaxExcluded, $this->basePriceTaxExcluded);
    }

    /**
     * Returns a new instance with a different base price tax excluded.
     */
    public function withBasePriceTaxExcluded(float $basePriceTaxExcluded): self
    {
        return new self($this->price, $this->basePrice, $this->priceTaxExcluded, $basePriceTaxExcluded);
    }

    /**
     * @return array<string, float>
     */
    public function jsonSerialize(): array
    {
        return [
            'price' => $this->price,
            'basePrice' => $this->basePrice,
            'priceTaxExcluded' => $this->priceTaxExcluded,
            'basePriceTaxExcluded' => $this->basePriceTaxExcluded,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validatePrice(float $price, string $fieldName): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException(
                sprintf('The %s cannot be negative.', $fieldName),
                $fieldName,
                $price
            );
        }
    }
}
