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
    ) {}

    /**
     * Validate a single product against the field configuration
     */
    public function validateProduct(array $product): void
    {
        $errors = [];

        // Ensure the product has an 'id' field if it's configured
        if (isset($this->fieldConfiguration['id']) && !array_key_exists('id', $product)) {
            $errors[] = "Product must have an 'id' field";
        }

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

            case FieldType::FLOAT:
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$fieldName}' must be a numeric value";
                } elseif (is_infinite((float)$value) || is_nan((float)$value)) {
                    $errors[] = "Field '{$fieldName}' must be a finite numeric value";
                }
                break;

            case FieldType::INTEGER:
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$fieldName}' must be a numeric value";
                } elseif (!is_int($value) && !ctype_digit((string)$value)) {
                    $errors[] = "Field '{$fieldName}' must be an integer value";
                } elseif (is_string($value) && (int)$value != $value) {
                    $errors[] = "Field '{$fieldName}' must be an integer value";
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

            case FieldType::NAME_VALUE_LIST:
                if (!is_array($value)) {
                    $errors[] = "Field '{$fieldName}' must be an array for name-value list type";
                } else {
                    $errors = array_merge($errors, $this->validateNameValueList($fieldName, $value));
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

            // Validate required variant fields (based on Go ProductVariant struct)
            $requiredFields = ['id', 'sku', 'url', 'attributes'];
            foreach ($requiredFields as $requiredField) {
                if (!isset($variant[$requiredField])) {
                    $errors[] = "Field '{$fieldName}' variant at index {$index} must have '{$requiredField}' field";
                    continue 2; // Skip to next variant if any required field is missing
                }
            }

            // Validate field types
            if (!is_string($variant['id']) || empty(trim($variant['id']))) {
                $errors[] = "Field '{$fieldName}' variant at index {$index} 'id' must be a non-empty string";
            }

            if (!is_string($variant['url']) || !filter_var($variant['url'], FILTER_VALIDATE_URL)) {
                $errors[] = "Field '{$fieldName}' variant at index {$index} 'url' must be a valid URL";
            }

            if (!is_array($variant['attributes'])) {
                $errors[] = "Field '{$fieldName}' variant at index {$index} 'attributes' must be an array";
                continue;
            }

            // Validate variant attributes against field config attributes
            if ($fieldConfig->attributes !== null) {
                foreach ($fieldConfig->attributes as $attrName => $attrConfig) {
                    if (isset($variant['attributes'][$attrName])) {
                        $attribute = $variant['attributes'][$attrName];

                        // Validate the attribute structure (must have 'name' and 'value')
                        if (!is_array($attribute)) {
                            $errors[] = "Field '{$fieldName}' variant at index {$index} attribute '{$attrName}' must be an object with 'name' and 'value' fields";
                            continue;
                        }

                        if (!isset($attribute['name']) || !isset($attribute['value'])) {
                            $errors[] = "Field '{$fieldName}' variant at index {$index} attribute '{$attrName}' must have 'name' and 'value' fields";
                            continue;
                        }

                        if (!is_string($attribute['name']) || $attribute['name'] !== $attrName) {
                            $errors[] = "Field '{$fieldName}' variant at index {$index} attribute '{$attrName}' name field must match the attribute key";
                        }

                        // Validate the attribute value against the field config
                        $attrErrors = $this->validateField(
                            "{$fieldName}.variants[{$index}].attributes.{$attrName}.value",
                            $attribute['value'],
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

    /**
     * Validate name-value list structure
     *
     * @return array<string>
     */
    private function validateNameValueList(string $fieldName, array $nameValueList): array
    {
        $errors = [];

        foreach ($nameValueList as $index => $item) {
            if (!is_array($item)) {
                $errors[] = "Field '{$fieldName}' item at index {$index} must be an object with 'name' and 'value' fields";
                continue;
            }

            // Validate required fields
            if (!isset($item['name'])) {
                $errors[] = "Field '{$fieldName}' item at index {$index} must have a 'name' field";
            } elseif (!is_string($item['name']) || empty(trim($item['name']))) {
                $errors[] = "Field '{$fieldName}' item at index {$index} 'name' must be a non-empty string";
            }

            if (!isset($item['value'])) {
                $errors[] = "Field '{$fieldName}' item at index {$index} must have a 'value' field";
            } elseif (!is_string($item['value'])) {
                $errors[] = "Field '{$fieldName}' item at index {$index} 'value' must be a string";
            }
        }

        return $errors;
    }
}
