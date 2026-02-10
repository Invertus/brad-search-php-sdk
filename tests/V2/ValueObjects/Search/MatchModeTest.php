<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use PHPUnit\Framework\TestCase;

class MatchModeTest extends TestCase
{
    public function testExactHasCorrectValue(): void
    {
        $this->assertEquals('exact', MatchMode::EXACT->value);
    }

    public function testFuzzyHasCorrectValue(): void
    {
        $this->assertEquals('fuzzy', MatchMode::FUZZY->value);
    }

    public function testPhrasePrefixHasCorrectValue(): void
    {
        $this->assertEquals('phrase_prefix', MatchMode::PHRASE_PREFIX->value);
    }

    public function testAllMatchModesAreStringBacked(): void
    {
        foreach (MatchMode::cases() as $mode) {
            $this->assertIsString($mode->value);
        }
    }

    public function testCasesReturnsAllModes(): void
    {
        $cases = MatchMode::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(MatchMode::EXACT, $cases);
        $this->assertContains(MatchMode::FUZZY, $cases);
        $this->assertContains(MatchMode::PHRASE_PREFIX, $cases);
    }

    public function testFromValidString(): void
    {
        $this->assertEquals(MatchMode::EXACT, MatchMode::from('exact'));
        $this->assertEquals(MatchMode::FUZZY, MatchMode::from('fuzzy'));
        $this->assertEquals(MatchMode::PHRASE_PREFIX, MatchMode::from('phrase_prefix'));
    }

    public function testTryFromInvalidStringReturnsNull(): void
    {
        $this->assertNull(MatchMode::tryFrom('invalid'));
        $this->assertNull(MatchMode::tryFrom(''));
    }
}
