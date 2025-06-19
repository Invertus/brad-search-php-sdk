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

## Extending with New Adapters

To create adapters for other e-commerce platforms:

1. Create a new class in this directory (e.g., `ShopifyAdapter.php`)
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
