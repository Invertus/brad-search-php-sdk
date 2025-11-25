<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

/**
 * Fluent query builder for Magento GraphQL product queries
 *
 * Supports flexible filter pass-through - any Magento filter structure can be used.
 */
class MagentoQueryBuilder
{
    private array $filters = [];
    private int $pageSize = 100;
    private int $currentPage = 1;
    private string $query;

    public function __construct(?string $query = null, ?int $defaultPageSize = null)
    {
        $this->query = $query ?? MagentoProductQuery::DEFAULT_QUERY;

        if ($defaultPageSize !== null) {
            $this->pageSize = $defaultPageSize;
        }
    }

    /**
     * Set the GraphQL query to use
     */
    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Add filters - accepts any Magento filter structure
     *
     * Example filters:
     * - ['category_id' => ['eq' => '2']]
     * - ['sku' => ['in' => ['SKU-1', 'SKU-2']]]
     * - ['price' => ['from' => '10', 'to' => '100']]
     * - ['name' => ['like' => '%shirt%']]
     *
     * @param array $filters Magento filter structure
     */
    public function filter(array $filters): self
    {
        $this->filters = array_merge($this->filters, $filters);
        return $this;
    }

    /**
     * Reset all filters
     */
    public function resetFilters(): self
    {
        $this->filters = [];
        return $this;
    }

    /**
     * Convenience method: Filter by category ID
     */
    public function filterByCategory(int|string $categoryId): self
    {
        $this->filters['category_id'] = ['eq' => (string) $categoryId];
        return $this;
    }

    /**
     * Convenience method: Filter by SKU(s)
     *
     * @param string|array $sku Single SKU or array of SKUs
     */
    public function filterBySku(string|array $sku): self
    {
        if (is_array($sku)) {
            $this->filters['sku'] = ['in' => $sku];
        } else {
            $this->filters['sku'] = ['eq' => $sku];
        }
        return $this;
    }

    /**
     * Convenience method: Filter by URL key
     */
    public function filterByUrlKey(string $urlKey): self
    {
        $this->filters['url_key'] = ['eq' => $urlKey];
        return $this;
    }

    /**
     * Set page size (products per page)
     */
    public function pageSize(int $size): self
    {
        $this->pageSize = $size;
        return $this;
    }

    /**
     * Set current page number
     */
    public function page(int $page): self
    {
        $this->currentPage = $page;
        return $this;
    }

    /**
     * Get a copy of this builder for a specific page
     */
    public function forPage(int $page): self
    {
        $clone = clone $this;
        $clone->currentPage = $page;
        return $clone;
    }

    /**
     * Get the GraphQL query string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Get the query variables for the GraphQL request
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        $variables = [
            'pageSize' => $this->pageSize,
            'currentPage' => $this->currentPage,
        ];

        if (!empty($this->filters)) {
            $variables['filter'] = $this->filters;
        }

        return $variables;
    }

    /**
     * Get current filters
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get current page size
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Get current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
}
