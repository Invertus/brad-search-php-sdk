<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * No HTTP response was obtained: connection refused, connect/read timeout, DNS or TLS failure.
 *
 * Extends ApiException so existing catch blocks keep working; statusCode is always 0 and
 * responseBody always null.
 */
class TransportException extends ApiException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, null, $previous);
    }
}
