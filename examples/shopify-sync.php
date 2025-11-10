<?php

/**
 * Example: Shopify Product Synchronization
 *
 * This example demonstrates how to use the ShopifyAdapter to transform
 * Shopify GraphQL product data and sync it with BradSearch.
 */

require_once __DIR__.'/../vendor/autoload.php';

use BradSearch\SyncSdk\Adapters\ShopifyAdapter;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\SynchronizationApiSdk;

// 1. Create configuration
$config = new SyncConfig(
    baseUrl: 'https://your-bradsearch-api.com',
    authToken: 'your-auth-token',
    timeout: 30,
    verifySSL: true
);

// 2. Define field configuration for e-commerce
$fieldConfiguration = FieldConfigBuilder::ecommerceFields(['en-US']);

// 3. Initialize SDK
$syncSdk = new SynchronizationApiSdk($config, $fieldConfiguration);

// 4. Sample Shopify GraphQL response
// This is what you'd get from Shopify's GraphQL API
$shopifyGraphQLResponse = [
    'data' => [
        'products' => [
            'edges' => [
                [
                    'node' => [
                        'id' => 'gid://shopify/Product/6843600694995',
                        'title' => 'Running Shoes Pro',
                        'descriptionHtml' => '<p>High-performance running shoes for athletes</p>',
                        'status' => 'ACTIVE',
                        'vendor' => 'Nike',
                        'productType' => 'Shoes',
                        'tags' => ['Running', 'Athletic', 'Men'],
                        'createdAt' => '2024-01-15T10:30:00Z',
                        'updatedAt' => '2024-01-20T15:45:00Z',
                        'publishedAt' => '2024-01-15T12:00:00Z',
                        'priceRangeV2' => [
                            'minVariantPrice' => [
                                'amount' => '129.99',
                                'currencyCode' => 'USD',
                            ],
                            'maxVariantPrice' => [
                                'amount' => '149.99',
                                'currencyCode' => 'USD',
                            ],
                        ],
                        'variants' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/40123456789',
                                        'title' => 'Size 10 / Blue',
                                        'sku' => 'NIKE-RUN-BLU-10',
                                        'price' => '129.99',
                                        'compareAtPrice' => '159.99',
                                        'inventoryQuantity' => 25,
                                        'availableForSale' => true,
                                        'selectedOptions' => [
                                            [
                                                'name' => 'Size',
                                                'value' => '10',
                                            ],
                                            [
                                                'name' => 'Color',
                                                'value' => 'Blue',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/40123456790',
                                        'title' => 'Size 11 / Blue',
                                        'sku' => 'NIKE-RUN-BLU-11',
                                        'price' => '129.99',
                                        'compareAtPrice' => '159.99',
                                        'inventoryQuantity' => 15,
                                        'availableForSale' => true,
                                        'selectedOptions' => [
                                            [
                                                'name' => 'Size',
                                                'value' => '11',
                                            ],
                                            [
                                                'name' => 'Color',
                                                'value' => 'Blue',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'images' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductImage/29123456789',
                                        'url' => 'https://cdn.shopify.com/s/files/1/0000/0000/products/shoe-blue.jpg',
                                        'altText' => 'Blue Running Shoes',
                                        'width' => 1200,
                                        'height' => 1200,
                                    ],
                                ],
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductImage/29123456790',
                                        'url' => 'https://cdn.shopify.com/s/files/1/0000/0000/products/shoe-side.jpg',
                                        'altText' => 'Running Shoes Side View',
                                        'width' => 1200,
                                        'height' => 1200,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'pageInfo' => [
                'hasNextPage' => false,
                'endCursor' => null,
            ],
        ],
    ],
];

// 5. Initialize Shopify adapter
$shopifyAdapter = new ShopifyAdapter();

// 6. Transform Shopify data to BradSearch format
echo "Transforming Shopify products...\n";
$transformedData = $shopifyAdapter->transform($shopifyGraphQLResponse);

// 7. Check for transformation errors
if (! empty($transformedData['errors'])) {
    echo "Transformation errors found:\n";
    foreach ($transformedData['errors'] as $error) {
        echo "  - Product {$error['product_id']}: {$error['message']}\n";
    }
}

// 8. Display transformed products
echo "\nTransformed ".count($transformedData['products'])." products successfully\n\n";

foreach ($transformedData['products'] as $product) {
    echo "Product: {$product['name']}\n";
    echo "  ID: {$product['id']}\n";
    echo "  SKU: {$product['sku']}\n";
    echo "  Brand: {$product['brand']}\n";
    echo "  Price: {$product['price']}\n";
    echo "  Category: {$product['categoryDefault']}\n";
    echo "  Categories: ".implode(', ', $product['categories'])."\n";
    echo "  In Stock: ".($product['inStock'] ? 'Yes' : 'No')."\n";
    echo "  Variants: ".count($product['variants'])."\n";

    foreach ($product['variants'] as $variant) {
        echo "    - Variant {$variant['id']} ({$variant['sku']})\n";
        foreach ($variant['attributes'] as $attr) {
            echo "      {$attr['name']}: {$attr['value']}\n";
        }
    }

    echo "\n";
}

// 9. Sync to BradSearch (optional - commented out for example)
/*
try {
    // Create index if it doesn't exist
    $indexName = 'shopify-products';
    $syncSdk->createIndex($indexName, ['en-US']);
    
    // Sync products
    $result = $syncSdk->syncBulk($indexName, $transformedData['products']);
    
    echo "Successfully synced {$result->getSuccessCount()} products\n";
    
    if ($result->getFailureCount() > 0) {
        echo "Failed to sync {$result->getFailureCount()} products\n";
    }
} catch (\Exception $e) {
    echo "Sync error: {$e->getMessage()}\n";
}
*/

echo "\nExample completed!\n";
