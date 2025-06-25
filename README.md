# Brad Search Synchronization PHP SDK

A comprehensive PHP SDK for synchronizing data with the Brad Search API. This SDK provides type-safe field configuration, strict data validation, and robust error handling.

## Requirements

- PHP 8.4 or higher
- cURL extension
- JSON extension

## Installation

```bash
composer require bradsearch/search-sync-sdk
```

## Basic Usage

### 1. Configuration

```php
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\SynchronizationApiSdk;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;

// Create configuration
$config = new SyncConfig(
    baseUrl: 'https://your-api-endpoint.com',
    authToken: 'your-auth-token',
    timeout: 30,
    verifySSL: true
);

// Define field configuration
$fieldConfiguration = FieldConfigBuilder::ecommerceFields();
// Or build custom fields:
// $fieldConfiguration = [
//     'title' => FieldConfigBuilder::textKeyword(),
//     'price' => FieldConfigBuilder::keyword(),
//     'categories' => FieldConfigBuilder::hierarchy(),
// ];

// To add custom fields or override existing default ecommerce field config with custom values:
// $variants = ['material'];
// #fieldConfiguration = FieldConfigBuilder::addToEcommerceFields($variants);

// Initialize SDK
$syncSdk = new SynchronizationApiSdk($config, $fieldConfiguration);
```

### 2. Index Management

```php
// Create an index
$syncSdk->createIndex('my-product-index');

// Create an index with locales
$syncSdk->createIndex('my-product-index', ['en', 'fr', 'de']);

// Delete an index
$syncSdk->deleteIndex('my-product-index');
```

### 3. Product Synchronization

#### Single Product

```php
$product = [
    'id' => 'product-123',
    'name' => 'Premium T-Shirt',
    'brand' => 'BrandName',
    'sku' => 'TSH-001',
    'categoryDefault' => 'Clothing > T-Shirts',
    'categories' => [
        'Clothing',
        'Clothing > T-Shirts',
        'Clothing > T-Shirts > Premium'
    ],
    'variants' => [
        [
            'id' => 'variant-1',
            'sku' => 'TSH-001-S-RED',
            'attributes' => [
                'size' => 'S',
                'color' => 'Red'
            ]
        ]
    ],
    'imageUrl' => [
        'small' => 'https://example.com/image-small.jpg',
        'medium' => 'https://example.com/image-medium.jpg'
    ],
    'productUrl' => 'https://shop.example.com/products/premium-tshirt'
];

$syncSdk->sync('my-product-index', $product);
```

#### Bulk Synchronization

```php
$products = [
    // ... array of product data
];

// Sync with default batch size (100)
$syncSdk->syncBulk('my-product-index', $products);

// Sync with custom batch size
$syncSdk->syncBulk('my-product-index', $products, 50);
```

## Field Types

The SDK supports the following field types:

- `TEXT_KEYWORD`: Full-text search with keyword matching
- `TEXT`: Full-text search only
- `KEYWORD`: Exact keyword matching
- `HIERARCHY`: Hierarchical categories
- `VARIANTS`: Product variants with attributes
- `IMAGE_URL`: Image URLs object with size keys (e.g., `{"small": "url", "medium": "url"}`)
- `URL`: Regular URLs with validation

## Custom Field Configuration

```php
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\Enums\FieldType;

$customFields = [
    'title' => FieldConfigBuilder::textKeyword(),
    'description' => FieldConfigBuilder::text(),
    'price' => FieldConfigBuilder::keyword(),
    'categories' => FieldConfigBuilder::hierarchy(),
    'website' => FieldConfigBuilder::url(),
    'image' => FieldConfigBuilder::imageUrl(),
    'variants' => FieldConfigBuilder::variants([
        'size' => FieldConfigBuilder::keyword(),
        'color' => FieldConfigBuilder::keyword(),
        'material' => FieldConfigBuilder::textKeyword(),
    ]),
];

// Or create manually
$customFields = [
    'title' => new FieldConfig(FieldType::TEXT_KEYWORD),
    'variants' => new FieldConfig(
        type: FieldType::VARIANTS,
        attributes: [
            'size' => new FieldConfig(FieldType::KEYWORD),
            'color' => new FieldConfig(FieldType::KEYWORD),
        ]
    ),
];
```

## Data Validation

The SDK provides strict data validation:

```php
// Validate a single product
try {
    $syncSdk->validateProduct($product);
    echo "Product is valid!";
} catch (ValidationException $e) {
    echo "Validation errors:\n";
    foreach ($e->errors as $error) {
        echo "- $error\n";
    }
}

// Validate multiple products
try {
    $syncSdk->validateProducts($products);
} catch (ValidationException $e) {
    // Handle validation errors
}
```

## Error Handling

The SDK uses typed exceptions for different error scenarios:

```php
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\ValidationException;
use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

try {
    $syncSdk->sync('my-index', $product);
} catch (ValidationException $e) {
    // Data validation failed
    echo "Validation errors: " . implode(', ', $e->errors);
} catch (ApiException $e) {
    // API request failed
    echo "API error {$e->statusCode}: {$e->getMessage()}";
    echo "Response: {$e->responseBody}";
} catch (InvalidFieldConfigException $e) {
    // Field configuration is invalid
    echo "Configuration error: {$e->getMessage()}";
}
```

## Advanced Usage

### Field Filtering

The SDK automatically filters product data to only include fields defined in the configuration. This ensures clean data and prevents sending unnecessary fields to the API.

### Batch Processing

Large datasets are automatically processed in batches to avoid memory issues and API limits:

```php
// Process 10,000 products in batches of 50
$syncSdk->syncBulk('my-index', $largeProductArray, 50);
```

### Getting Field Configuration

```php
$fields = $syncSdk->getFieldConfiguration();
foreach ($fields as $name => $config) {
    echo "Field: $name, Type: {$config->type->value}\n";
}
```

## Testing

The SDK includes comprehensive validation and error handling. For testing:

1. Use the validation methods to check data before syncing
2. Start with small batches to verify configuration
3. Monitor API responses for any issues

## License

MIT License

## Support

For support and bug reports, please create an issue in the repository.
