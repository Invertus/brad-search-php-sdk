<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Validators;

use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Enums\FieldType;
use BradSearch\SyncSdk\Exceptions\ValidationException;

class DataValidator
{
    /**
     * @param array<string, FieldConfig> $fieldConfiguration
     */
    public function __construct(
        private readonly array $fieldConfiguration
    ) {
    }

    /**
     * Validate a single product against the field configuration
     */
    public function validateProduct(array $product): void
    {
        $errors = [];

        foreach ($this->fieldConfiguration as $fieldName => $fieldConfig) {
            if (!array_key_exists($fieldName, $product)) {
                // Field is not present - this might be okay for optional fields
                continue;
            }

            $value = $product[$fieldName];
            $fieldErrors = $this->validateField($fieldName, $value, $fieldConfig);
            $errors = array_merge($errors, $fieldErrors);
        }

        if (!empty($errors)) {
            throw new ValidationException('Product validation failed', $errors);
        }
    }

    /**
     * Validate multiple products
     *
     * @param array<array> $products
     */
    public function validateProducts(array $products): void
    {
        foreach ($products as $index => $product) {
            try {
                $this->validateProduct($product);
            } catch (ValidationException $e) {
                $errors = array_map(
                    fn(string $error) => "Product {$index}: {$error}",
                    $e->errors
                );
                throw new ValidationException("Validation failed for product at index {$index}", $errors);
            }
        }
    }

    /**
     * @return array<string>
     */
    private function validateField(string $fieldName, mixed $value, FieldConfig $fieldConfig): array
    {
        $errors = [];

        switch ($fieldConfig->type) {
            case FieldType::TEXT:
            case FieldType::TEXT_KEYWORD:
            case FieldType::KEYWORD:
                if (!is_string($value)) {
                    $errors[] = "Field '{$fieldName}' must be a string";
                }
                break;

            case FieldType::URL:
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "Field '{$fieldName}' must be a valid URL";
                }
                break;

            case FieldType::IMAGE_URL:
                if (!is_array($value)) {
                    $errors[] = "Field '{$fieldName}' must be an array with image size keys";
                } else {
                    $errors = array_merge($errors, $this->validateImageUrls($fieldName, $value));
                }
                break;

            case FieldType::HIERARCHY:
                if (!is_array($value)) {
                    $errors[] = "Field '{$fieldName}' must be an array for hierarchy type";
                } else {
                    foreach ($value as $item) {
                        if (!is_string($item)) {
                            $errors[] = "Field '{$fieldName}' hierarchy items must be strings";
                            break;
                        }
                    }
                }
                break;

            case FieldType::VARIANTS:
                if (!is_array($value)) {
                    $errors[] = "Field '{$fieldName}' must be an array for variants type";
                } else {
                    $errors = array_merge($errors, $this->validateVariants($fieldName, $value, $fieldConfig));
                }
                break;
        }

        return $errors;
    }

    /**
     * @return array<string>
     */
    private function validateVariants(string $fieldName, array $variants, FieldConfig $fieldConfig): array
    {
        $errors = [];

        foreach ($variants as $index => $variant) {
            if (!is_array($variant)) {
                $errors[] = "Field '{$fieldName}' variant at index {$index} must be an array";
                continue;
            }

            if (!isset($variant['attributes']) || !is_array($variant['attributes'])) {
                $errors[] = "Field '{$fieldName}' variant at index {$index} must have 'attributes' array";
                continue;
            }

            // Validate variant attributes against field config attributes
            if ($fieldConfig->attributes !== null) {
                foreach ($fieldConfig->attributes as $attrName => $attrConfig) {
                    if (isset($variant['attributes'][$attrName])) {
                        $attrErrors = $this->validateField(
                            "{$fieldName}.variants[{$index}].attributes.{$attrName}",
                            $variant['attributes'][$attrName],
                            $attrConfig
                        );
                        $errors = array_merge($errors, $attrErrors);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate image URLs object with size keys
     *
     * @return array<string>
     */
    private function validateImageUrls(string $fieldName, array $imageUrls): array
    {
        $errors = [];

        if (empty($imageUrls)) {
            $errors[] = "Field '{$fieldName}' cannot be an empty array";
            return $errors;
        }

        // Check for expected size keys (based on Go implementation: small, medium)
        $expectedSizes = ['small', 'medium'];
        $validSizesFound = false;

        foreach ($imageUrls as $size => $url) {
            if (!is_string($size) || empty(trim($size))) {
                $errors[] = "Field '{$fieldName}' must have valid size keys (e.g., 'small', 'medium')";
                continue;
            }

            if (in_array($size, $expectedSizes, true)) {
                $validSizesFound = true;
            }

            if (!is_string($url) || !$this->isValidImageUrl($url)) {
                $errors[] = "Field '{$fieldName}[{$size}]' must be a valid image URL";
            }
        }

        // Recommend standard sizes if none are found
        if (!$validSizesFound) {
            $errors[] = "Field '{$fieldName}' should include standard size keys like 'small' or 'medium'";
        }

        return $errors;
    }

    private function isValidImageUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Basic check for image file extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions, true);
    }
} 