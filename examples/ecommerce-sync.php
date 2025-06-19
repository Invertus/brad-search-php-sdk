<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\SynchronizationApiSdk;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\ValidationException;

// Configuration
$config = new SyncConfig(
    baseUrl: $_ENV['BRADSEARCH_API_URL'] ?? 'http://localhost:8080',
    authToken: $_ENV['BRADSEARCH_AUTH_TOKEN'] ?? 'your_actual_secret_here223123',
    timeout: 30,
    verifySSL: false // Set to true in production
);

// Field configuration for ecommerce products
$fieldConfiguration = FieldConfigBuilder::ecommerceFields();

// Initialize SDK
$syncSdk = new SynchronizationApiSdk($config, $fieldConfiguration);

// Index name
$indexName = 'ecommerce-products-example';

try {
    echo "Starting ecommerce synchronization example...\n";

    // Step 1: Delete existing index (if any)
    echo "Deleting existing index...\n";
    try {
        $syncSdk->deleteIndex($indexName);
        echo "Index deleted successfully.\n";
    } catch (ApiException $e) {
        if ($e->statusCode === 404) {
            echo "Index doesn't exist (this is fine).\n";
        } else {
            throw $e;
        }
    }

    // Step 2: Create new index
    echo "Creating index '{$indexName}'...\n";
    $syncSdk->createIndex($indexName, ['en']);
    echo "Index created successfully.\n";

    // Step 3: Prepare sample products
    $products = [
        [
            'id' => 'tshirt-001',
            'name' => 'Premium Cotton T-Shirt',
            'brand' => 'EcoWear',
            'sku' => 'TSHIRT-PREM-001',
            'categoryDefault' => 'Clothing > T-Shirts > Premium',
            'categories' => [
                'Clothing',
                'Clothing > T-Shirts',
                'Clothing > T-Shirts > Premium'
            ],
            'variants' => [
                [
                    'id' => 'tshirt-001-s-blue',
                    'sku' => 'TSHIRT-PREM-001-S-BLUE',
                    'url' => 'https://shop.example.com/products/premium-cotton-tshirt/variant/tshirt-001-s-blue',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => 'S'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Blue'
                        ]
                    ]
                ],
                [
                    'id' => 'tshirt-001-m-blue',
                    'sku' => 'TSHIRT-PREM-001-M-BLUE',
                    'url' => 'https://shop.example.com/products/premium-cotton-tshirt/variant/tshirt-001-m-blue',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => 'M'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Blue'
                        ]
                    ]
                ],
                [
                    'id' => 'tshirt-001-l-red',
                    'sku' => 'TSHIRT-PREM-001-L-RED',
                    'url' => 'https://shop.example.com/products/premium-cotton-tshirt/variant/tshirt-001-l-red',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => 'L'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Red'
                        ]
                    ]
                ]
            ],
            'imageUrl' => [
                'small' => 'https://example.com/images/tshirt-001-small.jpg',
                'medium' => 'https://example.com/images/tshirt-001-medium.jpg'
            ],
            'productUrl' => 'https://shop.example.com/products/premium-cotton-tshirt',
            'descriptionShort' => 'Comfortable premium cotton t-shirt for everyday wear'
        ],
        [
            'id' => 'jeans-002',
            'name' => 'Slim Fit Denim Jeans',
            'brand' => 'DenimCo',
            'sku' => 'JEANS-SLIM-002',
            'categoryDefault' => 'Clothing > Jeans > Slim Fit',
            'categories' => [
                'Clothing',
                'Clothing > Jeans',
                'Clothing > Jeans > Slim Fit'
            ],
            'variants' => [
                [
                    'id' => 'jeans-002-30-dark',
                    'sku' => 'JEANS-SLIM-002-30-DARK',
                    'url' => 'https://shop.example.com/products/slim-fit-denim-jeans/variant/jeans-002-30-dark',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => '30'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Dark Blue'
                        ]
                    ]
                ],
                [
                    'id' => 'jeans-002-32-dark',
                    'sku' => 'JEANS-SLIM-002-32-DARK',
                    'url' => 'https://shop.example.com/products/slim-fit-denim-jeans/variant/jeans-002-32-dark',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => '32'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Dark Blue'
                        ]
                    ]
                ]
            ],
            'imageUrl' => [
                'small' => 'https://example.com/images/jeans-002-small.jpg',
                'medium' => 'https://example.com/images/jeans-002-medium.jpg'
            ],
            'productUrl' => 'https://shop.example.com/products/slim-fit-denim-jeans',
            'descriptionShort' => 'Classic slim fit denim jeans with modern styling'
        ],
        [
            'id' => 'sneakers-003',
            'name' => 'Athletic Running Sneakers',
            'brand' => 'SportMax',
            'sku' => 'SNEAKERS-RUN-003',
            'categoryDefault' => 'Footwear > Sneakers > Running',
            'categories' => [
                'Footwear',
                'Footwear > Sneakers',
                'Footwear > Sneakers > Running'
            ],
            'variants' => [
                [
                    'id' => 'sneakers-003-9-white',
                    'sku' => 'SNEAKERS-RUN-003-9-WHITE',
                    'url' => 'https://shop.example.com/products/athletic-running-sneakers/variant/sneakers-003-9-white',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => '9'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'White'
                        ]
                    ]
                ],
                [
                    'id' => 'sneakers-003-10-white',
                    'sku' => 'SNEAKERS-RUN-003-10-WHITE',
                    'url' => 'https://shop.example.com/products/athletic-running-sneakers/variant/sneakers-003-10-white',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => '10'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'White'
                        ]
                    ]
                ],
                [
                    'id' => 'sneakers-003-10-black',
                    'sku' => 'SNEAKERS-RUN-003-10-BLACK',
                    'url' => 'https://shop.example.com/products/athletic-running-sneakers/variant/sneakers-003-10-black',
                    'attributes' => [
                        'size' => [
                            'name' => 'size',
                            'value' => '10'
                        ],
                        'color' => [
                            'name' => 'color',
                            'value' => 'Black'
                        ]
                    ]
                ]
            ],
            'imageUrl' => [
                'small' => 'https://example.com/images/sneakers-003-small.jpg',
                'medium' => 'https://example.com/images/sneakers-003-medium.jpg'
            ],
            'productUrl' => 'https://shop.example.com/products/athletic-running-sneakers',
            'descriptionShort' => 'High-performance running sneakers for serious athletes'
        ]
    ];

    // Step 4: Validate products before syncing
    echo "Validating products...\n";
    $syncSdk->validateProducts($products);
    echo "All products validated successfully.\n";

    // Step 5: Sync products (single product example)
    echo "Syncing first product individually...\n";
    $syncSdk->sync($indexName, $products[0]);
    echo "First product synced successfully.\n";

    // Step 6: Sync remaining products in bulk
    echo "Syncing remaining products in bulk...\n";
    $remainingProducts = array_slice($products, 1);
    $syncSdk->syncBulk($indexName, $remainingProducts, 2); // Small batch size for demo
    echo "Bulk sync completed successfully.\n";

    // Step 7: Show configuration
    echo "\nField Configuration Used:\n";
    $fields = $syncSdk->getFieldConfiguration();
    foreach ($fields as $fieldName => $fieldConfig) {
        echo "- {$fieldName}: {$fieldConfig->type->value}\n";
        if ($fieldConfig->attributes !== null) {
            foreach ($fieldConfig->attributes as $attrName => $attrConfig) {
                echo "  └─ {$attrName}: {$attrConfig->type->value}\n";
            }
        }
    }

    echo "\nSynchronization completed successfully!\n";
    echo "Synced " . count($products) . " products to index '{$indexName}'.\n";

} catch (ValidationException $e) {
    echo "Validation Error: {$e->getMessage()}\n";
    echo "Errors:\n";
    foreach ($e->errors as $error) {
        echo "- {$error}\n";
    }
} catch (ApiException $e) {
    echo "API Error: {$e->getMessage()}\n";
    echo "Status Code: {$e->statusCode}\n";
    if ($e->responseBody) {
        echo "Response: {$e->responseBody}\n";
    }
} catch (Exception $e) {
    echo "Unexpected Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} 