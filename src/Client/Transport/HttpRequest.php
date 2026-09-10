<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client\Transport;

final readonly class HttpRequest
{
    /**
     * @param list<string> $headers Raw header lines, e.g. "Content-Type: application/json"
     * @param string|null $body Encoded request body, or null to send none
     * @param int $timeout Total request timeout in seconds
     * @param int $connectTimeout Connection-establishment timeout in seconds
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public ?string $body,
        public int $timeout,
        public int $connectTimeout,
        public bool $verifySSL,
    ) {
    }
}
