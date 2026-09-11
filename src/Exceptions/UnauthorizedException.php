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
    /**
     * Always PERMANENT_CONFIG, whatever code rode along with the 401/403. The
     * factory already refuses to let a code pick a different class here; letting
     * it pick the failure class would undo that, because a caller switches on the
     * failure class, not on the exception name. A 401 carrying
     * backend_unavailable would otherwise tell the caller to retry a bad token.
     */
    public function failureClass(): FailureClass
    {
        return FailureClass::PermanentConfig;
    }
}
