<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * No HTTP response was obtained: connection refused, connect/read timeout, DNS or TLS failure.
 *
 * Extends ApiException so existing catch blocks keep working; statusCode is always 0 and
 * responseBody always null. $connectionFailed is true when the failure happened before any byte
 * reached the server, which is the only case the SDK retries on its own: a read timeout means the
 * engine may still be processing the request, and re-sending it immediately only adds load.
 */
class TransportException extends ApiException
{
    public function __construct(
        string $message,
        public readonly int $curlErrno = 0,
        public readonly bool $connectionFailed = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, null, $previous);
    }

    /**
     * Whether a cURL failure happened in the connection phase (DNS, TCP connect, TLS handshake,
     * or a timeout that fired before the connection was established).
     *
     * @param float $connectTime CURLINFO_CONNECT_TIME; 0.0 means the connection was never established
     */
    public static function isConnectionFailure(int $curlErrno, float $connectTime): bool
    {
        $connectPhaseErrors = [
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_SSL_CONNECT_ERROR,
        ];

        if (in_array($curlErrno, $connectPhaseErrors, true)) {
            return true;
        }

        return $curlErrno === CURLE_OPERATION_TIMEDOUT && $connectTime <= 0.0;
    }
}
