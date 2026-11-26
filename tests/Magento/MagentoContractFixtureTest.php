<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Magento;

use BradSearch\SyncSdk\Magento\MagentoProductQuery;
use PHPUnit\Framework\TestCase;

/**
 * The Magento contract fixture (tests/fixtures/platform-contracts/magento/products.json) is the
 * answer to MagentoProductQuery::getDefaultQuery(). The extension checks its real answer to that query
 * against a copy of the fixture, and brad-app's adapter test reads another copy, so a field added to or
 * removed from the query must change the fixture in the same PR; this test fails until it does.
 *
 * brad-app adds `skipped_count` to the query (MagentoApi::withSkippedCount()), so the fixture has it
 * although getDefaultQuery() does not.
 */
class MagentoContractFixtureTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/platform-contracts/magento/products.json';

    /** Fields brad-app adds to getDefaultQuery() before sending it. */
    private const ADDED_BY_BRAD_APP = ['skipped_count'];

    public function testEveryFieldTheDefaultQuerySelectsIsInTheFixture(): void
    {
        $selection = $this->selection(MagentoProductQuery::getDefaultQuery());
        $fixture = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $this->missingFromFixture($selection['bradProducts'], $fixture['data']['bradProducts'], 'bradProducts'));
    }

    public function testEveryFixtureFieldIsSelectedByTheDefaultQuery(): void
    {
        $selection = $this->selection(MagentoProductQuery::getDefaultQuery());
        $fixture = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $this->notSelected($fixture['data']['bradProducts'], $selection['bradProducts'], 'bradProducts'));
    }

    /**
     * @param array<string, mixed> $selection
     * @return list<string>
     */
    private function missingFromFixture(array $selection, mixed $fixture, string $path): array
    {
        $missing = [];
        foreach ($this->objects($fixture) as $object) {
            foreach ($selection as $field => $sub) {
                if (!array_key_exists($field, $object)) {
                    $missing[] = "{$path}.{$field}";
                    continue;
                }
                if ($sub !== null && $object[$field] !== null) {
                    $missing = array_merge($missing, $this->missingFromFixture($sub, $object[$field], "{$path}.{$field}"));
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param array<string, mixed> $selection
     * @return list<string>
     */
    private function notSelected(mixed $fixture, array $selection, string $path): array
    {
        $extra = [];
        foreach ($this->objects($fixture) as $object) {
            foreach ($object as $field => $value) {
                if ($path === 'bradProducts' && in_array($field, self::ADDED_BY_BRAD_APP, true)) {
                    continue;
                }
                if (!array_key_exists($field, $selection)) {
                    $extra[] = "{$path}.{$field}";
                    continue;
                }
                if ($selection[$field] !== null && is_array($value)) {
                    $extra = array_merge($extra, $this->notSelected($value, $selection[$field], "{$path}.{$field}"));
                }
            }
        }

        return array_values(array_unique($extra));
    }

    /**
     * The JSON objects in a fixture value: the value itself, or every element of a list.
     *
     * @return list<array<string, mixed>>
     */
    private function objects(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [$value];
    }

    /**
     * The operation's selection set as field => sub-selection (null for a leaf). Arguments are skipped;
     * the Magento queries use no aliases or fragments.
     *
     * @return array<string, mixed>
     */
    private function selection(string $query): array
    {
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[A-Za-z_$][A-Za-z0-9_]*|[{}():\[\],!=]/', $query, $matches);
        $tokens = $matches[0];
        $i = (int) array_search('{', $tokens, true);

        return $this->selectionSet($tokens, $i);
    }

    /**
     * @param list<string> $tokens
     * @return array<string, mixed>
     */
    private function selectionSet(array $tokens, int &$i): array
    {
        $set = [];
        $i++;
        while ($tokens[$i] !== '}') {
            $field = $tokens[$i++];
            if ($tokens[$i] === '(') {
                for ($depth = 0;; $i++) {
                    $depth += $tokens[$i] === '(' ? 1 : ($tokens[$i] === ')' ? -1 : 0);
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
            $set[$field] = $tokens[$i] === '{' ? $this->selectionSet($tokens, $i) : null;
        }
        $i++;

        return $set;
    }
}
