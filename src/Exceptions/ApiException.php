<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ApiException extends SyncSdkException implements ClassifiedFailure
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

    /**
     * @param string|null $errorCode The engine's machine-readable error code, when it sent one.
     *                               Null for an older engine or a non-JSON body.
     *                               Named errorCode because Exception::$code is already
     *                               taken and holds the HTTP status here.
     */
    public function __construct(
        string $message = '',
        public readonly int $statusCode = 0,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->responseBodyTruncated = $responseBody !== null && strlen($responseBody) > self::MAX_RESPONSE_BODY_BYTES;
        // Cut on a character boundary so a body that lands in a log or a JSON context stays valid UTF-8.
        $this->responseBody = $this->responseBodyTruncated
            ? mb_strcut($responseBody, 0, self::MAX_RESPONSE_BODY_BYTES, 'UTF-8')
            : $responseBody;
    }

    /**
     * Defaults to TRANSIENT so an unclassified failure is retried rather than
     * dropping a product. Subclasses narrow this.
     */
    public function failureClass(): FailureClass
    {
        return ErrorCode::tryFromNullable($this->errorCode)?->failureClass() ?? FailureClass::Transient;
    }
}
