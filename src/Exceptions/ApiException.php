<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

class ApiException extends SyncSdkException implements ClassifiedFailure
{
    /**
     * @param string|null $errorCode The engine's machine-readable error code, when it sent one.
     *                               Null for an older engine or a non-JSON body.
     *                               Named errorCode because Exception::$code is already
     *                               taken and holds the HTTP status here.
     */
    public function __construct(
        string $message = '',
        public readonly int $statusCode = 0,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
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
