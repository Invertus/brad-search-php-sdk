<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

use BradSearch\SyncSdk\Exceptions\ApiException;

/**
 * cURL-based GraphQL client for Magento
 */
class MagentoGraphQLClient
{
    public function __construct(
        private readonly MagentoConfig $config
    ) {
    }

    /**
     * Execute a GraphQL query against Magento
     *
     * @param string $query The GraphQL query string
     * @param array $variables Query variables
     * @return array The response data
     * @throws ApiException
     */
    public function query(string $query, array $variables = []): array
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new ApiException('Failed to initialize cURL');
        }

        try {
            $payload = ['query' => $query];

            if (!empty($variables)) {
                $payload['variables'] = $variables;
            }

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];

            if ($this->config->bearerToken !== null) {
                $headers[] = 'Authorization: Bearer ' . $this->config->bearerToken;
            }

            $options = [
                CURLOPT_URL => $this->config->graphqlUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->config->timeout,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => $this->config->verifySSL,
                CURLOPT_SSL_VERIFYHOST => $this->config->verifySSL ? 2 : 0,
            ];

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
                    "GraphQL request failed with status {$statusCode}",
                    $statusCode,
                    $response
                );
            }

            try {
                $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ApiException(
                    "Failed to decode JSON response: {$e->getMessage()}",
                    $statusCode,
                    $response
                );
            }

            if (!is_array($decoded)) {
                throw new ApiException('Expected JSON object in response', $statusCode, $response);
            }

            // Check for GraphQL errors
            if (isset($decoded['errors']) && is_array($decoded['errors']) && !empty($decoded['errors'])) {
                $errorMessages = array_map(
                    fn($error) => $error['message'] ?? 'Unknown error',
                    $decoded['errors']
                );
                throw new ApiException(
                    'GraphQL errors: ' . implode('; ', $errorMessages),
                    $statusCode,
                    $response
                );
            }

            return $decoded;
        } finally {
            curl_close($curl);
        }
    }
}
