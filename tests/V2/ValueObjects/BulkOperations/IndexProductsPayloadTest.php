<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\IndexProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class IndexProductsPayloadTest extends TestCase
{
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
    }

    private function createProduct(string $id = 'prod-123'): Product
    {
        return new Product(
            $id,
            99.99,
            $this->createImageUrl()
        );
    }

    public function testConstructorWithProducts(): void
    {
        $product = $this->createProduct();
        $payload = new IndexProductsPayload([$product]);

        $this->assertCount(1, $payload->products);
        $this->assertSame($product, $payload->products[0]);
    }

    public function testConstructorWithMultipleProducts(): void
    {
        $product1 = $this->createProduct('prod-1');
        $product2 = $this->createProduct('prod-2');
        $payload = new IndexProductsPayload([$product1, $product2]);

        $this->assertCount(2, $payload->products);
    }

    public function testExtendsValueObject(): void
    {
        $payload = new IndexProductsPayload([$this->createProduct()]);

        $this->assertInstanceOf(ValueObject::class, $payload);
    }

    public function testImplementsJsonSerializable(): void
    {
        $payload = new IndexProductsPayload([$this->createProduct()]);

        $this->assertInstanceOf(JsonSerializable::class, $payload);
    }

    public function testJsonSerialize(): void
    {
        $product = $this->createProduct();
        $payload = new IndexProductsPayload([$product]);

        $expected = [
            'products' => [
                [
                    'id' => 'prod-123',
                    'price' => 99.99,
                    'imageUrl' => [
                        'small' => self::SMALL_IMAGE,
                        'medium' => self::MEDIUM_IMAGE,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $payload->jsonSerialize());
    }

    public function testJsonSerializeWithMultipleProducts(): void
    {
        $product1 = $this->createProduct('prod-1');
        $product2 = $this->createProduct('prod-2');
        $payload = new IndexProductsPayload([$product1, $product2]);

        $serialized = $payload->jsonSerialize();

        $this->assertCount(2, $serialized['products']);
        $this->assertEquals('prod-1', $serialized['products'][0]['id']);
        $this->assertEquals('prod-2', $serialized['products'][1]['id']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $payload = new IndexProductsPayload([$this->createProduct()]);

        $this->assertEquals($payload->jsonSerialize(), $payload->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $payload = new IndexProductsPayload([$this->createProduct()]);

        $json = json_encode($payload);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('products', $decoded);
        $this->assertCount(1, $decoded['products']);
    }

    public function testWithProductsReturnsNewInstance(): void
    {
        $product1 = $this->createProduct('prod-1');
        $product2 = $this->createProduct('prod-2');
        $payload = new IndexProductsPayload([$product1]);
        $newPayload = $payload->withProducts([$product2]);

        $this->assertNotSame($payload, $newPayload);
        $this->assertEquals('prod-1', $payload->products[0]->id);
        $this->assertEquals('prod-2', $newPayload->products[0]->id);
    }

    public function testWithAddedProductReturnsNewInstance(): void
    {
        $product1 = $this->createProduct('prod-1');
        $product2 = $this->createProduct('prod-2');
        $payload = new IndexProductsPayload([$product1]);
        $newPayload = $payload->withAddedProduct($product2);

        $this->assertNotSame($payload, $newPayload);
        $this->assertCount(1, $payload->products);
        $this->assertCount(2, $newPayload->products);
        $this->assertEquals('prod-2', $newPayload->products[1]->id);
    }

    public function testThrowsExceptionForEmptyProducts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one product is required in the payload.');

        new IndexProductsPayload([]);
    }

    public function testThrowsExceptionForInvalidProductType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product at index 0 must be an instance of Product.');

        new IndexProductsPayload(['not-a-product']);
    }

    public function testThrowsExceptionForMixedArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product at index 1 must be an instance of Product.');

        new IndexProductsPayload([$this->createProduct(), 'invalid']);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new IndexProductsPayload([]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('products', $e->argumentName);
        }
    }

    public function testWithProductsValidatesItems(): void
    {
        $payload = new IndexProductsPayload([$this->createProduct()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one product is required in the payload.');

        $payload->withProducts([]);
    }
}
