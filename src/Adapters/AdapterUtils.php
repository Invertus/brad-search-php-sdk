<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

/**
 * Common utility methods shared across platform adapters
 *
 * This class contains reusable transformation logic that can be used by
 * PrestaShopAdapter, ShopifyAdapter, MagentoAdapter, and any future adapters.
 */
final class AdapterUtils
{
    private function __construct()
    {
        // Prevent instantiation - static utility class
    }

    /**
     * Build imageUrl structure in SDK-compatible format (small/medium keys)
     *
     * @param string|null $smallUrl URL for small image
     * @param string|null $mediumUrl URL for medium image
     * @return array{small?: string, medium?: string} Empty array if no valid URLs
     */
    public static function buildImageUrl(?string $smallUrl, ?string $mediumUrl): array
    {
        $result = [];

        if ($smallUrl !== null && $smallUrl !== '') {
            $result['small'] = $smallUrl;
        }

        if ($mediumUrl !== null && $mediumUrl !== '') {
            $result['medium'] = $mediumUrl;
        }

        return $result;
    }

    /**
     * Extract URL from a nested image structure like {url: string, label: string}
     *
     * @param array $data The product or variant data
     * @param string $field The field name (e.g., 'image', 'small_image', 'thumbnail')
     * @return string|null The URL or null if not found/invalid
     */
    public static function extractNestedImageUrl(array $data, string $field): ?string
    {
        if (
            isset($data[$field]['url']) &&
            is_string($data[$field]['url']) &&
            $data[$field]['url'] !== ''
        ) {
            return $data[$field]['url'];
        }

        return null;
    }

    /**
     * Extract URL directly from a field
     *
     * @param array $data The data array
     * @param string $field The field name
     * @return string|null The URL or null if not found/invalid
     */
    public static function extractDirectUrl(array $data, string $field): ?string
    {
        if (
            isset($data[$field]) &&
            is_string($data[$field]) &&
            $data[$field] !== ''
        ) {
            return $data[$field];
        }

        return null;
    }

    /**
     * Safely get a nested value from an array
     *
     * @param array $data The array to traverse
     * @param array $keys The keys to traverse (e.g., ['price_range', 'minimum_price', 'value'])
     * @param mixed $default Default value if path doesn't exist
     * @return mixed The value at the nested path or default
     */
    public static function getNestedValue(array $data, array $keys, mixed $default = null): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Cast a value to string, handling various types safely
     *
     * @param mixed $value The value to cast
     * @return string The string value
     */
    public static function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Build error entry for transformation errors
     *
     * @param string $type Error type ('transformation_error', 'invalid_structure')
     * @param int $index Product index in the array
     * @param string $productId Product ID if available
     * @param string $message Error message
     * @param string|null $exception Exception class name
     * @return array The error entry
     */
    public static function buildError(
        string $type,
        int $index,
        string $productId,
        string $message,
        ?string $exception = null
    ): array {
        return [
            'type' => $type,
            'product_index' => $index,
            'product_id' => $productId,
            'message' => $message,
            'exception' => $exception,
        ];
    }
}
