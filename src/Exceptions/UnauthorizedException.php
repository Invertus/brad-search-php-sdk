<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * The engine rejected the credentials (HTTP 401 or 403).
 *
 * A wrong or expired token is a configuration problem, so a retry with the same
 * token cannot help.
 */
class UnauthorizedException extends ApiException
{
    public function failureClass(): FailureClass
    {
        return ErrorCode::tryFromNullable($this->errorCode)?->failureClass() ?? FailureClass::PermanentConfig;
    }
}
