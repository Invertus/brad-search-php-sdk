<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\LastWordSearch;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchType;
use PHPUnit\Framework\TestCase;

class LastWordSearchTest extends TestCase
{
    public function testDefaultsToDisabledWithNoSearchTypes(): void
    {
        $config = new LastWordSearch();

        $this->assertFalse($config->enabled);
        $this->assertSame([], $config->searchTypes);
        $this->assertNull($config->fuzzyConfig);
    }

    public function testJsonSerializeAlwaysEmitsEnabled(): void
    {
        // The engine's `enabled` has no omitempty, and omitting it would read as false.
        $this->assertSame(['enabled' => false], (new LastWordSearch())->jsonSerialize());
    }

    public function testFromArrayReadsPerPositionSearchTypes(): void
    {
        $config = LastWordSearch::fromArray([
            'enabled' => true,
            'searchTypes' => [
                'first' => ['match', 'match-fuzzy'],
                'last' => ['autocomplete'],
            ],
        ]);

        $this->assertTrue($config->enabled);
        $this->assertEquals([SearchType::MATCH, SearchType::MATCH_FUZZY], $config->searchTypes['first']);
        $this->assertEquals([SearchType::AUTOCOMPLETE], $config->searchTypes['last']);
    }

    public function testFromArrayReadsLegacySnakeCaseSearchTypes(): void
    {
        $config = LastWordSearch::fromArray([
            'enabled' => true,
            'search_types' => ['full' => ['autocomplete']],
        ]);

        $this->assertEquals([SearchType::AUTOCOMPLETE], $config->searchTypes['full']);
        $this->assertSame(['autocomplete'], $config->jsonSerialize()['searchTypes']['full']);
    }

    public function testRoundTripsDisabledConfigWithFuzzyOverrides(): void
    {
        $data = [
            'enabled' => false,
            'searchTypes' => [
                'first' => ['match'],
                'last' => ['stemmed-fuzzy'],
                'full' => ['phrase-prefix'],
            ],
            'fuzzy_config' => [
                'last' => ['stemmed-fuzzy' => ['fuzziness' => '2', 'prefix_length' => 2]],
            ],
        ];

        $this->assertEquals($data, LastWordSearch::fromArray($data)->jsonSerialize());
    }

    public function testKeepsUnknownSearchTypeAsString(): void
    {
        $config = LastWordSearch::fromArray([
            'enabled' => true,
            'searchTypes' => ['last' => ['exact_300']],
        ]);

        $this->assertSame('exact_300', $config->searchTypes['last'][0]);
        $this->assertSame(['exact_300'], $config->jsonSerialize()['searchTypes']['last']);
    }

    public function testThrowsForUnknownPosition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown lastWordSearch position "middle"');

        new LastWordSearch(true, ['middle' => [SearchType::MATCH]]);
    }

    public function testThrowsForNonStringSearchTypeEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LastWordSearch(true, ['first' => [42]]);
    }

    public function testWithMethodsReturnNewInstances(): void
    {
        $config = new LastWordSearch(true, ['first' => [SearchType::MATCH]]);

        $disabled = $config->withEnabled(false);
        $this->assertTrue($config->enabled);
        $this->assertFalse($disabled->enabled);
        $this->assertEquals($config->searchTypes, $disabled->searchTypes);

        $retuned = $config->withSearchTypes(['last' => [SearchType::AUTOCOMPLETE]]);
        $this->assertEquals(['last' => [SearchType::AUTOCOMPLETE]], $retuned->searchTypes);

        $fuzzy = $config->withFuzzyConfig(['first' => ['match-fuzzy' => ['fuzziness' => 'AUTO']]]);
        $this->assertNull($config->fuzzyConfig);
        $this->assertNotNull($fuzzy->fuzzyConfig);
    }
}
