<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Exceptions;

use BradSearch\SyncSdk\Adapters\MagentoAdapter;
use BradSearch\SyncSdk\Exceptions\ClassifiedFailure;
use BradSearch\SyncSdk\Exceptions\ErrorCode;
use BradSearch\SyncSdk\Exceptions\FailureClass;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidLocaleException;
use BradSearch\SyncSdk\V2\Exceptions\InvalidProductException;
use BradSearch\SyncSdk\V2\Exceptions\V2Exception;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\Response\BulkOperationsResponse;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use PHPUnit\Framework\TestCase;

/**
 * AC-7: client-side validation failures report PERMANENT_ITEM through the same
 * FailureClass API the HTTP exceptions use.
 */
class FailureClassTest extends TestCase
{
    public function testEnumIsPermanentOnlyForPermanentClasses(): void
    {
        $this->assertTrue(FailureClass::PermanentItem->isPermanent());
        $this->assertTrue(FailureClass::PermanentConfig->isPermanent());
        $this->assertFalse(FailureClass::Transient->isPermanent());
    }

    /**
     * @return list<array{ErrorCode, FailureClass}>
     */
    public static function errorCodeProvider(): array
    {
        return [
            [ErrorCode::ValidationError, FailureClass::PermanentItem],
            [ErrorCode::DocumentMissing, FailureClass::PermanentItem],
            [ErrorCode::ConfigMalformed, FailureClass::PermanentConfig],
            [ErrorCode::ConfigNotFound, FailureClass::PermanentConfig],
            [ErrorCode::BackendUnavailable, FailureClass::Transient],
            [ErrorCode::PlatformUnavailable, FailureClass::Transient],
            [ErrorCode::InternalError, FailureClass::Transient],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errorCodeProvider')]
    public function testEveryErrorCodeHasAFailureClass(ErrorCode $code, FailureClass $expected): void
    {
        $this->assertSame($expected, $code->failureClass());
    }

    public function testUnknownAndNullCodesResolveToNull(): void
    {
        $this->assertNull(ErrorCode::tryFromNullable(null));
        $this->assertNull(ErrorCode::tryFromNullable('something_new'));
        $this->assertSame(ErrorCode::ValidationError, ErrorCode::tryFromNullable('validation_error'));
    }

    public function testAdapterValidationExceptionIsPermanentItem(): void
    {
        $e = new ValidationException('No operations provided');

        $this->assertInstanceOf(ClassifiedFailure::class, $e);
        $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
    }

    public function testInvalidProductIsPermanentItem(): void
    {
        $e = new InvalidProductException('bad');

        $this->assertInstanceOf(ClassifiedFailure::class, $e);
        $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
    }

    /**
     * A locale comes from the integration's language configuration, not from one
     * product row.
     */
    public function testInvalidLocaleIsPermanentConfig(): void
    {
        $e = new InvalidLocaleException('bad');

        $this->assertInstanceOf(ClassifiedFailure::class, $e);
        $this->assertSame(FailureClass::PermanentConfig, $e->failureClass());
    }

    /**
     * The V2 base defaults to TRANSIENT, like ApiException. Response value
     * objects throw through it, so an unclassified V2 failure is retried rather
     * than dropping a product.
     */
    public function testBareV2ExceptionIsTransient(): void
    {
        foreach ([new V2Exception('bad'), new InvalidArgumentException('bad')] as $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::Transient, $e->failureClass());
        }
    }

    /**
     * The regression this guards: an engine deploy that renames a response field
     * must not classify as a catalog of invalid products. With brad-app#580 a
     * PERMANENT_ITEM here would fail the chunk on attempt one and drop it.
     */
    public function testMalformedEngineResponseIsTransient(): void
    {
        try {
            BulkOperationsResponse::fromArray([
                'status' => 'success',
                'total_operations' => 1,
                'successful_operations' => 1,
                'failed_operations' => 0,
                // 'results' renamed by a newer engine
                'items' => [],
            ]);
            $this->fail('expected the malformed response to be rejected');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::Transient, $e->failureClass());
        }
    }

    /**
     * The named case in the ticket: one product with an unsupported image
     * extension must classify as PERMANENT_ITEM, not as something retryable.
     */
    public function testUnsupportedImageExtensionIsPermanentItem(): void
    {
        try {
            new ImageUrl(
                small: 'https://cdn.example.com/img/product.bmp',
                medium: 'https://cdn.example.com/img/product.bmp',
            );
            $this->fail('expected the unsupported extension to be rejected');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
        }
    }

    public function testProductFromArrayFailureIsPermanentItem(): void
    {
        try {
            Product::fromArray([
                'id' => 'prod-1',
                'sku' => 'SKU-1',
                'imageUrl' => ['small' => 'https://cdn.example.com/a.bmp', 'medium' => 'https://cdn.example.com/a.bmp'],
            ]);
            $this->fail('expected the unsupported extension to be rejected');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
        }
    }

    public function testMagentoAdapterRejectsMalformedPayloadAsPermanentItem(): void
    {
        $adapter = new MagentoAdapter();

        try {
            $adapter->transform(['no data key here']);
            $this->fail('expected the malformed payload to be rejected');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
        }
    }
}
