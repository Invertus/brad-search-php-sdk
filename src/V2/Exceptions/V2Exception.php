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
     * Every V2 exception is raised while building a value object from client data
     * (an unsupported image extension, a bad locale, a wrong field type). The
     * offending item is invalid, so a retry cannot help.
     */
    public function failureClass(): FailureClass
    {
        return FailureClass::PermanentItem;
    }
}
