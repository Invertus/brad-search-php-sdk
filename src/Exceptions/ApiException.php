<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ApiException extends SyncSdkException
{
    /**
     * Enough to carry any engine error message, including a Go decode error naming the field,
     * without keeping a whole search response or an HTML error page in memory. Callers that log
     * the body cut it further.
     */
    public const MAX_RESPONSE_BODY_BYTES = 16384;

    public readonly ?string $responseBody;

    /** True when the engine's body was longer than MAX_RESPONSE_BODY_BYTES and was cut. */
    public readonly bool $responseBodyTruncated;

    public function __construct(
        string $message = '',
        public readonly int $statusCode = 0,
        ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->responseBodyTruncated = $responseBody !== null && strlen($responseBody) > self::MAX_RESPONSE_BODY_BYTES;
        $this->responseBody = $this->responseBodyTruncated
            ? substr($responseBody, 0, self::MAX_RESPONSE_BODY_BYTES)
            : $responseBody;
    }
}
