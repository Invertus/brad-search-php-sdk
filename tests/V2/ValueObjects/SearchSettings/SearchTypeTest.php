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

    public function testStemmedValue(): void
    {
        $this->assertEquals('stemmed', SearchType::STEMMED->value);
    }

    public function testStemmedFuzzyValue(): void
    {
        $this->assertEquals('stemmed-fuzzy', SearchType::STEMMED_FUZZY->value);
    }

    public function testSplitAlphaNumValue(): void
    {
        $this->assertEquals('split-alpha-num', SearchType::SPLIT_ALPHA_NUM->value);
    }

    public function testSplitAlphaNumFuzzyValue(): void
    {
        $this->assertEquals('split-alpha-num-fuzzy', SearchType::SPLIT_ALPHA_NUM_FUZZY->value);
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

    public function testSubstringNospaceValue(): void
    {
        $this->assertEquals('substring-nospace', SearchType::SUBSTRING_NOSPACE->value);
    }

    public function testPhrasePrefixValue(): void
    {
        $this->assertEquals('phrase-prefix', SearchType::PHRASE_PREFIX->value);
    }

    public function testPhrasePrefixStemmedValue(): void
    {
        $this->assertEquals('phrase-prefix-stemmed', SearchType::PHRASE_PREFIX_STEMMED->value);
    }

    public function testPhoneticValue(): void
    {
        $this->assertEquals('phonetic', SearchType::PHONETIC->value);
    }

    public function testSynonymValue(): void
    {
        $this->assertEquals('synonym', SearchType::SYNONYM->value);
    }

    public function testFromValidValues(): void
    {
        $this->assertEquals(SearchType::MATCH, SearchType::from('match'));
        $this->assertEquals(SearchType::MATCH_FUZZY, SearchType::from('match-fuzzy'));
        $this->assertEquals(SearchType::STEMMED, SearchType::from('stemmed'));
        $this->assertEquals(SearchType::STEMMED_FUZZY, SearchType::from('stemmed-fuzzy'));
        $this->assertEquals(SearchType::SPLIT_ALPHA_NUM, SearchType::from('split-alpha-num'));
        $this->assertEquals(SearchType::SPLIT_ALPHA_NUM_FUZZY, SearchType::from('split-alpha-num-fuzzy'));
        $this->assertEquals(SearchType::AUTOCOMPLETE, SearchType::from('autocomplete'));
        $this->assertEquals(SearchType::EXACT, SearchType::from('exact'));
        $this->assertEquals(SearchType::AUTOCOMPLETE_NOSPACE, SearchType::from('autocomplete-nospace'));
        $this->assertEquals(SearchType::SUBSTRING, SearchType::from('substring'));
        $this->assertEquals(SearchType::SUBSTRING_NOSPACE, SearchType::from('substring-nospace'));
        $this->assertEquals(SearchType::PHRASE_PREFIX, SearchType::from('phrase-prefix'));
        $this->assertEquals(SearchType::PHRASE_PREFIX_STEMMED, SearchType::from('phrase-prefix-stemmed'));
        $this->assertEquals(SearchType::PHONETIC, SearchType::from('phonetic'));
        $this->assertEquals(SearchType::SYNONYM, SearchType::from('synonym'));
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        SearchType::from('invalid');
    }

    public function testEnumCases(): void
    {
        $cases = SearchType::cases();

        $this->assertCount(15, $cases);
        $this->assertContains(SearchType::MATCH, $cases);
        $this->assertContains(SearchType::MATCH_FUZZY, $cases);
        $this->assertContains(SearchType::STEMMED, $cases);
        $this->assertContains(SearchType::STEMMED_FUZZY, $cases);
        $this->assertContains(SearchType::SPLIT_ALPHA_NUM, $cases);
        $this->assertContains(SearchType::SPLIT_ALPHA_NUM_FUZZY, $cases);
        $this->assertContains(SearchType::AUTOCOMPLETE, $cases);
        $this->assertContains(SearchType::EXACT, $cases);
        $this->assertContains(SearchType::AUTOCOMPLETE_NOSPACE, $cases);
        $this->assertContains(SearchType::SUBSTRING, $cases);
        $this->assertContains(SearchType::SUBSTRING_NOSPACE, $cases);
        $this->assertContains(SearchType::PHRASE_PREFIX, $cases);
        $this->assertContains(SearchType::PHRASE_PREFIX_STEMMED, $cases);
        $this->assertContains(SearchType::PHONETIC, $cases);
        $this->assertContains(SearchType::SYNONYM, $cases);
    }
}
