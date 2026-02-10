<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\QueryFieldType;
use PHPUnit\Framework\TestCase;

class QueryFieldTypeTest extends TestCase
{
    public function testTextValue(): void
    {
        $this->assertEquals('text', QueryFieldType::TEXT->value);
    }

    public function testNestedValue(): void
    {
        $this->assertEquals('nested', QueryFieldType::NESTED->value);
    }

    public function testFromValidText(): void
    {
        $type = QueryFieldType::from('text');
        $this->assertEquals(QueryFieldType::TEXT, $type);
    }

    public function testFromValidNested(): void
    {
        $type = QueryFieldType::from('nested');
        $this->assertEquals(QueryFieldType::NESTED, $type);
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        QueryFieldType::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $type = QueryFieldType::tryFrom('text');
        $this->assertEquals(QueryFieldType::TEXT, $type);
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $type = QueryFieldType::tryFrom('invalid');
        $this->assertNull($type);
    }

    public function testEnumCases(): void
    {
        $cases = QueryFieldType::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(QueryFieldType::TEXT, $cases);
        $this->assertContains(QueryFieldType::NESTED, $cases);
    }
}
