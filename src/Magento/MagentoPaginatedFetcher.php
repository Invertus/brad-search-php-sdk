<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Magento;

use BradSearch\SyncSdk\Adapters\MagentoAdapter;
use BradSearch\SyncSdk\Exceptions\ApiException;
use Generator;

/**
 * Paginated fetcher for Magento products
 *
 * Automatically handles pagination when fetching products from Magento GraphQL API.
 */
class MagentoPaginatedFetcher
{
    private array $allErrors = [];

    public function __construct(
        private readonly MagentoGraphQLClient $client,
        private readonly MagentoAdapter $adapter,
        private readonly ?string $query = null,
    ) {
    }

    /**
     * Fetch all products with automatic pagination
     *
     * Yields batches of transformed products page by page.
     * Use this for memory-efficient processing of large catalogs.
     *
     * @param array $filters Magento filter structure (e.g., ['category_id' => ['eq' => '2']])
     * @param int $pageSize Products per page (max 300)
     * @return Generator<array{products: array, errors: array, page: int, total_pages: int, total_count: int}>
     * @throws ApiException
     */
    public function fetchAll(array $filters = [], int $pageSize = 100): Generator
    {
        $this->allErrors = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 0;

        do {
            $result = $this->fetchPage($filters, $currentPage, $pageSize);

            $totalPages = $result['total_pages'];
            $totalCount = $result['total_count'];

            // Collect errors
            $this->allErrors = array_merge($this->allErrors, $result['errors']);

            yield [
                'products' => $result['products'],
                'errors' => $result['errors'],
                'page' => $currentPage,
                'total_pages' => $totalPages,
                'total_count' => $totalCount,
            ];

            $currentPage++;
        } while ($currentPage <= $totalPages);
    }

    /**
     * Fetch a single page of products
     *
     * @param array $filters Magento filter structure
     * @param int $page Page number (1-based)
     * @param int $pageSize Products per page
     * @return array{products: array, errors: array, page: int, total_pages: int, total_count: int}
     * @throws ApiException
     */
    public function fetchPage(array $filters, int $page, int $pageSize = 100): array
    {
        $builder = new MagentoQueryBuilder($this->query, $pageSize);
        $builder->filter($filters)->page($page);

        $response = $this->client->query($builder->getQuery(), $builder->getVariables());

        $transformed = $this->adapter->transform($response);
        $pagination = $this->adapter->extractPaginationInfo($response);

        return [
            'products' => $transformed['products'],
            'errors' => $transformed['errors'],
            'page' => $pagination['current_page'] ?? $page,
            'total_pages' => $pagination['total_pages'] ?? 1,
            'total_count' => $pagination['total_count'] ?? count($transformed['products']),
        ];
    }

    /**
     * Fetch all products and return as a single array
     *
     * Warning: This loads all products into memory. Use fetchAll() generator
     * for large catalogs to avoid memory issues.
     *
     * @param array $filters Magento filter structure
     * @param int $pageSize Products per page
     * @return array{products: array, errors: array, total_count: int, pages_fetched: int}
     * @throws ApiException
     */
    public function fetchAllAsArray(array $filters = [], int $pageSize = 100): array
    {
        $allProducts = [];
        $allErrors = [];
        $pagesFetched = 0;
        $totalCount = 0;

        foreach ($this->fetchAll($filters, $pageSize) as $batch) {
            $allProducts = array_merge($allProducts, $batch['products']);
            $allErrors = array_merge($allErrors, $batch['errors']);
            $pagesFetched++;
            $totalCount = $batch['total_count'];
        }

        return [
            'products' => $allProducts,
            'errors' => $allErrors,
            'total_count' => $totalCount,
            'pages_fetched' => $pagesFetched,
        ];
    }

    /**
     * Get total count of products matching filters (fetches first page only)
     *
     * @param array $filters Magento filter structure
     * @return int Total number of products
     * @throws ApiException
     */
    public function getTotalCount(array $filters = []): int
    {
        $result = $this->fetchPage($filters, 1, 1);
        return $result['total_count'];
    }

    /**
     * Get all errors accumulated during fetchAll()
     */
    public function getErrors(): array
    {
        return $this->allErrors;
    }
}
