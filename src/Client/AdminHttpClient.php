<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client;

use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Exceptions\ApiException;

/**
 * HTTP client for admin operations that includes the X-Admin-Action header.
 */
class AdminHttpClient
{
    public function __construct(
        private readonly SyncConfig $config
    ) {
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new ApiException('Failed to initialize cURL');
        }

        try {
            $url = rtrim($this->config->baseUrl, '/') . '/' . ltrim($endpoint, '/');

            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->config->timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->config->authToken,
                    'X-Admin-Action: true',
                ],
                CURLOPT_SSL_VERIFYPEER => $this->config->verifySSL,
                CURLOPT_SSL_VERIFYHOST => $this->config->verifySSL ? 2 : 0,
            ];

            if ($data !== null) {
                $json = json_encode($data, JSON_THROW_ON_ERROR);
                $options[CURLOPT_POSTFIELDS] = $json;
            }

            curl_setopt_array($curl, $options);

            $response = curl_exec($curl);

            if ($response === false) {
                $error = curl_error($curl);
                throw new ApiException("cURL error: {$error}");
            }

            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (!is_string($response)) {
                throw new ApiException('Invalid response from server');
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new ApiException(
                    "API request failed with status {$statusCode}",
                    $statusCode,
                    $response
                );
            }

            if (empty($response)) {
                return [];
            }

            try {
                $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ApiException("Failed to decode JSON response: {$e->getMessage()}", $statusCode, $response);
            }

            if (!is_array($decoded)) {
                throw new ApiException('Expected JSON object in response', $statusCode, $response);
            }

            return $decoded;
        } finally {
            curl_close($curl);
        }
    }
}
