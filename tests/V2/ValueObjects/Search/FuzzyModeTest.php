<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\ValueObjects\Search\FuzzyMode;
use PHPUnit\Framework\TestCase;

class FuzzyModeTest extends TestCase
{
    public function testAutoModeHasCorrectValue(): void
    {
        $this->assertEquals('auto', FuzzyMode::AUTO->value);
    }

    public function testFixedModeHasCorrectValue(): void
    {
        $this->assertEquals('fixed', FuzzyMode::FIXED->value);
    }

    public function testHasExactlyTwoCases(): void
    {
        $cases = FuzzyMode::cases();

        $this->assertCount(2, $cases);
    }

    public function testCanBeCreatedFromString(): void
    {
        $auto = FuzzyMode::from('auto');
        $fixed = FuzzyMode::from('fixed');

        $this->assertEquals(FuzzyMode::AUTO, $auto);
        $this->assertEquals(FuzzyMode::FIXED, $fixed);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $result = FuzzyMode::tryFrom('invalid');

        $this->assertNull($result);
    }
}
