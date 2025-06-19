<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\SynchronizationApiSdk;
use BradSearch\SyncSdk\Models\FieldConfigBuilder;
use BradSearch\SyncSdk\Adapters\PrestaShopAdapter;
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

// Initialize PrestaShop adapter with supported locales
$prestaShopAdapter = new PrestaShopAdapter(['en-US', 'lt-LT']);

// Index name
$indexName = 'prestashop-products-example';

try {
    echo "Starting PrestaShop synchronization example...\n";

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
    $syncSdk->createIndex($indexName);
    echo "Index created successfully.\n";

    // Step 3: Sample PrestaShop API response data
    $prestaShopApiResponse = [
        "products" => [
            [
                "remoteId" => "1807",
                "brand" => [
                    "remoteId" => "3",
                    "localizedNames" => [
                        "en-US" => "Springa",
                        "lt-LT" => "Springa LT"
                    ]
                ],
                "localizedNames" => [
                    "en-US" => "Sneakers \"101H\" Springa multi",
                    "lt-LT" => "Sportiniai batai \"101H\" Springa daugiaspalviai"
                ],
                "sku" => "M0E20000000EAAK",
                "productUrl" => [
                    "en-US" => "http://prestashop/sneakers/1807-sneakers-101h-springa-multi.html",
                    "lt-LT" => "http://prestashop/lt/sportiniai-batai/1807-sneakers-101h-springa-multi.html"
                ],
                "imageUrl" => [
                    "small" => "http://prestashop/5309-small_default/sneakers-101h-springa-multi.jpg",
                    "medium" => "http://prestashop/5309-medium_default/sneakers-101h-springa-multi.jpg"
                ],
                // todo: currently not synching this as well
                "categoryDefault" => [
                    "remoteId" => "164",
                    "localizedNames" => [
                        "en-US" => "Sneakers"
                    ],
                    "localizedValues" => [
                        "url" => [
                            "en-US" => "http://prestashop/164-sneakers"
                        ],
                        "path" => [
                            "en-US" => "Men > Shoes > Sneakers"
                        ]
                    ]
                ],
                "categories" => [
                    "lvl2" => [
                        [
                            "remoteId" => "162",
                            "localizedNames" => [
                                "en-US" => "Men"
                            ],
                            "localizedValues" => [
                                "path" => [
                                    "en-US" => "Men"
                                ],
                                "url" => [
                                    "en-US" => "http://prestashop/162-men"
                                ]
                            ]
                        ]
                    ],
                    "lvl3" => [
                        [
                            "remoteId" => "163",
                            "localizedNames" => [
                                "en-US" => "Shoes"
                            ],
                            "localizedValues" => [
                                "path" => [
                                    "en-US" => "Men > Shoes"
                                ],
                                "url" => [
                                    "en-US" => "http://prestashop/163-shoes"
                                ]
                            ]
                        ]
                    ],
                    "lvl4" => [
                        [
                            "remoteId" => "164",
                            "localizedNames" => [
                                "en-US" => "Sneakers"
                            ],
                            "localizedValues" => [
                                "path" => [
                                    "en-US" => "Men > Shoes > Sneakers"
                                ],
                                "url" => [
                                    "en-US" => "http://prestashop/164-sneakers"
                                ]
                            ]
                        ]
                    ]
                ],
                "variants" => [
                    [
                        "remoteId" => "26911",
                        "attributes" => [
                            "Size" => [
                                "localizedNames" => [
                                    "en-US" => "Size"
                                ],
                                "localizedValues" => [
                                    "en-US" => "34"
                                ]
                            ],
                            "Color" => [
                                "localizedNames" => [
                                    "en-US" => "Color"
                                ],
                                "localizedValues" => [
                                    "en-US" => "multi"
                                ]
                            ]
                        ],
                        "productUrl" => [
                            "localizedValues" => [
                                "en-US" => "http://prestashop/sneakers/1807-26911-sneakers-101h-springa-multi.html#/size-34/color-multi"
                            ]
                        ],
                        "imageUrl" => [
                            "small" => "http://prestashop/5309-small_default/sneakers-101h-springa-multi.jpg",
                            "medium" => "http://prestashop/5309-medium_default/sneakers-101h-springa-multi.jpg"
                        ],
                        "sku" => "M0E20000000EAAK"
                    ],
                    [
                        "remoteId" => "26912",
                        "attributes" => [
                            "Size" => [
                                "localizedNames" => [
                                    "en-US" => "Size"
                                ],
                                "localizedValues" => [
                                    "en-US" => "34.5"
                                ]
                            ],
                            "Color" => [
                                "localizedNames" => [
                                    "en-US" => "Color"
                                ],
                                "localizedValues" => [
                                    "en-US" => "multi"
                                ]
                            ]
                        ],
                        "productUrl" => [
                            "localizedValues" => [
                                "en-US" => "http://prestashop/sneakers/1807-26912-sneakers-101h-springa-multi.html#/size-345/color-multi"
                            ]
                        ],
                        "imageUrl" => [
                            "small" => "http://prestashop/5309-small_default/sneakers-101h-springa-multi.jpg",
                            "medium" => "http://prestashop/5309-medium_default/sneakers-101h-springa-multi.jpg"
                        ],
                        "sku" => "M0E20000000EAAL"
                    ],
                    [
                        "remoteId" => "26913",
                        "attributes" => [
                            "Size" => [
                                "localizedNames" => [
                                    "en-US" => "Size"
                                ],
                                "localizedValues" => [
                                    "en-US" => "35"
                                ]
                            ],
                            "Color" => [
                                "localizedNames" => [
                                    "en-US" => "Color"
                                ],
                                "localizedValues" => [
                                    "en-US" => "blue"
                                ]
                            ]
                        ],
                        "productUrl" => [
                            "localizedValues" => [
                                "en-US" => "http://prestashop/sneakers/1807-26913-sneakers-101h-springa-multi.html#/size-35/color-blue"
                            ]
                        ],
                        "imageUrl" => [
                            "small" => "http://prestashop/5309-small_default/sneakers-101h-springa-multi.jpg",
                            "medium" => "http://prestashop/5309-medium_default/sneakers-101h-springa-multi.jpg"
                        ],
                        "sku" => "M0E20000000EAAM"
                    ]
                ],
                "tags" => [],
                //todo: this does not work, fix it in Presta
                "descriptionShort" => [
                    "1" => ""
                ]
            ]
        ]
    ];

    // Step 4: Transform PrestaShop data to BradSearch format
    echo "Transforming PrestaShop data to BradSearch format...\n";
    $transformedProducts = $prestaShopAdapter->transform($prestaShopApiResponse);
    echo "Transformed " . count($transformedProducts) . " products.\n";

    // Step 5: Show transformed data structure
    echo "\nTransformed product structure:\n";
    $firstProduct = $transformedProducts[0];
    echo "Product ID: " . $firstProduct['id'] . "\n";
    echo "Product Name: " . $firstProduct['name'] . "\n";
    if (isset($firstProduct['name_lt-LT'])) {
        echo "Product Name (lt-LT): " . $firstProduct['name_lt-LT'] . "\n";
    }
    echo "Brand: " . $firstProduct['brand'] . "\n";
    echo "Categories: " . implode(', ', $firstProduct['categories']) . "\n";
    echo "Variants count: " . count($firstProduct['variants']) . "\n";
    
    if (!empty($firstProduct['variants'])) {
        echo "First variant attributes:\n";
        foreach ($firstProduct['variants'][0]['attributes'] as $attrName => $attrData) {
            echo "  - {$attrName}: {$attrData['value']}\n";
        }
    }

    // Step 6: Validate transformed products
    echo "\nValidating transformed products...\n";
    $syncSdk->validateProducts($transformedProducts);
    echo "All products validated successfully.\n";

    // Step 7: Sync products to BradSearch
    echo "Syncing products to BradSearch...\n";
    $syncSdk->syncBulk($indexName, $transformedProducts, 10);
    echo "Bulk sync completed successfully.\n";

    // Step 8: Show adapter configuration
    echo "\nPrestaShop Adapter Configuration:\n";
    echo "Supported Locales: " . implode(', ', $prestaShopAdapter->getSupportedLocales()) . "\n";
    echo "Default Locale: " . $prestaShopAdapter->getDefaultLocale() . "\n";

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

    echo "\nPrestaShop synchronization completed successfully!\n";
    echo "Synced " . count($transformedProducts) . " products to index '{$indexName}'.\n";

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