<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ValidationException extends SyncSdkException implements ClassifiedFailure
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        string $message = '',
        public readonly array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Client-side input validation: the data itself is wrong, so re-sending it
     * unchanged gives the same result.
     */
    public function failureClass(): FailureClass
    {
        return FailureClass::PermanentItem;
    }
}
