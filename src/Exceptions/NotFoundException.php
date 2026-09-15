<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * The engine has no such application, configuration or index (HTTP 404).
 *
 * The tenant is not set up as the caller expects, so a retry cannot help.
 */
class NotFoundException extends ApiException
{
    public function failureClass(): FailureClass
    {
        return ErrorCode::tryFromNullable($this->errorCode)?->failureClass() ?? FailureClass::PermanentConfig;
    }
}
