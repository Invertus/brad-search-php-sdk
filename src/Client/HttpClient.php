<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client;

use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Exceptions\ApiException;
use CurlHandle;

class HttpClient
{
    public function __construct(
        private readonly SyncConfig $config
    ) {
    }

    /**
     * Make a GET request
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * Make a POST request
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PUT request
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Make a PATCH request
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Make HTTP request
     */
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

            // Handle empty responses (e.g., from DELETE requests)
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