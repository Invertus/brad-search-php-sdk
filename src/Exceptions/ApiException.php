<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ApiException extends SyncSdkException
{
    public function __construct(
        string $message = '',
        public readonly int $statusCode = 0,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
} 