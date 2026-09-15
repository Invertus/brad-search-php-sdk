<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * The engine rejected the request or one item as invalid (HTTP 400 or 422).
 *
 * Retrying the same payload gives the same answer. Named ValidationFailedException
 * rather than ValidationException because that name is already taken by the
 * adapters' client-side input validation.
 */
class ValidationFailedException extends ApiException
{
    public function failureClass(): FailureClass
    {
        // config_malformed arrives with a 422 but is a broken tenant config, not
        // one bad product, so let the code decide when it is present.
        return ErrorCode::tryFromNullable($this->errorCode)?->failureClass() ?? FailureClass::PermanentItem;
    }
}
