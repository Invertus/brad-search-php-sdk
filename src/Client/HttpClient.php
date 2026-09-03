<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client;

use BradSearch\SyncSdk\Client\Transport\CurlTransport;
use BradSearch\SyncSdk\Client\Transport\HttpRequest;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Client\Transport\NativeSleeper;
use BradSearch\SyncSdk\Client\Transport\Sleeper;
use BradSearch\SyncSdk\Client\Transport\Transport;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\TransportException;

class HttpClient
{
    private readonly Transport $transport;

    private readonly Sleeper $sleeper;

    /**
     * @param list<string> $extraHeaders Additional raw header lines sent with every request
     */
    public function __construct(
        private readonly SyncConfig $config,
        ?Transport $transport = null,
        ?Sleeper $sleeper = null,
        private readonly array $extraHeaders = [],
    ) {
        $this->transport = $transport ?? new CurlTransport();
        $this->sleeper = $sleeper ?? new NativeSleeper();
    }

    /**
     * Make a GET request
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint, null, true);
    }

    /**
     * Make a POST request.
     *
     * POST is not retried unless the caller marks it idempotent (bulk operations keyed by id,
     * pure computations). Configuration mutations must leave the flag false.
     */
    public function post(string $endpoint, array $data = [], bool $idempotent = false): array
    {
        return $this->request('POST', $endpoint, $data, $idempotent);
    }

    /**
     * Make a PUT request
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data, true);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint, null, true);
    }

    /**
     * Make a PATCH request
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data, true);
    }

    /**
     * Send the request, retrying transport failures, 5xx and 429 when the call is idempotent.
     */
    private function request(string $method, string $endpoint, ?array $data, bool $idempotent): array
    {
        $request = $this->buildRequest($method, $endpoint, $data);
        $policy = $this->config->retryPolicy;
        $maxAttempts = $idempotent ? $policy->maxAttempts : 1;
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->transport->send($request);
            } catch (TransportException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $this->sleeper->sleep($policy->delayBeforeRetry($attempt));
                continue;
            }

            if ($attempt < $maxAttempts && $policy->isRetryableStatus($response->statusCode)) {
                $this->sleeper->sleep($policy->delayBeforeRetry($attempt));
                continue;
            }

            return $this->decode($response);
        }
    }

    private function buildRequest(string $method, string $endpoint, ?array $data): HttpRequest
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->authToken,
            ...$this->extraHeaders,
        ];

        return new HttpRequest(
            method: $method,
            url: rtrim($this->config->baseUrl, '/') . '/' . ltrim($endpoint, '/'),
            headers: $headers,
            body: $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
            timeout: $this->config->timeout,
            connectTimeout: $this->config->connectTimeout,
            verifySSL: $this->config->verifySSL,
        );
    }

    private function decode(HttpResponse $response): array
    {
        $statusCode = $response->statusCode;
        $body = $response->body;

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException(
                "API request failed with status {$statusCode}",
                $statusCode,
                $body
            );
        }

        // Handle empty responses (e.g., from DELETE requests)
        if (empty($body)) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException("Failed to decode JSON response: {$e->getMessage()}", $statusCode, $body);
        }

        if (!is_array($decoded)) {
            throw new ApiException('Expected JSON object in response', $statusCode, $body);
        }

        return $decoded;
    }
}
