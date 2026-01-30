<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchType;
use PHPUnit\Framework\TestCase;

class SearchTypeTest extends TestCase
{
    public function testMatchValue(): void
    {
        $this->assertEquals('match', SearchType::MATCH->value);
    }

    public function testMatchFuzzyValue(): void
    {
        $this->assertEquals('match-fuzzy', SearchType::MATCH_FUZZY->value);
    }

    public function testAutocompleteValue(): void
    {
        $this->assertEquals('autocomplete', SearchType::AUTOCOMPLETE->value);
    }

    public function testExactValue(): void
    {
        $this->assertEquals('exact', SearchType::EXACT->value);
    }

    public function testAutocompleteNospaceValue(): void
    {
        $this->assertEquals('autocomplete-nospace', SearchType::AUTOCOMPLETE_NOSPACE->value);
    }

    public function testSubstringValue(): void
    {
        $this->assertEquals('substring', SearchType::SUBSTRING->value);
    }

    public function testFromValidValues(): void
    {
        $this->assertEquals(SearchType::MATCH, SearchType::from('match'));
        $this->assertEquals(SearchType::MATCH_FUZZY, SearchType::from('match-fuzzy'));
        $this->assertEquals(SearchType::AUTOCOMPLETE, SearchType::from('autocomplete'));
        $this->assertEquals(SearchType::EXACT, SearchType::from('exact'));
        $this->assertEquals(SearchType::AUTOCOMPLETE_NOSPACE, SearchType::from('autocomplete-nospace'));
        $this->assertEquals(SearchType::SUBSTRING, SearchType::from('substring'));
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        SearchType::from('invalid');
    }

    public function testEnumCases(): void
    {
        $cases = SearchType::cases();

        $this->assertCount(6, $cases);
        $this->assertContains(SearchType::MATCH, $cases);
        $this->assertContains(SearchType::MATCH_FUZZY, $cases);
        $this->assertContains(SearchType::AUTOCOMPLETE, $cases);
        $this->assertContains(SearchType::EXACT, $cases);
        $this->assertContains(SearchType::AUTOCOMPLETE_NOSPACE, $cases);
        $this->assertContains(SearchType::SUBSTRING, $cases);
    }
}
