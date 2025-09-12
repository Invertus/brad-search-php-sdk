# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage

# Run a specific test
vendor/bin/phpunit tests/Adapters/PrestaShopAdapterTest.php
```

### Code Quality
```bash
# Run PHPStan static analysis
vendor/bin/phpstan analyse

# Run PHP CodeSniffer
vendor/bin/phpcs src tests

# Install dependencies
composer install

# Update dependencies
composer update
```

## Code Architecture

### Core SDK Structure
The PHP SDK for Brad Search synchronization is organized into a modular architecture:

- **`SynchronizationApiSdk`** - Main SDK class providing the public API for index management and product synchronization
- **Field Configuration System** - Type-safe field definitions using PHP enums and configuration builders
- **Validation Layer** - Comprehensive data validation against field configurations before API calls
- **HTTP Client** - cURL-based client with error handling and authentication
- **Exception Hierarchy** - Typed exceptions for different error scenarios

### Key Components

#### SynchronizationApiSdk (src/SynchronizationApiSdk.php)
Main entry point providing methods:
- `createIndex()` / `deleteIndex()` - Index management
- `sync()` / `syncBulk()` - Product synchronization (single and batch)
- `copyIndex()` - Index replication
- `deleteProductsBulk()` - Bulk product deletion
- `validateProduct()` / `validateProducts()` - Data validation without syncing

#### Field Configuration (src/Models/)
- **`FieldConfig`** - Individual field configuration with type and attributes
- **`FieldConfigBuilder`** - Helper for building common field configurations
- **`FieldType` enum** - Defines supported field types (TEXT_KEYWORD, HIERARCHY, VARIANTS, etc.)

#### Validation System (src/Validators/)
- **`DataValidator`** - Validates product data against field configuration
- Supports all field types including hierarchical categories, variants with attributes, and URL validation
- Provides detailed error reporting

#### HTTP Layer (src/Client/)
- **`HttpClient`** - Handles API communication with authentication, timeouts, and error handling
- Supports all HTTP methods (GET, POST, PUT, DELETE) with JSON encoding

### API Endpoints
The SDK communicates with these endpoints:
- `DELETE /api/v1/sync/{index}` - Delete index
- `PUT /api/v1/sync/` - Create index with field configuration
- `POST /api/v1/sync/` - Bulk sync products
- `POST /api/v1/sync/reindex` - Copy/reindex operations
- `POST /api/v1/sync/delete-products` - Bulk delete products

### Field Types Supported
- `TEXT_KEYWORD` - Full-text search with keyword matching
- `TEXT` - Full-text search only
- `KEYWORD` - Exact keyword matching
- `HIERARCHY` - Hierarchical categories (e.g., "Clothing > T-Shirts > Premium")
- `VARIANTS` - Product variants with configurable attributes
- `NAME_VALUE_LIST` - Key-value pairs (features, specifications)
- `IMAGE_URL` - Image URLs object with size keys
- `URL` - Regular URLs with validation
- `FLOAT`, `INTEGER`, `DOUBLE` - Numeric types

### Data Processing
- **Field Filtering** - Only configured fields are sent to API
- **Batch Processing** - Large datasets automatically chunked (default 100 items per batch)
- **Validation First** - All products validated before any API calls
- **Embeddable Fields** - Support for localized fields with configurable locales

### PrestaShop Integration
The SDK includes a PrestaShop adapter (`src/Adapters/PrestaShopAdapter.php`) for e-commerce platform integration, handling product data mapping and synchronization specific to PrestaShop's data structure.

### Dependencies
- **PHP 8.4+** - Uses modern PHP features (readonly properties, enums, constructor property promotion)
- **ext-json** - JSON encoding/decoding
- **ext-curl** - HTTP client functionality
- **PHPUnit** - Testing framework
- **PHPStan** - Static analysis
- **PHP CodeSniffer** - Code style enforcement