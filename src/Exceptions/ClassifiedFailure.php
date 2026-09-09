<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * An exception that says whether retrying can help.
 *
 * Implemented by both sides of the SDK: exceptions raised from an engine response
 * and exceptions raised by client-side validation before a request is even sent.
 * A caller can therefore classify any SDK failure the same way.
 */
interface ClassifiedFailure
{
    public function failureClass(): FailureClass;
}
