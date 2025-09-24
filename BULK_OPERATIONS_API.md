# Bulk Operations API Documentation

## Overview

The Bulk Operations API allows you to perform multiple CRUD operations (create, read, update, delete) on products and indexes in a single HTTP request. This significantly reduces network roundtrips and improves performance when dealing with multiple operations.

## Base URL

- **Endpoint**: `POST /api/v1/sync/bulk-operations`

**Note**: This single endpoint handles operations for all clients and indexes. Simply specify the target `index_name` in each operation's payload.

## Authentication

All endpoints require JWT authentication via the `token` query parameter:
```
POST /api/v1/sync/bulk-operations?token={your-jwt-token}
```

## Supported Operations

| Operation Type | Description | Use Case |
|----------------|-------------|----------|
| `index_products` | Index new products | Bulk product creation |
| `update_products` | Update existing products | Bulk product updates |
| `delete_products` | Delete specific products by ID | Selective product removal |
| `delete_index` | Delete entire indexes | Index cleanup |

## Request Format

### Basic Structure

```json
{
  "operations": [
    {
      "type": "operation_type",
      "payload": {
        // Operation-specific payload
      }
    }
  ]
}
```

### Operation Payloads

#### 1. Index Products (`index_products`)

```json
{
  "type": "index_products",
  "payload": {
    "index_name": "products-v1",
    "products": [
      {
        "id": "prod-123",
        "name": "Wireless Headphones",
        "price": 99.99,
        "brand": "TechBrand",
        "category": "Electronics"
      },
      {
        "id": "prod-124",
        "name": "Bluetooth Speaker",
        "price": 149.99,
        "brand": "AudioTech",
        "category": "Electronics"
      }
    ],
    "subfields": {
      "name": {
        "split_by": [" ", "-"],
        "max_count": 3
      }
    },
    "embeddablefields": {
      "description": "name"
    }
  }
}
```

#### 2. Update Products (`update_products`)

```json
{
  "type": "update_products",
  "payload": {
    "index_name": "products-v1",
    "updates": [
      {
        "id": "prod-123",
        "fields": {
          "name": "Premium Wireless Headphones",
          "price": 129.99
        }
      },
      {
        "id": "prod-124",
        "fields": {
          "price": 139.99,
          "stock": true
        }
      }
    ]
  }
}
```

#### 3. Delete Products (`delete_products`)

```json
{
  "type": "delete_products",
  "payload": {
    "index_name": "products-v1",
    "product_ids": ["prod-125", "prod-126", "prod-127"]
  }
}
```

#### 4. Delete Index (`delete_index`)

```json
{
  "type": "delete_index",
  "payload": {
    "index_name": "old-products-index"
  }
}
```

## Complete Sample Requests & Responses

### Example 1: Single Operation - Index Products

**Request:**
```bash
curl -X POST "https://api.example.com/api/v1/sync/bulk-operations?token=your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {
        "type": "index_products",
        "payload": {
          "index_name": "products-v1",
          "products": [
            {
              "id": "headphones-001",
              "name": "Sony WH-1000XM4",
              "price": 299.99,
              "brand": "Sony",
              "category": "Electronics/Audio",
              "variants": [
                {
                  "id": "var-001",
                  "attributes": {
                    "color": "Black",
                    "size": "Standard"
                  }
                }
              ]
            },
            {
              "id": "speaker-001",
              "name": "JBL Charge 5",
              "price": 179.99,
              "brand": "JBL",
              "category": "Electronics/Audio"
            }
          ]
        }
      }
    ]
  }'
```

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "All 1 operations completed successfully",
  "total_operations": 1,
  "successful_operations": 1,
  "failed_operations": 0,
  "processing_time_ms": 1245,
  "results": [
    {
      "type": "index_products",
      "status": "success",
      "message": "Operation index_products completed successfully",
      "count": 2,
      "index_name": "products-v1"
    }
  ]
}
```

### Example 2: Mixed Operations

**Request:**
```bash
curl -X POST "https://api.example.com/api/v1/sync/bulk-operations?token=your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {
        "type": "index_products",
        "payload": {
          "index_name": "products-v1",
          "products": [
            {
              "id": "new-product-001",
              "name": "Latest Smartphone",
              "price": 899.99,
              "brand": "TechCorp"
            }
          ]
        }
      },
      {
        "type": "update_products",
        "payload": {
          "index_name": "products-v1",
          "updates": [
            {
              "id": "existing-product-001",
              "fields": {
                "price": 199.99,
                "sale": true
              }
            }
          ]
        }
      },
      {
        "type": "delete_products",
        "payload": {
          "index_name": "products-v1",
          "product_ids": ["discontinued-001", "discontinued-002"]
        }
      }
    ]
  }'
```

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "All 3 operations completed successfully",
  "total_operations": 3,
  "successful_operations": 3,
  "failed_operations": 0,
  "processing_time_ms": 2156,
  "results": [
    {
      "type": "index_products",
      "status": "success",
      "message": "Operation index_products completed successfully",
      "count": 1,
      "index_name": "products-v1"
    },
    {
      "type": "update_products",
      "status": "success",
      "message": "Operation update_products completed successfully",
      "count": 1,
      "index_name": "products-v1"
    },
    {
      "type": "delete_products",
      "status": "success",
      "message": "Operation delete_products completed successfully",
      "count": 2,
      "index_name": "products-v1"
    }
  ]
}
```

### Example 3: Index Deletion

**Request:**
```bash
curl -X POST "https://api.example.com/api/v1/sync/bulk-operations?token=your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {
        "type": "delete_index",
        "payload": {
          "index_name": "old-products-index"
        }
      }
    ]
  }'
```

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "All 1 operations completed successfully",
  "total_operations": 1,
  "successful_operations": 1,
  "failed_operations": 0,
  "processing_time_ms": 456,
  "results": [
    {
      "type": "delete_index",
      "status": "success",
      "message": "Index 'old-products-index' deleted successfully",
      "count": 1,
      "index_name": "old-products-index"
    }
  ]
}
```

## Error Handling Examples

### Example 4: Partial Failure

**Request:**
```json
{
  "operations": [
    {
      "type": "index_products",
      "payload": {
        "index_name": "products-v1",
        "products": [
          {
            "id": "valid-product",
            "name": "Valid Product",
            "price": 99.99
          }
        ]
      }
    },
    {
      "type": "delete_index",
      "payload": {
        "index_name": "non-existent-index"
      }
    }
  ]
}
```

**Response (207 Multi-Status):**
```json
{
  "status": "partial",
  "message": "1 operations succeeded, 1 operations failed",
  "total_operations": 2,
  "successful_operations": 1,
  "failed_operations": 1,
  "processing_time_ms": 856,
  "results": [
    {
      "type": "index_products",
      "status": "success",
      "message": "Operation index_products completed successfully",
      "count": 1,
      "index_name": "products-v1"
    },
    {
      "type": "delete_index",
      "status": "error",
      "message": "Index 'non-existent-index' does not exist",
      "error": "index not found",
      "index_name": "non-existent-index"
    }
  ]
}
```

### Example 5: Complete Failure

**Request:**
```json
{
  "operations": [
    {
      "type": "invalid_operation",
      "payload": {}
    }
  ]
}
```

**Response (400 Bad Request):**
```json
{
  "status": "error",
  "message": "All 1 operations failed",
  "total_operations": 1,
  "successful_operations": 0,
  "failed_operations": 1,
  "results": [
    {
      "type": "invalid_operation",
      "status": "error",
      "message": "Unsupported operation type",
      "error": "operation at index 0 has unsupported type 'invalid_operation'"
    }
  ]
}
```

### Example 6: Validation Error

**Request:**
```json
{
  "operations": []
}
```

**Response (400 Bad Request):**
```json
{
  "status": "error",
  "message": "No operations provided",
  "total_operations": 0,
  "successful_operations": 0,
  "failed_operations": 0,
  "results": []
}
```

## HTTP Status Codes

| Status Code | Description | When Used |
|-------------|-------------|-----------|
| `200 OK` | All operations successful | All operations completed without errors |
| `207 Multi-Status` | Partial success | Some operations succeeded, some failed |
| `400 Bad Request` | Request validation failed or all operations failed | Invalid request format or complete failure |
| `401 Unauthorized` | Authentication failed | Invalid or missing JWT token |
| `500 Internal Server Error` | Server error | Unexpected server-side errors |

## Performance Considerations

### Batch Size Recommendations

- **Products per operation**: 100-1000 products (optimal: 500)
- **Total request size**: Max 15MB
- **Operations per request**: 1-50 operations (optimal: 5-10)

### Processing Order

1. **Phase 1**: Index deletion operations (processed individually for safety)
2. **Phase 2**: Document operations (index, update, delete products) processed in bulk

### Best Practices

1. **Group similar operations**: Batch multiple products in single operations rather than multiple single-product operations
2. **Monitor response times**: Use `processing_time_ms` to optimize batch sizes
3. **Handle partial failures**: Always check individual operation results
4. **Use appropriate timeouts**: Allow sufficient time for large batches (recommend 60+ seconds)

## Integration Examples

### JavaScript/Node.js

```javascript
const axios = require('axios');

async function bulkOperations(token, operations) {
  try {
    const response = await axios.post(
      `https://api.example.com/api/v1/sync/bulk-operations?token=${token}`,
      { operations },
      {
        headers: { 'Content-Type': 'application/json' },
        timeout: 60000 // 60 seconds
      }
    );

    console.log('Bulk operations completed:', response.data);

    // Check for failures
    const failures = response.data.results.filter(r => r.status === 'error');
    if (failures.length > 0) {
      console.warn('Some operations failed:', failures);
    }

    return response.data;
  } catch (error) {
    console.error('Bulk operations error:', error.response?.data || error.message);
    throw error;
  }
}

// Usage
const operations = [
  {
    type: 'index_products',
    payload: {
      index_name: 'products-v1',
      products: [
        { id: '1', name: 'Product 1', price: 99.99 }
      ]
    }
  }
];

bulkOperations('your-jwt-token', operations);
```

### Python

```python
import requests
import json

def bulk_operations(token, operations, timeout=60):
    url = f"https://api.example.com/api/v1/sync/bulk-operations"

    response = requests.post(
        url,
        params={'token': token},
        json={'operations': operations},
        timeout=timeout
    )

    if response.status_code in [200, 207]:
        data = response.json()

        # Check for failures
        failures = [r for r in data['results'] if r['status'] == 'error']
        if failures:
            print(f"Warning: {len(failures)} operations failed")
            for failure in failures:
                print(f"- {failure['type']}: {failure['message']}")

        return data
    else:
        response.raise_for_status()

# Usage
operations = [
    {
        "type": "index_products",
        "payload": {
            "index_name": "products-v1",
            "products": [
                {"id": "1", "name": "Product 1", "price": 99.99}
            ]
        }
    }
]

result = bulk_operations("your-jwt-token", operations)
print(f"Processed {result['total_operations']} operations")
```

## Testing

### Running Integration Tests

```bash
# Run all bulk operations tests
go test ./tests/integration/bulk_operations_integration_test.go -v

# Run specific test
go test ./tests/integration/bulk_operations_integration_test.go -run TestBulkOperations_MixedOperations -v

# Run with real OpenSearch (requires running OpenSearch)
docker exec -it search go test ./tests/integration/bulk_operations_integration_test.go -v
```

### Test Data Setup

Before running tests, ensure you have:
1. Running OpenSearch instance
2. Valid `.env` configuration
3. Proper JWT authentication setup

## Troubleshooting

### Common Issues

1. **Request Too Large (413)**
   - Reduce batch size or split into multiple requests
   - Check individual product size

2. **Timeout Errors**
   - Increase client timeout
   - Reduce batch size
   - Check OpenSearch cluster performance

3. **Authentication Failures (401)**
   - Verify JWT token is valid and not expired
   - Check token permissions include required indexes

4. **Partial Failures**
   - Check individual operation results in response
   - Verify index names and product IDs exist
   - Review OpenSearch cluster health

### Debug Mode

Enable debug mode by setting `DEBUG=true` environment variable to get detailed OpenSearch query logs.

## Changelog

### v1.0.0 (Initial Release)
- Added bulk operations endpoint
- Support for mixed CRUD operations
- Multi-client architecture integration
- Comprehensive error handling
- Integration test suite