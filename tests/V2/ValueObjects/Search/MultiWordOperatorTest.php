<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use PHPUnit\Framework\TestCase;

class MultiWordOperatorTest extends TestCase
{
    public function testAndOperatorHasCorrectValue(): void
    {
        $this->assertEquals('and', MultiWordOperator::AND->value);
    }

    public function testOrOperatorHasCorrectValue(): void
    {
        $this->assertEquals('or', MultiWordOperator::OR->value);
    }

    public function testAllCasesExist(): void
    {
        $cases = MultiWordOperator::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(MultiWordOperator::AND, $cases);
        $this->assertContains(MultiWordOperator::OR, $cases);
    }

    /**
     * @dataProvider operatorDataProvider
     */
    public function testOperatorValues(MultiWordOperator $operator, string $expectedValue): void
    {
        $this->assertEquals($expectedValue, $operator->value);
    }

    /**
     * @return array<string, array{MultiWordOperator, string}>
     */
    public static function operatorDataProvider(): array
    {
        return [
            'and' => [MultiWordOperator::AND, 'and'],
            'or' => [MultiWordOperator::OR, 'or'],
        ];
    }

    public function testCanBeCreatedFromString(): void
    {
        $this->assertEquals(MultiWordOperator::AND, MultiWordOperator::from('and'));
        $this->assertEquals(MultiWordOperator::OR, MultiWordOperator::from('or'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(MultiWordOperator::tryFrom('invalid'));
        $this->assertNull(MultiWordOperator::tryFrom('AND'));
        $this->assertNull(MultiWordOperator::tryFrom('OR'));
    }
}
