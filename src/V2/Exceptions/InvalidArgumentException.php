<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

/**
 * Base exception for invalid argument validation errors in V2 ValueObjects.
 */
class InvalidArgumentException extends V2Exception
{
    public function __construct(
        string $message = '',
        public readonly string $argumentName = '',
        public readonly mixed $invalidValue = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
