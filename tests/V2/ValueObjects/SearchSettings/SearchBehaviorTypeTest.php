<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehaviorType;
use PHPUnit\Framework\TestCase;

class SearchBehaviorTypeTest extends TestCase
{
    public function testExactCaseHasCorrectValue(): void
    {
        $this->assertEquals('exact', SearchBehaviorType::EXACT->value);
    }

    public function testMatchCaseHasCorrectValue(): void
    {
        $this->assertEquals('match', SearchBehaviorType::MATCH->value);
    }

    public function testFuzzyCaseHasCorrectValue(): void
    {
        $this->assertEquals('fuzzy', SearchBehaviorType::FUZZY->value);
    }

    public function testNgramCaseHasCorrectValue(): void
    {
        $this->assertEquals('ngram', SearchBehaviorType::NGRAM->value);
    }

    public function testPhrasePrefixCaseHasCorrectValue(): void
    {
        $this->assertEquals('phrase_prefix', SearchBehaviorType::PHRASE_PREFIX->value);
    }

    public function testPhraseCaseHasCorrectValue(): void
    {
        $this->assertEquals('phrase', SearchBehaviorType::PHRASE->value);
    }

    public function testAllCasesExist(): void
    {
        $cases = SearchBehaviorType::cases();
        $this->assertCount(6, $cases);
    }

    public function testCanBeCreatedFromValue(): void
    {
        $this->assertEquals(SearchBehaviorType::EXACT, SearchBehaviorType::from('exact'));
        $this->assertEquals(SearchBehaviorType::MATCH, SearchBehaviorType::from('match'));
        $this->assertEquals(SearchBehaviorType::FUZZY, SearchBehaviorType::from('fuzzy'));
        $this->assertEquals(SearchBehaviorType::NGRAM, SearchBehaviorType::from('ngram'));
        $this->assertEquals(SearchBehaviorType::PHRASE_PREFIX, SearchBehaviorType::from('phrase_prefix'));
        $this->assertEquals(SearchBehaviorType::PHRASE, SearchBehaviorType::from('phrase'));
    }
}
