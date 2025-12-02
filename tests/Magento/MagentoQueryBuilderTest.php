<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Magento;

use BradSearch\SyncSdk\Magento\MagentoProductQuery;
use BradSearch\SyncSdk\Magento\MagentoQueryBuilder;
use PHPUnit\Framework\TestCase;

class MagentoQueryBuilderTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $builder = new MagentoQueryBuilder();

        $this->assertSame(MagentoProductQuery::DEFAULT_QUERY, $builder->getQuery());
        $this->assertSame(100, $builder->getPageSize());
        $this->assertSame(1, $builder->getCurrentPage());
        $this->assertSame([], $builder->getFilters());
    }

    public function testCustomQuery(): void
    {
        $customQuery = 'query { products { items { id } } }';
        $builder = new MagentoQueryBuilder($customQuery);

        $this->assertSame($customQuery, $builder->getQuery());
    }

    public function testCustomDefaultPageSize(): void
    {
        $builder = new MagentoQueryBuilder(null, 50);

        $this->assertSame(50, $builder->getPageSize());
    }

    public function testSetQuery(): void
    {
        $builder = new MagentoQueryBuilder();
        $customQuery = 'custom query';

        $result = $builder->setQuery($customQuery);

        $this->assertSame($builder, $result);
        $this->assertSame($customQuery, $builder->getQuery());
    }

    public function testFilter(): void
    {
        $builder = new MagentoQueryBuilder();

        $result = $builder->filter(['category_id' => ['eq' => '2']]);

        $this->assertSame($builder, $result);
        $this->assertSame(['category_id' => ['eq' => '2']], $builder->getFilters());
    }

    public function testFilterMergesMultipleCalls(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder
            ->filter(['category_id' => ['eq' => '2']])
            ->filter(['sku' => ['in' => ['SKU-1', 'SKU-2']]]);

        $expected = [
            'category_id' => ['eq' => '2'],
            'sku' => ['in' => ['SKU-1', 'SKU-2']],
        ];

        $this->assertSame($expected, $builder->getFilters());
    }

    public function testResetFilters(): void
    {
        $builder = new MagentoQueryBuilder();
        $builder->filter(['category_id' => ['eq' => '2']]);

        $result = $builder->resetFilters();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getFilters());
    }

    public function testFilterByCategory(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filterByCategory(42);

        $this->assertSame(['category_id' => ['eq' => '42']], $builder->getFilters());
    }

    public function testFilterByCategoryWithString(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filterByCategory('42');

        $this->assertSame(['category_id' => ['eq' => '42']], $builder->getFilters());
    }

    public function testFilterBySkuSingle(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filterBySku('TEST-SKU');

        $this->assertSame(['sku' => ['eq' => 'TEST-SKU']], $builder->getFilters());
    }

    public function testFilterBySkuMultiple(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filterBySku(['SKU-1', 'SKU-2', 'SKU-3']);

        $this->assertSame(['sku' => ['in' => ['SKU-1', 'SKU-2', 'SKU-3']]], $builder->getFilters());
    }

    public function testFilterByUrlKey(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filterByUrlKey('my-product-url');

        $this->assertSame(['url_key' => ['eq' => 'my-product-url']], $builder->getFilters());
    }

    public function testPageSize(): void
    {
        $builder = new MagentoQueryBuilder();

        $result = $builder->pageSize(50);

        $this->assertSame($builder, $result);
        $this->assertSame(50, $builder->getPageSize());
    }

    public function testPage(): void
    {
        $builder = new MagentoQueryBuilder();

        $result = $builder->page(5);

        $this->assertSame($builder, $result);
        $this->assertSame(5, $builder->getCurrentPage());
    }

    public function testForPage(): void
    {
        $builder = new MagentoQueryBuilder();
        $builder->filter(['category_id' => ['eq' => '2']])->pageSize(50);

        $clone = $builder->forPage(3);

        // Original should be unchanged
        $this->assertSame(1, $builder->getCurrentPage());

        // Clone should have new page
        $this->assertSame(3, $clone->getCurrentPage());
        $this->assertSame(50, $clone->getPageSize());
        $this->assertSame(['category_id' => ['eq' => '2']], $clone->getFilters());
    }

    public function testGetVariablesWithoutFilters(): void
    {
        $builder = new MagentoQueryBuilder();

        $variables = $builder->getVariables();

        $this->assertSame(['pageSize' => 100, 'currentPage' => 1], $variables);
        $this->assertArrayNotHasKey('filter', $variables);
    }

    public function testGetVariablesWithFilters(): void
    {
        $builder = new MagentoQueryBuilder();
        $builder
            ->filter(['category_id' => ['eq' => '2']])
            ->pageSize(50)
            ->page(3);

        $variables = $builder->getVariables();

        $expected = [
            'pageSize' => 50,
            'currentPage' => 3,
            'filter' => ['category_id' => ['eq' => '2']],
        ];

        $this->assertSame($expected, $variables);
    }

    public function testFluentInterface(): void
    {
        $builder = new MagentoQueryBuilder();

        $result = $builder
            ->filter(['category_id' => ['eq' => '2']])
            ->filterBySku(['SKU-1', 'SKU-2'])
            ->pageSize(50)
            ->page(2);

        $this->assertSame($builder, $result);

        $variables = $builder->getVariables();

        $this->assertSame(50, $variables['pageSize']);
        $this->assertSame(2, $variables['currentPage']);
        $this->assertSame(['eq' => '2'], $variables['filter']['category_id']);
        $this->assertSame(['in' => ['SKU-1', 'SKU-2']], $variables['filter']['sku']);
    }

    public function testComplexFilterStructure(): void
    {
        $builder = new MagentoQueryBuilder();

        $builder->filter([
            'price' => ['from' => '10', 'to' => '100'],
            'name' => ['like' => '%shirt%'],
            'category_id' => ['in' => ['2', '3', '4']],
        ]);

        $filters = $builder->getFilters();

        $this->assertSame(['from' => '10', 'to' => '100'], $filters['price']);
        $this->assertSame(['like' => '%shirt%'], $filters['name']);
        $this->assertSame(['in' => ['2', '3', '4']], $filters['category_id']);
    }
}
