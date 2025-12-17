<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

/**
 * GraphQL query builder for Magento product fetching.
 *
 * Provides static methods to generate GraphQL queries with shared item body definitions
 * to ensure consistency across different query types.
 *
 * Available query methods:
 * - getDefaultQuery(): Paginated query with filter support and full item fields
 * - getByIdsQuery(): Query by specific product IDs with full item fields
 * - getMinimalQuery(): Paginated query with minimal item fields (faster for large catalogs)
 * - getIncrementalQuery(): Paginated query for sync checks (id, sku, updated_at only)
 *
 * Item body types:
 * - Full: All product fields including attributes, descriptions, categories
 * - Minimal: Basic fields for performance (id, sku, name, url, stock, price, image, categories)
 * - Incremental: Sync-only fields (id, sku, updated_at)
 *
 * @example Default paginated query
 * ```php
 * $query = MagentoProductQuery::getDefaultQuery();
 * $variables = [
 *     'filter' => ['category_id' => ['eq' => '2']],
 *     'pageSize' => 100,
 *     'currentPage' => 1
 * ];
 * ```
 *
 * @example Query by product IDs
 * ```php
 * $query = MagentoProductQuery::getByIdsQuery();
 * $variables = [
 *     'ids' => ['325465', '1924192', '1924190'],
 *     'pageSize' => 100,
 *     'currentPage' => 1
 * ];
 * ```
 */
final class MagentoProductQuery
{
    /**
     * Full item body with all product fields.
     * Used by getDefaultQuery() and getByIdsQuery().
     */
    private const FULL_ITEMS_BODY = <<<'GRAPHQL'
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
GRAPHQL;

    /**
     * Minimal item body for faster queries on large catalogs.
     * Used by getMinimalQuery().
     */
    private const MINIMAL_ITEMS_BODY = <<<'GRAPHQL'
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
GRAPHQL;

    /**
     * Incremental sync item body (minimal fields for checking updates).
     * Used by getIncrementalQuery().
     */
    private const INCREMENTAL_ITEMS_BODY = <<<'GRAPHQL'
            id
            sku
            updated_at
GRAPHQL;

    /**
     * Page info fragment for paginated queries.
     */
    private const PAGE_INFO = <<<'GRAPHQL'
        page_info {
            current_page
            page_size
            total_pages
        }
GRAPHQL;

    private function __construct()
    {
        // Prevent instantiation
    }

    /**
     * Get the default paginated query with full item fields.
     *
     * Variables:
     * - $filter: ProductAttributeFilterInput (e.g., {"category_id": {"eq": "2"}})
     * - $pageSize: Int (e.g., 100)
     * - $currentPage: Int (e.g., 1)
     */
    public static function getDefaultQuery(): string
    {
        return self::buildPaginatedQuery(self::FULL_ITEMS_BODY);
    }

    /**
     * Get query for fetching products by their IDs with full item fields.
     *
     * Variables:
     * - $ids: [String!] (e.g., ["325465", "1924192", "1924190"])
     * - $pageSize: Int (e.g., 100)
     * - $currentPage: Int (e.g., 1)
     */
    public static function getByIdsQuery(): string
    {
        return self::buildByIdsQuery(self::FULL_ITEMS_BODY);
    }

    /**
     * Get minimal paginated query for faster performance on large catalogs.
     *
     * Variables:
     * - $filter: ProductAttributeFilterInput (e.g., {"category_id": {"eq": "2"}})
     * - $pageSize: Int (e.g., 100)
     * - $currentPage: Int (e.g., 1)
     */
    public static function getMinimalQuery(): string
    {
        return self::buildPaginatedQuery(self::MINIMAL_ITEMS_BODY);
    }

    /**
     * Get incremental sync query for checking updated products.
     *
     * Variables:
     * - $filter: ProductAttributeFilterInput (e.g., {"category_id": {"eq": "2"}})
     * - $pageSize: Int (e.g., 100)
     * - $currentPage: Int (e.g., 1)
     */
    public static function getIncrementalQuery(): string
    {
        return self::buildPaginatedQuery(self::INCREMENTAL_ITEMS_BODY);
    }

    /**
     * Build a paginated query with the given items body.
     */
    private static function buildPaginatedQuery(string $itemsBody): string
    {
        $pageInfo = self::PAGE_INFO;

        return <<<GRAPHQL
query GetProducts(\$filter: ProductAttributeFilterInput, \$pageSize: Int, \$currentPage: Int) {
    products(filter: \$filter, pageSize: \$pageSize, currentPage: \$currentPage) {
        total_count
{$pageInfo}
        items {
{$itemsBody}
        }
    }
}
GRAPHQL;
    }

    /**
     * Build a query by IDs with the given items body.
     */
    private static function buildByIdsQuery(string $itemsBody): string
    {
        $pageInfo = self::PAGE_INFO;

        return <<<GRAPHQL
query GetProductsByIds(\$ids: [String!], \$pageSize: Int, \$currentPage: Int) {
    products(filter: { entity_id: { in: \$ids } }, pageSize: \$pageSize, currentPage: \$currentPage) {
        total_count
{$pageInfo}
        items {
{$itemsBody}
        }
    }
}
GRAPHQL;
    }
}
