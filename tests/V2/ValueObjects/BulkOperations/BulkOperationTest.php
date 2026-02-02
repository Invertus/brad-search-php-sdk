<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\IndexProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class BulkOperationTest extends TestCase
{
    private const SMALL_IMAGE = 'https://cdn.example.com/images/small.jpg';
    private const MEDIUM_IMAGE = 'https://cdn.example.com/images/medium.jpg';

    private function createImageUrl(): ImageUrl
    {
        return new ImageUrl(self::SMALL_IMAGE, self::MEDIUM_IMAGE);
    }

    private function createPricing(): ProductPricing
    {
        return new ProductPricing(99.99, 99.99, 82.64, 82.64);
    }

    private function createProduct(string $id = 'prod-123'): Product
    {
        return new Product(
            $id,
            'SKU-' . $id,
            $this->createPricing(),
            $this->createImageUrl()
        );
    }

    private function createPayload(): IndexProductsPayload
    {
        return new IndexProductsPayload([$this->createProduct()]);
    }

    private function createOperation(): BulkOperation
    {
        return new BulkOperation(
            BulkOperationType::INDEX_PRODUCTS,
            $this->createPayload()
        );
    }

    public function testConstructor(): void
    {
        $payload = $this->createPayload();
        $operation = new BulkOperation(
            BulkOperationType::INDEX_PRODUCTS,
            $payload
        );

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $operation->type);
        $this->assertSame($payload, $operation->payload);
    }

    public function testExtendsValueObject(): void
    {
        $operation = $this->createOperation();

        $this->assertInstanceOf(ValueObject::class, $operation);
    }

    public function testImplementsJsonSerializable(): void
    {
        $operation = $this->createOperation();

        $this->assertInstanceOf(JsonSerializable::class, $operation);
    }

    public function testJsonSerialize(): void
    {
        $operation = $this->createOperation();

        $expected = [
            'type' => 'index_products',
            'payload' => [
                'products' => [
                    [
                        'id' => 'prod-123',
                        'sku' => 'SKU-prod-123',
                        'price' => 99.99,
                        'basePrice' => 99.99,
                        'priceTaxExcluded' => 82.64,
                        'basePriceTaxExcluded' => 82.64,
                        'imageUrl' => [
                            'small' => self::SMALL_IMAGE,
                            'medium' => self::MEDIUM_IMAGE,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $operation->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $operation = $this->createOperation();

        $this->assertEquals($operation->jsonSerialize(), $operation->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $operation = $this->createOperation();

        $json = json_encode($operation);
        $decoded = json_decode($json, true);

        $this->assertEquals('index_products', $decoded['type']);
        $this->assertArrayHasKey('payload', $decoded);
        $this->assertArrayHasKey('products', $decoded['payload']);
    }

    public function testIndexProductsFactoryMethod(): void
    {
        $products = [$this->createProduct()];
        $operation = BulkOperation::indexProducts($products);

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $operation->type);
        $this->assertCount(1, $operation->payload->products);
    }

    public function testIndexProductsFactoryMethodWithMultipleProducts(): void
    {
        $products = [
            $this->createProduct('prod-1'),
            $this->createProduct('prod-2'),
        ];
        $operation = BulkOperation::indexProducts($products);

        $this->assertCount(2, $operation->payload->products);
    }

    public function testWithTypeReturnsNewInstance(): void
    {
        $operation = $this->createOperation();
        // Currently only INDEX_PRODUCTS is available, so we test same type
        $newOperation = $operation->withType(BulkOperationType::INDEX_PRODUCTS);

        $this->assertNotSame($operation, $newOperation);
        $this->assertEquals($operation->type, $newOperation->type);
    }

    public function testWithPayloadReturnsNewInstance(): void
    {
        $operation = $this->createOperation();
        $newPayload = new IndexProductsPayload([
            $this->createProduct('new-prod'),
        ]);
        $newOperation = $operation->withPayload($newPayload);

        $this->assertNotSame($operation, $newOperation);
        $this->assertNotSame($operation->payload, $newOperation->payload);
        $this->assertEquals('prod-123', $operation->payload->products[0]->id);
        $this->assertEquals('new-prod', $newOperation->payload->products[0]->id);
    }

    public function testJsonSerializeMatchesApiStructure(): void
    {
        $operation = $this->createOperation();
        $serialized = $operation->jsonSerialize();

        // Verify the structure matches what the API expects
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('payload', $serialized);
        $this->assertIsString($serialized['type']);
        $this->assertIsArray($serialized['payload']);
        $this->assertArrayHasKey('products', $serialized['payload']);
    }
}
