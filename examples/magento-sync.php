<?php

/**
 * Example: Magento Product Synchronization
 *
 * This example demonstrates how to use the MagentoAdapter to transform
 * Magento GraphQL product data and sync it with BradSearch.
 *
 * The adapter performs minimal transformation - it validates required fields
 * and passes most data through unchanged, making it easy to work with
 * Magento's flexible attribute system.
 */

require_once __DIR__.'/../vendor/autoload.php';

use BradSearch\SyncSdk\Adapters\MagentoAdapter;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Magento\MagentoConfig;
use BradSearch\SyncSdk\Magento\MagentoGraphQLClient;
use BradSearch\SyncSdk\Magento\MagentoPaginatedFetcher;
use BradSearch\SyncSdk\Magento\MagentoProductQuery;
use BradSearch\SyncSdk\Magento\MagentoQueryBuilder;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\SynchronizationApiSdk;

// ============================================================================
// PART 1: Basic Usage (Transform Only)
// ============================================================================

echo "=== Part 1: Basic Transformation ===\n\n";

// Sample Magento GraphQL response
// This is what you'd get from Magento's GraphQL API
$magentoGraphQLResponse = [
    'data' => [
        'products' => [
            'total_count' => 1,
            'page_info' => [
                'current_page' => 1,
                'page_size' => 100,
                'total_pages' => 1,
            ],
            'items' => [
                [
                    'id' => 1924184,
                    'sku' => '1924184',
                    'name' => 'Drill Crown Bahco Bi-Metal 98x38 mm',
                    'url_key' => 'drill-crown-bahco-bi-metal-98x38-mm',
                    'is_in_stock' => true,
                    'allows_backorders' => true,
                    'short_description' => ['html' => '<p>High-quality bi-metal drill crown</p>'],
                    'description' => ['html' => '<p>Professional grade drill crown suitable for various materials.</p>'],
                    'attributes' => [
                        [
                            'code' => 'manufacturer',
                            'label' => 'Manufacturer',
                            'value' => 'Bahco',
                            'formatted' => 'Bahco',
                            'position' => 2,
                            'is_searchable' => false,
                            'is_filterable' => true,
                            'unit' => null,
                            'numeric_value' => null,
                            'has_unit' => false,
                        ],
                        [
                            'code' => 'mpn',
                            'label' => 'MPN',
                            'value' => '3830-98-C',
                            'formatted' => '3830-98-C',
                            'position' => 1,
                            'is_searchable' => true,
                            'is_filterable' => false,
                            'unit' => null,
                            'numeric_value' => null,
                            'has_unit' => false,
                        ],
                        [
                            'code' => 'diameter',
                            'label' => 'Diameter',
                            'value' => '98 mm',
                            'formatted' => '98 mm',
                            'position' => 1,
                            'is_searchable' => false,
                            'is_filterable' => true,
                            'unit' => 'mm',
                            'numeric_value' => 98,
                            'has_unit' => true,
                        ],
                    ],
                    'image' => [
                        'url' => 'https://example.com/media/catalog/product/drill-crown-main.jpg',
                        'label' => 'Drill Crown Bahco',
                    ],
                    'small_image' => [
                        'url' => 'https://example.com/media/catalog/product/drill-crown-small.jpg',
                        'label' => 'Drill Crown Bahco',
                    ],
                    'thumbnail' => [
                        'url' => 'https://example.com/media/catalog/product/drill-crown-thumb.jpg',
                        'label' => 'Drill Crown Bahco',
                    ],
                    'media_gallery' => [
                        [
                            'url' => 'https://example.com/media/catalog/product/drill-crown-main.jpg',
                            'label' => null,
                            'position' => 0,
                            'disabled' => false,
                        ],
                    ],
                    'price_range' => [
                        'minimum_price' => [
                            'regular_price' => ['value' => 18.56, 'currency' => 'EUR'],
                            'final_price' => ['value' => 18.50, 'currency' => 'EUR'],
                            'discount' => ['amount_off' => 0.06, 'percent_off' => 0.32],
                        ],
                        'maximum_price' => [
                            'regular_price' => ['value' => 18.56, 'currency' => 'EUR'],
                            'final_price' => ['value' => 18.50, 'currency' => 'EUR'],
                        ],
                    ],
                    'categories' => [
                        [
                            'id' => 34,
                            'name' => 'Drill Bits & Accessories',
                            'url_path' => 'drill-bits-accessories',
                            'level' => 2,
                            'path' => '1/2/34',
                        ],
                        [
                            'id' => 663,
                            'name' => 'Hole Saws',
                            'url_path' => 'drill-bits-accessories/hole-saws',
                            'level' => 3,
                            'path' => '1/2/34/663',
                        ],
                    ],
                    'stock_status' => 'IN_STOCK',
                ],
            ],
        ],
    ],
];

// Initialize adapter
$adapter = new MagentoAdapter();

// Transform Magento data to BradSearch format
echo "Transforming Magento products...\n";
$result = $adapter->transform($magentoGraphQLResponse);

// Check for transformation errors
if (!empty($result['errors'])) {
    echo "Transformation errors found:\n";
    foreach ($result['errors'] as $error) {
        echo "  - Product {$error['product_id']}: {$error['message']}\n";
    }
}

// Extract pagination info
$pagination = $adapter->extractPaginationInfo($magentoGraphQLResponse);
if ($pagination) {
    echo "Pagination: Page {$pagination['current_page']} of {$pagination['total_pages']} ";
    echo "({$pagination['total_count']} total products)\n";
}

// Display transformed products
echo "\nTransformed ".count($result['products'])." products successfully\n\n";

foreach ($result['products'] as $product) {
    echo "Product: {$product['name']}\n";
    echo "  ID: {$product['id']}\n";
    echo "  SKU: {$product['sku']}\n";
    echo "  URL Key: {$product['url_key']}\n";
    echo "  Stock Status: {$product['stock_status']}\n";

    // Price from preserved structure
    if (isset($product['price_range']['minimum_price']['final_price'])) {
        $price = $product['price_range']['minimum_price']['final_price'];
        echo "  Price: {$price['value']} {$price['currency']}\n";
    }

    // Categories from preserved structure
    if (isset($product['categories']) && is_array($product['categories'])) {
        $categoryNames = array_map(fn($c) => $c['name'], $product['categories']);
        echo "  Categories: ".implode(' > ', $categoryNames)."\n";
    }

    // Attributes from preserved structure
    if (isset($product['attributes']) && is_array($product['attributes'])) {
        echo "  Attributes:\n";
        foreach ($product['attributes'] as $attr) {
            echo "    - {$attr['label']}: {$attr['value']}\n";
        }
    }

    // imageUrl (transformed to SDK format)
    if (isset($product['imageUrl'])) {
        echo "  Images (SDK format):\n";
        echo "    - small: {$product['imageUrl']['small']}\n";
        echo "    - medium: {$product['imageUrl']['medium']}\n";
    }

    echo "\n";
}

// ============================================================================
// PART 2: Full Usage with Helper Utilities
// ============================================================================

echo "=== Part 2: Using Helper Utilities ===\n\n";

// Show the default GraphQL query
echo "Default GraphQL Query Template:\n";
echo "--------------------------------\n";
echo substr(MagentoProductQuery::DEFAULT_QUERY, 0, 500)."...\n\n";

// Demonstrate the query builder
echo "Building a query with filters:\n";
$builder = new MagentoQueryBuilder();
$builder
    ->filterByCategory(2)
    ->pageSize(100)
    ->page(1);

echo "Query: (using default template)\n";
echo "Variables: ".json_encode($builder->getVariables(), JSON_PRETTY_PRINT)."\n\n";

// Demonstrate flexible filters
echo "Building a query with complex filters:\n";
$builder2 = new MagentoQueryBuilder();
$builder2->filter([
    'category_id' => ['eq' => '2'],
    'price' => ['from' => '10', 'to' => '100'],
    'name' => ['like' => '%drill%'],
]);

echo "Variables: ".json_encode($builder2->getVariables(), JSON_PRETTY_PRINT)."\n\n";

// ============================================================================
// PART 3: Example with Real API (commented out)
// ============================================================================

/*
// This is how you'd use it with a real Magento instance

// 1. Configure Magento connection
$magentoConfig = new MagentoConfig(
    graphqlUrl: 'https://your-magento-store.com/graphql',
    bearerToken: 'your-integration-token',  // Optional for public data
    timeout: 30,
    verifySSL: true,
    defaultPageSize: 100,
);

// 2. Create helper instances
$client = new MagentoGraphQLClient($magentoConfig);
$adapter = new MagentoAdapter();
$fetcher = new MagentoPaginatedFetcher($client, $adapter);

// 3. Configure BradSearch SDK
$bradConfig = new SyncConfig(
    baseUrl: 'https://your-bradsearch-api.com',
    authToken: 'your-auth-token',
);
$fieldConfiguration = FieldConfigBuilder::ecommerceFields(['en-US']);
$syncSdk = new SynchronizationApiSdk($bradConfig, $fieldConfiguration);

// 4. Fetch and sync all products with automatic pagination
$indexName = 'magento-products';
$syncSdk->createIndex($indexName);

foreach ($fetcher->fetchAll(['category_id' => ['eq' => '2']]) as $batch) {
    echo "Syncing page {$batch['page']}/{$batch['total_pages']} ";
    echo "({$batch['total_count']} total)\n";

    if (!empty($batch['products'])) {
        $syncSdk->syncBulk($indexName, $batch['products']);
    }

    // Log any transformation errors
    if (!empty($batch['errors'])) {
        foreach ($batch['errors'] as $error) {
            echo "  Warning: {$error['message']}\n";
        }
    }
}

// 5. Alternative: Fetch all at once (use with caution for large catalogs)
$allData = $fetcher->fetchAllAsArray(['category_id' => ['eq' => '2']]);
echo "Fetched {$allData['total_count']} products in {$allData['pages_fetched']} pages\n";
$syncSdk->syncBulk($indexName, $allData['products']);
*/

echo "=== Example completed! ===\n";
