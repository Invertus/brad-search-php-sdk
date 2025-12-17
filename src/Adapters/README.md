# E-commerce Platform Adapters

This directory contains adapters for transforming e-commerce platform data formats into the BradSearch format.

## PrestaShop Adapter

The `PrestaShopAdapter` transforms PrestaShop product data (typically from API responses) into the standardized BradSearch format.

### Features

- **Multi-locale support**: Handles localized fields with configurable locale priority
- **Category flattening**: Automatically extracts and flattens category hierarchies (lvl2, lvl3, lvl4, etc.)
- **Variant transformation**: Converts complex PrestaShop variant structures to BradSearch format
- **Automatic field mapping**: Maps PrestaShop fields to BradSearch equivalents
- **Data validation**: Built-in validation with detailed error messages

### Usage

```php
use BradSearch\SyncSdk\Adapters\PrestaShopAdapter;
use BradSearch\SyncSdk\SynchronizationApiSdk;

// Initialize adapter with supported locales (first one becomes default)
$adapter = new PrestaShopAdapter(['en-US', 'lt-LT', 'fr-FR']);

// Transform PrestaShop API response
$prestaShopResponse = [
    'products' => [
        // ... PrestaShop product data
    ]
];

$transformedProducts = $adapter->transform($prestaShopResponse);

// Use with BradSearch SDK
$syncSdk = new SynchronizationApiSdk($config, $fieldConfiguration);
$syncSdk->syncBulk('my-index', $transformedProducts);
```

### Locale Handling

The adapter supports multiple locales with the following logic:

1. **Default locale**: The first locale in the constructor array becomes the default
2. **Field naming**: Default locale fields have no suffix (e.g., `name`)
3. **Additional locales**: Other locales get suffixed fields (e.g., `name_lt-LT`)
4. **Fallback**: If default locale is missing, falls back to first available locale

Example with multiple locales:

```php
$adapter = new PrestaShopAdapter(['en-US', 'lt-LT']);

// PrestaShop data with multiple locales
$prestaShopData = [
    'products' => [
        [
            'localizedNames' => [
                'en-US' => 'Sneakers',
                'lt-LT' => 'Sportiniai batai'
            ],
            // ... other fields
        ]
    ]
];

$result = $adapter->transform($prestaShopData);
// Result will have:
// - name: "Sneakers" (default locale)
// - name_lt-LT: "Sportiniai batai" (additional locale)
```

### Field Mapping

| PrestaShop Field       | BradSearch Field                 | Notes                   |
| ---------------------- | -------------------------------- | ----------------------- |
| `remoteId`             | `id`                             | Required                |
| `sku`                  | `sku`                            | Required                |
| `localizedNames`       | `name` (+ locale suffixes)       | Multi-locale support    |
| `brand.localizedNames` | `brand` (+ locale suffixes)      | Multi-locale support    |
| `productUrl`           | `productUrl` (+ locale suffixes) | Multi-locale support    |
| `imageUrl`             | `imageUrl`                       | Maps small/medium sizes |
| `categories.lvl*`      | `categories`                     | Flattened hierarchy     |
| `variants`             | `variants`                       | Complex transformation  |

### Category Transformation

Categories are flattened from PrestaShop's hierarchical structure:

```php
// PrestaShop input
"categories" => [
    "lvl2" => [["localizedValues" => ["path" => ["en-US" => "Men"]]]],
    "lvl3" => [["localizedValues" => ["path" => ["en-US" => "Men > Shoes"]]]],
    "lvl4" => [["localizedValues" => ["path" => ["en-US" => "Men > Shoes > Sneakers"]]]]
]

// BradSearch output
"categories" => ["Men", "Men > Shoes", "Men > Shoes > Sneakers"]
```

### Variant Transformation

PrestaShop variants are transformed to match BradSearch requirements:

```php
// PrestaShop input
"variants" => [
    [
        "remoteId" => "26911",
        "sku" => "PROD-001",
        "attributes" => [
            "Size" => ["localizedValues" => ["en-US" => "34"]],
            "Color" => ["localizedValues" => ["en-US" => "blue"]]
        ],
        "productUrl" => ["localizedValues" => ["en-US" => "http://..."]]
    ]
]

// BradSearch output
"variants" => [
    [
        "id" => "26911",
        "sku" => "PROD-001",
        "url" => "http://...",
        "attributes" => [
            "size" => ["name" => "size", "value" => "34"],
            "color" => ["name" => "color", "value" => "blue"]
        ]
    ]
]
```

### Error Handling

The adapter validates input data and throws `ValidationException` for:

- Missing required fields (`remoteId`, `sku`)
- Invalid data structure
- Missing product array

### Complete Example

See `examples/prestashop-sync.php` for a complete working example.

## Shopify Adapter

The `ShopifyAdapter` transforms Shopify GraphQL product data into the standardized BradSearch format.

### Requirements

- **BCMath PHP extension**: Required for precise decimal price comparisons

### Features

- **GraphQL to BradSearch**: Handles Shopify's GraphQL response structure (edges/nodes)
- **GID extraction**: Converts Shopify GIDs (`gid://shopify/Product/123`) to numeric IDs
- **Variant transformation**: Converts `selectedOptions` to BradSearch attributes format
- **Category extraction**: Combines `productType` and `tags` into categories
- **Price handling**: Extracts prices from `priceRangeV2` structure with BCMath precision
- **Image processing**: Flattens GraphQL image edges to simple URLs

### Usage

```php
use BradSearch\SyncSdk\Adapters\ShopifyAdapter;
use BradSearch\SyncSdk\SynchronizationApiSdk;

// Initialize adapter
$adapter = new ShopifyAdapter();

// Transform Shopify GraphQL response
$shopifyResponse = [
    'data' => [
        'products' => [
            'edges' => [
                // ... Shopify GraphQL product data
            ]
        ]
    ]
];

$transformedData = $adapter->transform($shopifyResponse);

// Use with BradSearch SDK
// Note: $config and $fieldConfiguration need to be set up first (see main README)
$syncSdk = new SynchronizationApiSdk($config, $fieldConfiguration);
$syncSdk->syncBulk('my-index', $transformedData['products']);
```

### Field Mapping

| Shopify Field | BradSearch Field | Notes |
|---------------|------------------|-------|
| `id` (GID) | `id` | Numeric ID extracted from GID |
| `title` | `name` | Product title |
| `descriptionHtml` | `description` | HTML tags stripped |
| `vendor` | `brand` | Shopify vendor = brand |
| `productType` | `categoryDefault` | Primary category |
| `productType` + `tags` | `categories` | Combined categories |
| `priceRangeV2.minVariantPrice` | `price` | Minimum variant price |
| `variants.*.compareAtPrice` | `basePrice` | Maximum compareAtPrice across variants; falls back to maxVariantPrice, then minVariantPrice if unavailable |
| `variants[0].sku` | `sku` | First variant SKU |
| `variants.*.availableForSale` | `inStock` | Any variant available |
| `images.edges[*]` | `imageUrl` | Intelligently selects images by width: smallest for `small`, middle-range for `medium` |
| `variants.selectedOptions` | `variants.attributes` | Attribute names lowercased (e.g., 'Size' → 'size') |

### Shopify-Specific Transformations

#### GID to Numeric ID
```php
// Shopify input
"id": "gid://shopify/Product/6843600694995"

// BradSearch output
"id": "6843600694995"
```

#### Variants with Selected Options
```php
// Shopify input
"variants": {
    "edges": [
        {
            "node": {
                "id": "gid://shopify/ProductVariant/123",
                "sku": "SHOE-BLU-42",
                "selectedOptions": [
                    {"name": "Size", "value": "42"},
                    {"name": "Color", "value": "Blue"}
                ]
            }
        }
    ]
}

// BradSearch output
"variants": [
    {
        "id": "123",
        "sku": "SHOE-BLU-42",
        "attributes": [
            {"name": "size", "value": "42"},
            {"name": "color", "value": "Blue"}
        ]
    }
]
```

#### Categories from ProductType and Tags
```php
// Shopify input
"productType": "Shoes",
"tags": ["Running", "Athletic", "Men"]

// BradSearch output
"categoryDefault": "Shoes",
"categories": ["Shoes", "Running", "Athletic", "Men"]
```

### Error Handling

The adapter validates input data and returns errors array:

```php
$result = $adapter->transform($shopifyData);

// Check for transformation errors
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        echo "Error on product {$error['product_id']}: {$error['message']}\n";
    }
}

// Process successful products
$products = $result['products'];
```

### Complete Example

See `examples/shopify-sync.php` for a complete working example.

## Magento Adapter

The `MagentoAdapter` transforms Magento GraphQL product data into the BradSearch format with minimal transformation - it passes data through as-is while validating required fields.

### Features

- **Minimal transformation**: Validates required fields (id, sku, name) and passes all other data unchanged
- **GraphQL response handling**: Handles Magento's `data.products.items` structure
- **Pagination support**: Extract pagination info separately via `extractPaginationInfo()`
- **Helper utilities**: Optional GraphQL client, query builder, and paginated fetcher

### Basic Usage (Transform Only)

```php
use BradSearch\SyncSdk\Adapters\MagentoAdapter;

$adapter = new MagentoAdapter();

// Transform pre-fetched Magento GraphQL response
$magentoResponse = [
    'data' => [
        'products' => [
            'items' => [
                // ... Magento product data
            ]
        ]
    ]
];

$result = $adapter->transform($magentoResponse);

// Handle errors
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        echo "Error on product {$error['product_id']}: {$error['message']}\n";
    }
}

// Use with BradSearch SDK
$syncSdk->syncBulk('my-index', $result['products']);
```

### Full Usage with Helper Utilities

```php
use BradSearch\SyncSdk\Adapters\MagentoAdapter;
use BradSearch\SyncSdk\Magento\MagentoConfig;
use BradSearch\SyncSdk\Magento\MagentoGraphQLClient;
use BradSearch\SyncSdk\Magento\MagentoPaginatedFetcher;

// 1. Configure Magento connection
$config = new MagentoConfig(
    graphqlUrl: 'https://magento.example.com/graphql',
    bearerToken: 'your-integration-token', // Optional
    defaultPageSize: 100,
);

// 2. Create helper instances
$client = new MagentoGraphQLClient($config);
$adapter = new MagentoAdapter();
$fetcher = new MagentoPaginatedFetcher($client, $adapter);

// 3. Fetch all products with automatic pagination
foreach ($fetcher->fetchAll(['category_id' => ['eq' => '2']]) as $batch) {
    echo "Page {$batch['page']}/{$batch['total_pages']} ({$batch['total_count']} total)\n";
    $syncSdk->syncBulk('my-index', $batch['products']);
}
```

### Using the Query Builder

```php
use BradSearch\SyncSdk\Magento\MagentoQueryBuilder;

$builder = new MagentoQueryBuilder();

// Fluent filter API
$builder
    ->filterByCategory(42)
    ->filterBySku(['SKU-1', 'SKU-2'])
    ->pageSize(50)
    ->page(1);

// Or pass any Magento filter structure directly
$builder->filter([
    'price' => ['from' => '10', 'to' => '100'],
    'name' => ['like' => '%shirt%'],
    'category_id' => ['in' => ['2', '3', '4']],
]);

// Get query and variables for GraphQL request
$query = $builder->getQuery();
$variables = $builder->getVariables();
```

### GraphQL Query Templates

The `MagentoProductQuery` class provides static methods to generate GraphQL queries with shared item body definitions, ensuring consistency across different query types.

```php
use BradSearch\SyncSdk\Magento\MagentoProductQuery;

// Default paginated query with full item fields
$query = MagentoProductQuery::getDefaultQuery();
$variables = [
    'filter' => ['category_id' => ['eq' => '2']],
    'pageSize' => 100,
    'currentPage' => 1
];

// Query by specific product IDs (uses same item fields as default)
$query = MagentoProductQuery::getByIdsQuery();
$variables = [
    'ids' => ['325465', '1924192', '1924190'],
    'pageSize' => 100,
    'currentPage' => 1
];

// Minimal query for faster fetching on large catalogs
$query = MagentoProductQuery::getMinimalQuery();

// Incremental sync query (id, sku, updated_at only)
$query = MagentoProductQuery::getIncrementalQuery();

// Use with query builder
$builder = new MagentoQueryBuilder();
$builder->setQuery($customQuery);
```

#### Available Query Methods

| Method | Variables | Item Fields | Use Case |
|--------|-----------|-------------|----------|
| `getDefaultQuery()` | `$filter`, `$pageSize`, `$currentPage` | Full (all fields) | Standard product sync |
| `getByIdsQuery()` | `$ids: [String!]`, `$pageSize`, `$currentPage` | Full (all fields) | Fetch specific products by ID |
| `getMinimalQuery()` | `$filter`, `$pageSize`, `$currentPage` | Minimal (basic fields) | Fast sync for large catalogs |
| `getIncrementalQuery()` | `$filter`, `$pageSize`, `$currentPage` | Incremental (id, sku, updated_at) | Check for updates |

#### Item Body Types

- **Full**: All product fields including attributes, descriptions, categories, prices, stock status
- **Minimal**: Basic fields only (id, sku, name, url, stock, price, image, categories)
- **Incremental**: Sync-only fields (id, sku, updated_at)

### Field Mapping

The MagentoAdapter performs **minimal transformation**:

| Magento Field | BradSearch Field | Notes |
|---------------|------------------|-------|
| `id` | `id` | Cast to string, required |
| `sku` | `sku` | Required |
| `name` | `name` | Required |
| All other fields | Pass through as-is | No transformation |

All nested structures (attributes, categories, price_range, media_gallery, etc.) are preserved exactly as received from Magento.

### Pagination Info

```php
$pagination = $adapter->extractPaginationInfo($magentoResponse);

// Returns:
// [
//     'total_count' => 78574,
//     'current_page' => 1,
//     'page_size' => 100,
//     'total_pages' => 786,
// ]
```

### Supported Magento Filters

The query builder supports any Magento filter condition:

| Condition | Example | Description |
|-----------|---------|-------------|
| `eq` | `['category_id' => ['eq' => '2']]` | Equal to |
| `neq` | `['status' => ['neq' => 'disabled']]` | Not equal to |
| `in` | `['sku' => ['in' => ['A', 'B']]]` | In list |
| `nin` | `['sku' => ['nin' => ['X', 'Y']]]` | Not in list |
| `like` | `['name' => ['like' => '%shirt%']]` | Pattern match |
| `from`/`to` | `['price' => ['from' => '10', 'to' => '100']]` | Range |
| `gt`/`lt` | `['qty' => ['gt' => '0']]` | Greater/less than |

### Error Handling

```php
$result = $adapter->transform($magentoData);

// Errors are collected, not thrown (processing continues)
foreach ($result['errors'] as $error) {
    // $error contains:
    // - type: 'transformation_error' or 'invalid_structure'
    // - product_index: Position in items array
    // - product_id: Product ID if available
    // - message: Error description
    // - exception: Exception class name
}
```

### Helper Classes

| Class | Purpose |
|-------|---------|
| `MagentoConfig` | Connection configuration (URL, token, timeout, SSL) |
| `MagentoGraphQLClient` | cURL-based GraphQL HTTP client |
| `MagentoQueryBuilder` | Fluent filter and pagination builder |
| `MagentoProductQuery` | GraphQL query builder with shared item bodies |
| `MagentoPaginatedFetcher` | Automatic pagination with generator support |

## Shared Utilities (AdapterUtils)

The `AdapterUtils` class provides common utility methods that can be reused across all adapters:

```php
use BradSearch\SyncSdk\Adapters\AdapterUtils;

// Build imageUrl structure with small/medium keys
$imageUrl = AdapterUtils::buildImageUrl($smallUrl, $mediumUrl);

// Extract URL from nested structure like {url: string, label: string}
$url = AdapterUtils::extractNestedImageUrl($product, 'image');

// Extract URL directly from a field
$url = AdapterUtils::extractDirectUrl($product, 'productUrl');

// Safely get nested value from array
$price = AdapterUtils::getNestedValue($product, ['price_range', 'minimum_price', 'value']);

// Build error entry for transformation errors
$error = AdapterUtils::buildError('transformation_error', $index, $productId, $message);
```

### Available Methods

| Method | Description |
|--------|-------------|
| `buildImageUrl($small, $medium)` | Build imageUrl with small/medium keys |
| `extractNestedImageUrl($data, $field)` | Extract URL from `{url, label}` structure |
| `extractDirectUrl($data, $field)` | Extract URL directly from field |
| `getNestedValue($data, $keys, $default)` | Safely traverse nested arrays |
| `toString($value)` | Cast value to string safely |
| `buildError(...)` | Build standardized error entry |

## Extending with New Adapters

To create adapters for other e-commerce platforms:

1. Create a new class in this directory (e.g., `WooCommerceAdapter.php`)
2. Implement the transformation logic following the same pattern
3. Create comprehensive unit tests
4. Add documentation and examples

### Adapter Interface Pattern

```php
class MyPlatformAdapter
{
    public function __construct(array $config = []) {}

    public function transform(array $platformData): array
    {
        // Transform to BradSearch format
        return $transformedProducts;
    }
}
```

## Testing

Run the adapter tests:

```bash
vendor/bin/phpunit tests/Adapters/
```

## Contributing

When adding new adapters:

1. Follow the same code structure and patterns
2. Include comprehensive unit tests
3. Add documentation and examples
4. Ensure compatibility with the BradSearch SDK
