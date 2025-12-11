<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

/**
 * Static GraphQL query templates for Magento product fetching
 */
final class MagentoProductQuery
{
    /**
     * Default GraphQL query for fetching products with all common fields
     *
     * This query uses variables for filter, pageSize, and currentPage.
     * Variables should be passed as:
     * - $filter: ProductAttributeFilterInput (e.g., {"category_id": {"eq": "2"}})
     * - $pageSize: Int (e.g., 100)
     * - $currentPage: Int (e.g., 1)
     */
    public const DEFAULT_QUERY = <<<'GRAPHQL'
query GetProducts($filter: ProductAttributeFilterInput, $pageSize: Int, $currentPage: Int) {
    products(filter: $filter, pageSize: $pageSize, currentPage: $currentPage) {
        total_count
        page_info {
            current_page
            page_size
            total_pages
        }
        items {
            id
            sku
            name
            full_url
            is_in_stock
            allows_backorders
            short_description { html }
            description { html }
            attributes {
                attribute_id
                code
                label
                value
                formatted
                position
                is_searchable
                is_filterable
                unit
                numeric_value
                has_unit
            }
            image_optimized
            price_range {
                minimum_price {
                    final_price { value currency }
                    final_price_excl_tax { value currency }
                }
            }
            categories {
                id
                name
                url_path
                level
                path
            }
            stock_status
        }
    }
}
GRAPHQL;

    /**
     * Minimal query for basic product data (faster for large catalogs)
     */
    public const MINIMAL_QUERY = <<<'GRAPHQL'
query GetProducts($filter: ProductAttributeFilterInput, $pageSize: Int, $currentPage: Int) {
    products(filter: $filter, pageSize: $pageSize, currentPage: $currentPage) {
        total_count
        page_info {
            current_page
            page_size
            total_pages
        }
        items {
            id
            sku
            name
            full_url
            stock_status
            price_range {
                minimum_price {
                    final_price { value currency }
                    final_price_excl_tax { value currency }
                }
            }
            image_optimized
            categories {
                id
                name
                level
                path
            }
        }
    }
}
GRAPHQL;

    /**
     * Query for incremental sync (check updated products)
     */
    public const INCREMENTAL_QUERY = <<<'GRAPHQL'
query GetProducts($filter: ProductAttributeFilterInput, $pageSize: Int, $currentPage: Int) {
    products(filter: $filter, pageSize: $pageSize, currentPage: $currentPage) {
        total_count
        page_info {
            current_page
            page_size
            total_pages
        }
        items {
            id
            sku
            updated_at
        }
    }
}
GRAPHQL;

    private function __construct()
    {
        // Prevent instantiation
    }
}
