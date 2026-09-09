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
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
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

    public function testV2ValueObjectExceptionsArePermanentItem(): void
    {
        foreach ([new InvalidArgumentException('bad'), new InvalidLocaleException('bad')] as $e) {
            $this->assertInstanceOf(ClassifiedFailure::class, $e);
            $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
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
