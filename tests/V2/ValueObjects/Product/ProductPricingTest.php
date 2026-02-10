<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Product;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ProductPricingTest extends TestCase
{
    private const PRICE = 99.99;
    private const BASE_PRICE = 129.99;
    private const PRICE_TAX_EXCLUDED = 82.64;
    private const BASE_PRICE_TAX_EXCLUDED = 107.43;

    private function createPricing(): ProductPricing
    {
        return new ProductPricing(
            self::PRICE,
            self::BASE_PRICE,
            self::PRICE_TAX_EXCLUDED,
            self::BASE_PRICE_TAX_EXCLUDED
        );
    }

    public function testConstructorWithValidValues(): void
    {
        $pricing = $this->createPricing();

        $this->assertEquals(self::PRICE, $pricing->price);
        $this->assertEquals(self::BASE_PRICE, $pricing->basePrice);
        $this->assertEquals(self::PRICE_TAX_EXCLUDED, $pricing->priceTaxExcluded);
        $this->assertEquals(self::BASE_PRICE_TAX_EXCLUDED, $pricing->basePriceTaxExcluded);
    }

    public function testExtendsValueObject(): void
    {
        $pricing = $this->createPricing();

        $this->assertInstanceOf(ValueObject::class, $pricing);
    }

    public function testImplementsJsonSerializable(): void
    {
        $pricing = $this->createPricing();

        $this->assertInstanceOf(JsonSerializable::class, $pricing);
    }

    public function testJsonSerialize(): void
    {
        $pricing = $this->createPricing();

        $expected = [
            'price' => self::PRICE,
            'basePrice' => self::BASE_PRICE,
            'priceTaxExcluded' => self::PRICE_TAX_EXCLUDED,
            'basePriceTaxExcluded' => self::BASE_PRICE_TAX_EXCLUDED,
        ];

        $this->assertEquals($expected, $pricing->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $pricing = $this->createPricing();

        $this->assertEquals($pricing->jsonSerialize(), $pricing->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $pricing = $this->createPricing();

        $json = json_encode($pricing);
        $decoded = json_decode($json, true);

        $this->assertEquals(self::PRICE, $decoded['price']);
        $this->assertEquals(self::BASE_PRICE, $decoded['basePrice']);
        $this->assertEquals(self::PRICE_TAX_EXCLUDED, $decoded['priceTaxExcluded']);
        $this->assertEquals(self::BASE_PRICE_TAX_EXCLUDED, $decoded['basePriceTaxExcluded']);
    }

    public function testWithPriceReturnsNewInstance(): void
    {
        $pricing = $this->createPricing();
        $newPrice = 149.99;
        $newPricing = $pricing->withPrice($newPrice);

        $this->assertNotSame($pricing, $newPricing);
        $this->assertEquals(self::PRICE, $pricing->price);
        $this->assertEquals($newPrice, $newPricing->price);
        $this->assertEquals(self::BASE_PRICE, $newPricing->basePrice);
    }

    public function testWithBasePriceReturnsNewInstance(): void
    {
        $pricing = $this->createPricing();
        $newBasePrice = 199.99;
        $newPricing = $pricing->withBasePrice($newBasePrice);

        $this->assertNotSame($pricing, $newPricing);
        $this->assertEquals(self::BASE_PRICE, $pricing->basePrice);
        $this->assertEquals($newBasePrice, $newPricing->basePrice);
        $this->assertEquals(self::PRICE, $newPricing->price);
    }

    public function testWithPriceTaxExcludedReturnsNewInstance(): void
    {
        $pricing = $this->createPricing();
        $newPriceTaxExcluded = 123.97;
        $newPricing = $pricing->withPriceTaxExcluded($newPriceTaxExcluded);

        $this->assertNotSame($pricing, $newPricing);
        $this->assertEquals(self::PRICE_TAX_EXCLUDED, $pricing->priceTaxExcluded);
        $this->assertEquals($newPriceTaxExcluded, $newPricing->priceTaxExcluded);
    }

    public function testWithBasePriceTaxExcludedReturnsNewInstance(): void
    {
        $pricing = $this->createPricing();
        $newBasePriceTaxExcluded = 165.29;
        $newPricing = $pricing->withBasePriceTaxExcluded($newBasePriceTaxExcluded);

        $this->assertNotSame($pricing, $newPricing);
        $this->assertEquals(self::BASE_PRICE_TAX_EXCLUDED, $pricing->basePriceTaxExcluded);
        $this->assertEquals($newBasePriceTaxExcluded, $newPricing->basePriceTaxExcluded);
    }

    public function testChainedWithMethods(): void
    {
        $pricing = $this->createPricing()
            ->withPrice(199.99)
            ->withBasePrice(249.99)
            ->withPriceTaxExcluded(165.29)
            ->withBasePriceTaxExcluded(206.61);

        $this->assertEquals(199.99, $pricing->price);
        $this->assertEquals(249.99, $pricing->basePrice);
        $this->assertEquals(165.29, $pricing->priceTaxExcluded);
        $this->assertEquals(206.61, $pricing->basePriceTaxExcluded);
    }

    public function testThrowsExceptionForNegativePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The price cannot be negative.');

        new ProductPricing(-10.00, self::BASE_PRICE, self::PRICE_TAX_EXCLUDED, self::BASE_PRICE_TAX_EXCLUDED);
    }

    public function testThrowsExceptionForNegativeBasePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The basePrice cannot be negative.');

        new ProductPricing(self::PRICE, -10.00, self::PRICE_TAX_EXCLUDED, self::BASE_PRICE_TAX_EXCLUDED);
    }

    public function testThrowsExceptionForNegativePriceTaxExcluded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The priceTaxExcluded cannot be negative.');

        new ProductPricing(self::PRICE, self::BASE_PRICE, -10.00, self::BASE_PRICE_TAX_EXCLUDED);
    }

    public function testThrowsExceptionForNegativeBasePriceTaxExcluded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The basePriceTaxExcluded cannot be negative.');

        new ProductPricing(self::PRICE, self::BASE_PRICE, self::PRICE_TAX_EXCLUDED, -10.00);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new ProductPricing(-1.00, self::BASE_PRICE, self::PRICE_TAX_EXCLUDED, self::BASE_PRICE_TAX_EXCLUDED);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('price', $e->argumentName);
            $this->assertEquals(-1.00, $e->invalidValue);
        }
    }

    public function testWithPriceValidatesNewValue(): void
    {
        $pricing = $this->createPricing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The price cannot be negative.');

        $pricing->withPrice(-1.00);
    }

    public function testWithBasePriceValidatesNewValue(): void
    {
        $pricing = $this->createPricing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The basePrice cannot be negative.');

        $pricing->withBasePrice(-1.00);
    }

    public function testAcceptsZeroPrices(): void
    {
        $pricing = new ProductPricing(0.0, 0.0, 0.0, 0.0);

        $this->assertEquals(0.0, $pricing->price);
        $this->assertEquals(0.0, $pricing->basePrice);
        $this->assertEquals(0.0, $pricing->priceTaxExcluded);
        $this->assertEquals(0.0, $pricing->basePriceTaxExcluded);
    }

    public function testAcceptsHighPrecisionValues(): void
    {
        $pricing = new ProductPricing(99.999999, 129.123456, 82.654321, 107.987654);

        $this->assertEquals(99.999999, $pricing->price);
        $this->assertEquals(129.123456, $pricing->basePrice);
        $this->assertEquals(82.654321, $pricing->priceTaxExcluded);
        $this->assertEquals(107.987654, $pricing->basePriceTaxExcluded);
    }
}
