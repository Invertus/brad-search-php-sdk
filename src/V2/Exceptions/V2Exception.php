<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

use BradSearch\SyncSdk\Exceptions\ClassifiedFailure;
use BradSearch\SyncSdk\Exceptions\FailureClass;
use BradSearch\SyncSdk\Exceptions\SyncSdkException;

/**
 * Base exception for all V2 SDK exceptions.
 */
class V2Exception extends SyncSdkException implements ClassifiedFailure
{
    /**
     * Defaults to TRANSIENT, like ApiException does. Every value object under
     * ValueObjects/Response/ throws through this base while reading what the
     * engine sent back, so an engine that renames a response field must not
     * look like a catalog of invalid products.
     *
     * Subclasses narrow this: InvalidProductException is PERMANENT_ITEM.
     */
    public function failureClass(): FailureClass
    {
        return FailureClass::Transient;
    }
}
