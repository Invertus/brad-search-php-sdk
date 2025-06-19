<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ValidationException extends SyncSdkException
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
} 