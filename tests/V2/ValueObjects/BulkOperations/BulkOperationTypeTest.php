<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\BulkOperations;

use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use PHPUnit\Framework\TestCase;

class BulkOperationTypeTest extends TestCase
{
    public function testIndexProductsValue(): void
    {
        $this->assertEquals('index_products', BulkOperationType::INDEX_PRODUCTS->value);
    }

    public function testEnumIsStringBacked(): void
    {
        $type = BulkOperationType::INDEX_PRODUCTS;

        $this->assertIsString($type->value);
    }

    public function testCanCreateFromString(): void
    {
        $type = BulkOperationType::from('index_products');

        $this->assertEquals(BulkOperationType::INDEX_PRODUCTS, $type);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $type = BulkOperationType::tryFrom('invalid_type');

        $this->assertNull($type);
    }

    public function testAllCasesAvailable(): void
    {
        $cases = BulkOperationType::cases();

        $this->assertContains(BulkOperationType::INDEX_PRODUCTS, $cases);
    }
}
