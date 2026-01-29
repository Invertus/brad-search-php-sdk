<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\IndexProductsPayload;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class BulkOperationsRequestTest extends TestCase
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

    private function createOperation(): BulkOperation
    {
        return BulkOperation::indexProducts([$this->createProduct()]);
    }

    public function testConstructorWithOperations(): void
    {
        $operation = $this->createOperation();
        $request = new BulkOperationsRequest([$operation]);

        $this->assertCount(1, $request->operations);
        $this->assertSame($operation, $request->operations[0]);
    }

    public function testConstructorWithMultipleOperations(): void
    {
        $operation1 = $this->createOperation();
        $operation2 = BulkOperation::indexProducts([$this->createProduct('prod-456')]);
        $request = new BulkOperationsRequest([$operation1, $operation2]);

        $this->assertCount(2, $request->operations);
    }

    public function testExtendsValueObject(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $this->assertInstanceOf(ValueObject::class, $request);
    }

    public function testImplementsJsonSerializable(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $this->assertInstanceOf(JsonSerializable::class, $request);
    }

    public function testJsonSerialize(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $expected = [
            'operations' => [
                [
                    'type' => 'index_products',
                    'payload' => [
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
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $request->jsonSerialize());
    }

    public function testJsonSerializeWithMultipleOperations(): void
    {
        $operation1 = $this->createOperation();
        $operation2 = BulkOperation::indexProducts([$this->createProduct('prod-456')]);
        $request = new BulkOperationsRequest([$operation1, $operation2]);

        $serialized = $request->jsonSerialize();

        $this->assertCount(2, $serialized['operations']);
        $this->assertEquals('prod-123', $serialized['operations'][0]['payload']['products'][0]['id']);
        $this->assertEquals('prod-456', $serialized['operations'][1]['payload']['products'][0]['id']);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $this->assertEquals($request->jsonSerialize(), $request->toArray());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('operations', $decoded);
        $this->assertCount(1, $decoded['operations']);
    }

    public function testWithOperationsReturnsNewInstance(): void
    {
        $operation1 = $this->createOperation();
        $operation2 = BulkOperation::indexProducts([$this->createProduct('new-prod')]);
        $request = new BulkOperationsRequest([$operation1]);
        $newRequest = $request->withOperations([$operation2]);

        $this->assertNotSame($request, $newRequest);
        $this->assertCount(1, $request->operations);
        $this->assertCount(1, $newRequest->operations);
        $this->assertEquals('prod-123', $request->operations[0]->payload->products[0]->id);
        $this->assertEquals('new-prod', $newRequest->operations[0]->payload->products[0]->id);
    }

    public function testWithAddedOperationReturnsNewInstance(): void
    {
        $operation1 = $this->createOperation();
        $operation2 = BulkOperation::indexProducts([$this->createProduct('prod-456')]);
        $request = new BulkOperationsRequest([$operation1]);
        $newRequest = $request->withAddedOperation($operation2);

        $this->assertNotSame($request, $newRequest);
        $this->assertCount(1, $request->operations);
        $this->assertCount(2, $newRequest->operations);
    }

    public function testThrowsExceptionForEmptyOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one operation is required.');

        new BulkOperationsRequest([]);
    }

    public function testThrowsExceptionForInvalidOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation at index 0 must be an instance of BulkOperation.');

        new BulkOperationsRequest(['not-an-operation']);
    }

    public function testThrowsExceptionForMixedArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation at index 1 must be an instance of BulkOperation.');

        new BulkOperationsRequest([$this->createOperation(), 'invalid']);
    }

    public function testExceptionContainsArgumentName(): void
    {
        try {
            new BulkOperationsRequest([]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('operations', $e->argumentName);
        }
    }

    public function testWithOperationsValidatesItems(): void
    {
        $request = new BulkOperationsRequest([$this->createOperation()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one operation is required.');

        $request->withOperations([]);
    }

    public function testJsonSerializeMatchesDarboDrabuziaiExample(): void
    {
        $variant = new ProductVariant(
            '12345-M-RED',
            'SKU-12345-M-RED',
            99.99,
            129.99,
            82.64,
            107.43,
            'https://shop.lt/produktas-12345?size=M&color=RED',
            new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ),
            ['size' => 'M', 'color' => 'RED']
        );

        $product = new Product(
            '12345',
            99.99,
            new ImageUrl(
                'https://cdn.shop.lt/images/12345-small.jpg',
                'https://cdn.shop.lt/images/12345-medium.jpg'
            ),
            [$variant],
            [
                'name_lt-LT' => 'Darbo drabužis Premium',
                'brand_lt-LT' => 'WorkWear Pro',
                'sku' => 'SKU-12345',
                'description_lt-LT' => 'Aukštos kokybės darbo drabužis',
                'categories_lt-LT' => ['Darbo drabužiai', 'Darbo drabužiai > Kelnės'],
            ]
        );

        $operation = BulkOperation::indexProducts([$product]);
        $request = new BulkOperationsRequest([$operation]);

        $serialized = $request->jsonSerialize();

        // Verify top-level structure
        $this->assertArrayHasKey('operations', $serialized);
        $this->assertCount(1, $serialized['operations']);

        // Verify operation structure
        $opData = $serialized['operations'][0];
        $this->assertEquals('index_products', $opData['type']);
        $this->assertArrayHasKey('payload', $opData);
        $this->assertArrayHasKey('products', $opData['payload']);

        // Verify product structure
        $productData = $opData['payload']['products'][0];
        $this->assertEquals('12345', $productData['id']);
        $this->assertEquals(99.99, $productData['price']);
        $this->assertEquals('Darbo drabužis Premium', $productData['name_lt-LT']);
        $this->assertEquals('WorkWear Pro', $productData['brand_lt-LT']);
        $this->assertEquals('SKU-12345', $productData['sku']);

        // Verify variant structure
        $this->assertArrayHasKey('variants', $productData);
        $this->assertCount(1, $productData['variants']);
        $variantData = $productData['variants'][0];
        $this->assertEquals('12345-M-RED', $variantData['id']);
        $this->assertEquals('SKU-12345-M-RED', $variantData['sku']);
        $this->assertEquals(['size' => 'M', 'color' => 'RED'], $variantData['attrs']);
    }

    public function testJsonOutputMatchesExpectedApiFormat(): void
    {
        $product = new Product(
            '12345',
            99.99,
            new ImageUrl(
                'https://cdn.example.com/small.jpg',
                'https://cdn.example.com/medium.jpg'
            ),
            [],
            ['name_lt-LT' => 'Test Product']
        );

        $operation = BulkOperation::indexProducts([$product]);
        $request = new BulkOperationsRequest([$operation]);

        $json = json_encode($request, JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true);

        // Verify the structure matches what the API expects
        $this->assertArrayHasKey('operations', $decoded);
        $this->assertIsArray($decoded['operations']);
        $this->assertArrayHasKey('type', $decoded['operations'][0]);
        $this->assertArrayHasKey('payload', $decoded['operations'][0]);
        $this->assertEquals('index_products', $decoded['operations'][0]['type']);
    }
}
