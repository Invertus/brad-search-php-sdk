<?php

require_once __DIR__ . '/../vendor/autoload.php';

use BradSearch\SyncSdk\SynchronizationApiSdk;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Models\FieldConfig;
use BradSearch\SyncSdk\Models\BulkOperation;
use BradSearch\SyncSdk\Enums\FieldType;

// Example: Bulk Operations API Usage

// Configure the SDK
$config = new SyncConfig('https://your-api-domain.com', 'your-jwt-token');

$fieldConfiguration = [
    'id' => new FieldConfig(FieldType::KEYWORD, []),
    'name' => new FieldConfig(FieldType::TEXT_KEYWORD, []),
    'price' => new FieldConfig(FieldType::FLOAT, []),
    'brand' => new FieldConfig(FieldType::TEXT_KEYWORD, []),
    'category' => new FieldConfig(FieldType::HIERARCHY, [])
];

$sdk = new SynchronizationApiSdk($config, $fieldConfiguration);

try {
    // Create multiple bulk operations
    $operations = [
        // 1. Index new products
        BulkOperation::indexProducts('products-v1', [
            [
                'id' => 'prod-001',
                'name' => 'Wireless Headphones',
                'price' => 99.99,
                'brand' => 'TechBrand',
                'category' => 'Electronics > Audio > Headphones'
            ],
            [
                'id' => 'prod-002',
                'name' => 'Bluetooth Speaker',
                'price' => 149.99,
                'brand' => 'AudioTech',
                'category' => 'Electronics > Audio > Speakers'
            ]
        ],
        // Optional: Add subfields configuration
        [
            'name' => [
                'split_by' => [' ', '-'],
                'max_count' => 3
            ]
        ],
        // Optional: Add embeddable fields configuration
        [
            'description' => 'name'
        ]),

        // 2. Update existing products
        BulkOperation::updateProducts('products-v1', [
            [
                'id' => 'prod-123',
                'fields' => [
                    'name' => 'Premium Wireless Headphones',
                    'price' => 129.99
                ]
            ],
            [
                'id' => 'prod-124',
                'fields' => [
                    'price' => 139.99
                ]
            ]
        ]),

        // 3. Delete specific products
        BulkOperation::deleteProducts('products-v1', [
            'prod-125',
            'prod-126',
            'prod-127'
        ]),
    ];

    // Execute all operations in a single API call
    $result = $sdk->bulkOperations($operations);

    // Check results
    echo "Bulk Operations Result:\n";
    echo "Status: " . $result->status . "\n";
    echo "Total Operations: " . $result->totalOperations . "\n";
    echo "Successful: " . $result->successfulOperations . "\n";
    echo "Failed: " . $result->failedOperations . "\n";
    echo "Processing Time: " . $result->processingTimeMs . "ms\n\n";

    if ($result->isSuccess()) {
        echo "✅ All operations completed successfully!\n";
    } elseif ($result->isPartialSuccess()) {
        echo "⚠️  Some operations failed:\n";
        foreach ($result->getFailedResults() as $failed) {
            echo "- {$failed['type']}: {$failed['message']}\n";
        }
    } else {
        echo "❌ All operations failed\n";
    }

    // Detailed results
    echo "\nDetailed Results:\n";
    foreach ($result->results as $operationResult) {
        $status = $operationResult['status'] === 'success' ? '✅' : '❌';
        echo "{$status} {$operationResult['type']}: {$operationResult['message']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}