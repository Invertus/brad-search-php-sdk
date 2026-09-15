<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * The engine or a backend behind it failed in a way that may pass on a retry
 * (HTTP 5xx or 429).
 *
 * The SDK already retried this on its own retry budget for idempotent calls, so
 * reaching the caller means that budget is spent. The caller's own retry layer
 * decides what happens next.
 */
class TransientApiException extends ApiException
{
    public function failureClass(): FailureClass
    {
        return ErrorCode::tryFromNullable($this->errorCode)?->failureClass() ?? FailureClass::Transient;
    }
}
