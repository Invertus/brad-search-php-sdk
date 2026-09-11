<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

use BradSearch\SyncSdk\Exceptions\FailureClass;

/**
 * A product the caller handed us is invalid: an unsupported image extension, a
 * negative price, a missing id. Re-sending the same product gives the same
 * result, so the item is dropped rather than retried.
 */
class InvalidProductException extends InvalidArgumentException
{
    public function failureClass(): FailureClass
    {
        return FailureClass::PermanentItem;
    }
}
