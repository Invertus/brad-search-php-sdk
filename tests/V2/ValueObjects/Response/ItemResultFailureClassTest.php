<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\Exceptions\ErrorCode;
use BradSearch\SyncSdk\Exceptions\FailureClass;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationType;
use BradSearch\SyncSdk\V2\ValueObjects\Response\ItemResult;
use PHPUnit\Framework\TestCase;

/**
 * A failed bulk item must carry the engine's per-item code, so a caller decides
 * whether to retry that item without matching the message text.
 */
class ItemResultFailureClassTest extends TestCase
{
    public function testParsesCodeFromApiResponse(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-1',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'mapper_parsing_exception: failed to parse field [price]',
            'code' => 'validation_error',
        ]);

        $this->assertSame('validation_error', $result->code);
        $this->assertSame(ErrorCode::ValidationError, $result->errorCode());
        $this->assertSame(FailureClass::PermanentItem, $result->failureClass());
        $this->assertTrue($result->hasError());
    }

    public function testBackendUnavailableItemIsTransient(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-2',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'es_rejected_execution_exception: rejected',
            'code' => 'backend_unavailable',
        ]);

        $this->assertSame(FailureClass::Transient, $result->failureClass());
        $this->assertFalse($result->failureClass()->isPermanent());
    }

    public function testDocumentMissingItemIsPermanent(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-3',
            'operation' => 'update_products',
            'status' => 'error',
            'error' => 'document_missing_exception',
            'code' => 'document_missing',
        ]);

        $this->assertSame(FailureClass::PermanentItem, $result->failureClass());
    }

    /**
     * An older engine sends no per-item code. A failed item with no code must be
     * treated as TRANSIENT, so the chunk is retried rather than dropped.
     */
    public function testMissingCodeOnAFailedItemIsTransient(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-4',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'something went wrong',
        ]);

        $this->assertNull($result->code);
        $this->assertNull($result->errorCode());
        $this->assertSame(FailureClass::Transient, $result->failureClass());
    }

    public function testUnknownCodeIsTransient(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-5',
            'operation' => 'index_products',
            'status' => 'error',
            'code' => 'some_new_code',
        ]);

        $this->assertNull($result->errorCode());
        $this->assertSame(FailureClass::Transient, $result->failureClass());
    }

    public function testSuccessfulItemHasNoFailureClass(): void
    {
        $result = ItemResult::fromArray([
            'id' => 'prod-6',
            'operation' => 'index_products',
            'status' => 'created',
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertNull($result->code);
        $this->assertNull($result->failureClass());
    }

    public function testCodeRoundTripsThroughJsonSerialize(): void
    {
        $result = new ItemResult(
            id: 'prod-7',
            operation: BulkOperationType::INDEX_PRODUCTS,
            status: 'error',
            error: 'bad product',
            code: 'validation_error',
        );

        $this->assertSame([
            'id' => 'prod-7',
            'operation' => 'index_products',
            'status' => 'error',
            'error' => 'bad product',
            'code' => 'validation_error',
        ], $result->jsonSerialize());
    }

    public function testCodeIsOmittedFromJsonWhenAbsent(): void
    {
        $result = new ItemResult(
            id: 'prod-8',
            operation: BulkOperationType::INDEX_PRODUCTS,
            status: 'created',
        );

        $this->assertArrayNotHasKey('code', $result->jsonSerialize());
    }
}
