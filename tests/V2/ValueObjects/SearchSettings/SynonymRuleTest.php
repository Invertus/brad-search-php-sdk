<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SynonymRule;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SynonymRuleTest extends TestCase
{
    public function testConstructorTrimsAndStoresParts(): void
    {
        $rule = new SynonymRule('  samet ', ' t-550', 'sku ');

        $this->assertSame('samet', $rule->when);
        $this->assertSame('t-550', $rule->match);
        $this->assertSame('sku', $rule->field);
    }

    public function testExtendsValueObjectAndIsJsonSerializable(): void
    {
        $rule = new SynonymRule('samet', 't-550', 'sku');

        $this->assertInstanceOf(ValueObject::class, $rule);
        $this->assertInstanceOf(JsonSerializable::class, $rule);
    }

    public function testJsonSerializeMatchesApiShape(): void
    {
        $rule = new SynonymRule('james brown', 'jb', 'name');

        $this->assertSame(
            ['when' => 'james brown', 'match' => 'jb', 'field' => 'name'],
            $rule->jsonSerialize()
        );
        $this->assertSame('{"when":"james brown","match":"jb","field":"name"}', json_encode($rule));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function emptyPartProvider(): array
    {
        return [
            'empty when' => ['', 't-550', 'sku', 'when'],
            'blank when' => ['   ', 't-550', 'sku', 'when'],
            'empty match' => ['samet', '', 'sku', 'match'],
            'blank match' => ['samet', "\t", 'sku', 'match'],
            'empty field' => ['samet', 't-550', '', 'field'],
            'nbsp-only when' => ["\u{00A0}", 't-550', 'sku', 'when'],
            'unicode-space match' => ['samet', "\u{2003}\u{00A0} ", 'sku', 'match'],
        ];
    }

    #[DataProvider('emptyPartProvider')]
    public function testEmptyPartThrows(string $when, string $match, string $field, string $argument): void
    {
        try {
            new SynonymRule($when, $match, $field);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($argument, $e->argumentName);
            $this->assertStringContainsString('cannot be empty', $e->getMessage());
        }
    }

    public function testFromArrayBuildsRule(): void
    {
        $rule = SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku']);

        $this->assertSame('samet', $rule->when);
        $this->assertSame('t-550', $rule->match);
        $this->assertSame('sku', $rule->field);
    }

    public function testFromArrayRejectsMissingKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "field" must be a string.');

        SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550']);
    }

    public function testFromArrayRejectsNonStringValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "when" must be a string.');

        SynonymRule::fromArray(['when' => ['samet'], 'match' => 't-550', 'field' => 'sku']);
    }

    public function testEnabledDefaultsToTrueAndIsOmittedFromJson(): void
    {
        $rule = new SynonymRule('samet', 't-550', 'sku');

        $this->assertTrue($rule->enabled);
        $this->assertSame(['when' => 'samet', 'match' => 't-550', 'field' => 'sku'], $rule->jsonSerialize());
    }

    public function testDisabledRuleSerializesTheFlag(): void
    {
        $rule = new SynonymRule('samet', 't-550', 'sku', false);

        $this->assertFalse($rule->enabled);
        $this->assertSame(
            ['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'enabled' => false],
            $rule->jsonSerialize()
        );
    }

    public function testFromArrayReadsTheEnabledFlag(): void
    {
        $this->assertFalse(
            SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'enabled' => false])->enabled
        );
        $this->assertTrue(
            SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku'])->enabled
        );
    }

    public function testFromArrayRejectsNonBooleanEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "enabled" must be a boolean.');

        SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'enabled' => 'no']);
    }

    public function testANullEnabledFlagMeansEnabled(): void
    {
        $rule = SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'enabled' => null]);

        $this->assertTrue($rule->enabled);
        $this->assertArrayNotHasKey('enabled', $rule->jsonSerialize());
    }

    public function testADisabledRuleSurvivesARoundTrip(): void
    {
        $rule = SynonymRule::fromArray(SynonymRule::fromArray(
            ['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'enabled' => false]
        )->jsonSerialize());

        $this->assertFalse($rule->enabled);
    }

    public function testTrimStripsUnicodeWhitespace(): void
    {
        $rule = new SynonymRule("\u{00A0}samet\u{2003}", "\u{FEFF}t-550 ", 'sku');

        $this->assertSame('samet', $rule->when);
        $this->assertSame('t-550', $rule->match);
    }

    public function testIdAndPairIdAreOmittedWhenNotSet(): void
    {
        $rule = new SynonymRule('samet', 't-550', 'sku');

        $this->assertNull($rule->id);
        $this->assertNull($rule->pairId);
        $this->assertSame('{"when":"samet","match":"t-550","field":"sku"}', json_encode($rule));
    }

    public function testIdAndPairIdSurviveARoundTrip(): void
    {
        $data = ['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'id' => 'r-1', 'pair_id' => 'p-1'];

        $rule = SynonymRule::fromArray($data);

        $this->assertSame('r-1', $rule->id);
        $this->assertSame('p-1', $rule->pairId);
        $this->assertSame($data, $rule->jsonSerialize());
    }

    public function testEmptyIdIsTreatedAsUnset(): void
    {
        $rule = SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'id' => '', 'pair_id' => ' ']);

        $this->assertNull($rule->id);
        $this->assertNull($rule->pairId);
    }

    public function testFromArrayRejectsNonStringPairId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "pair_id" must be a string.');

        SynonymRule::fromArray(['when' => 'samet', 'match' => 't-550', 'field' => 'sku', 'pair_id' => 7]);
    }
}
