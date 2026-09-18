<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequestBuilder;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SynonymRule;
use PHPUnit\Framework\TestCase;

class SearchSettingsRequestSynonymRulesTest extends TestCase
{
    /** @var array<string, mixed> */
    private const QUERY_CONFIG = [
        'fields' => [
            ['type' => 'text', 'name' => 'name', 'locale_suffix' => true, 'searchTypes' => ['match', 'match-fuzzy']],
            ['type' => 'text', 'name' => 'sku', 'searchTypes' => ['exact', 'autocomplete']],
        ],
    ];

    public function testSynonymRulesAreOmittedWhenNotSet(): void
    {
        $request = new SearchSettingsRequest('app_123', rawQueryConfig: self::QUERY_CONFIG);

        $this->assertNull($request->synonymRules);
        $this->assertArrayNotHasKey('synonym_rules', $request->jsonSerialize());
    }

    public function testEmptySynonymRulesAreOmitted(): void
    {
        $request = new SearchSettingsRequest('app_123', rawQueryConfig: self::QUERY_CONFIG, synonymRules: []);

        $this->assertArrayNotHasKey('synonym_rules', $request->jsonSerialize());
    }

    public function testSynonymRulesSerializeKeyedByLanguage(): void
    {
        $request = new SearchSettingsRequest(
            'app_123',
            rawQueryConfig: self::QUERY_CONFIG,
            synonymRules: [
                'lt' => [new SynonymRule('samet', 't-550', 'sku'), new SynonymRule('james brown', 'jb', 'name')],
                'en' => [new SynonymRule('grinder', 'angle grinder', 'name')],
            ],
        );

        $json = $request->jsonSerialize();

        $this->assertSame([
            'lt' => [
                ['when' => 'samet', 'match' => 't-550', 'field' => 'sku'],
                ['when' => 'james brown', 'match' => 'jb', 'field' => 'name'],
            ],
            'en' => [
                ['when' => 'grinder', 'match' => 'angle grinder', 'field' => 'name'],
            ],
        ], $json['synonym_rules']);
    }

    public function testFromSearchConfigurationParsesSynonymRules(): void
    {
        $config = [
            'supported_locales' => ['lt-LT'],
            'query_config' => self::QUERY_CONFIG,
            'synonym_rules' => [
                'lt' => [
                    ['when' => 'samet', 'match' => 't-550', 'field' => 'sku'],
                ],
            ],
        ];

        $request = SearchSettingsRequest::fromSearchConfiguration('app_123', $config);

        $this->assertCount(1, $request->synonymRules['lt']);
        $this->assertInstanceOf(SynonymRule::class, $request->synonymRules['lt'][0]);
        $this->assertSame('t-550', $request->synonymRules['lt'][0]->match);
        $this->assertSame($config['synonym_rules'], $request->jsonSerialize()['synonym_rules']);
    }

    public function testFromSearchConfigurationWithoutSynonymRulesLeavesNull(): void
    {
        $request = SearchSettingsRequest::fromSearchConfiguration('app_123', ['query_config' => self::QUERY_CONFIG]);

        $this->assertNull($request->synonymRules);
    }

    public function testFromSearchConfigurationIgnoresLegacySynonymsConfigKey(): void
    {
        $config = [
            'query_config' => self::QUERY_CONFIG,
            'synonyms_config' => ['lt' => [['bulgarke', 'kampinis šlifuoklis']]],
        ];

        $request = SearchSettingsRequest::fromSearchConfiguration('app_123', $config);

        $this->assertNull($request->synonymRules);
        $this->assertArrayNotHasKey('synonyms_config', $request->jsonSerialize());
    }

    public function testFromSearchConfigurationRejectsMalformedRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "match" must be a string.');

        SearchSettingsRequest::fromSearchConfiguration('app_123', [
            'query_config' => self::QUERY_CONFIG,
            'synonym_rules' => ['lt' => [['when' => 'samet', 'field' => 'sku']]],
        ]);
    }

    public function testRuleWithEmptyWhenIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Synonym rule "when" cannot be empty.');

        new SearchSettingsRequest(
            'app_123',
            rawQueryConfig: self::QUERY_CONFIG,
            synonymRules: ['lt' => [new SynonymRule(' ', 't-550', 'sku')]],
        );
    }

    public function testRuleTargetingUnknownFieldIsRejected(): void
    {
        try {
            new SearchSettingsRequest(
                'app_123',
                rawQueryConfig: self::QUERY_CONFIG,
                synonymRules: ['lt' => [new SynonymRule('samet', 't-550', 'ean')]],
            );
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('synonym_rules', $e->argumentName);
            $this->assertSame('ean', $e->invalidValue);
            $this->assertStringContainsString('not in the query configuration', $e->getMessage());
        }
    }

    public function testFieldCheckUsesSearchConfigFieldNames(): void
    {
        $searchConfig = new SearchConfig([new FieldConfig('name_field', 'name')]);

        $ok = new SearchSettingsRequest(
            'app_123',
            $searchConfig,
            synonymRules: ['lt' => [new SynonymRule('bulgarke', 'kampinis šlifuoklis', 'name')]],
        );
        $this->assertCount(1, $ok->synonymRules['lt']);

        $this->expectException(InvalidArgumentException::class);
        new SearchSettingsRequest(
            'app_123',
            $searchConfig,
            synonymRules: ['lt' => [new SynonymRule('samet', 't-550', 'sku')]],
        );
    }

    public function testFieldCheckIsSkippedWithoutQueryConfiguration(): void
    {
        $request = new SearchSettingsRequest(
            'app_123',
            synonymRules: ['lt' => [new SynonymRule('samet', 't-550', 'sku')]],
        );

        $this->assertSame('sku', $request->synonymRules['lt'][0]->field);
    }

    public function testLanguageKeyMustBeIsoCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISO 639-1');

        new SearchSettingsRequest(
            'app_123',
            rawQueryConfig: self::QUERY_CONFIG,
            synonymRules: ['lt-LT' => [new SynonymRule('samet', 't-550', 'sku')]],
        );
    }

    public function testRulesMustBeSynonymRuleInstances(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain SynonymRule instances');

        new SearchSettingsRequest(
            'app_123',
            rawQueryConfig: self::QUERY_CONFIG,
            synonymRules: ['lt' => [['when' => 'samet', 'match' => 't-550', 'field' => 'sku']]],
        );
    }

    public function testWithSynonymRulesReplacesAndClears(): void
    {
        $request = new SearchSettingsRequest('app_123', rawQueryConfig: self::QUERY_CONFIG);

        $withRules = $request->withSynonymRules(['lt' => [new SynonymRule('samet', 't-550', 'sku')]]);
        $this->assertCount(1, $withRules->synonymRules['lt']);
        $this->assertNull($request->synonymRules);

        $this->assertNull($withRules->withSynonymRules(null)->synonymRules);
    }

    public function testWithAddedSynonymRuleAppendsPerLanguage(): void
    {
        $request = (new SearchSettingsRequest('app_123', rawQueryConfig: self::QUERY_CONFIG))
            ->withAddedSynonymRule('lt', new SynonymRule('samet', 't-550', 'sku'))
            ->withAddedSynonymRule('lt', new SynonymRule('james brown', 'jb', 'name'))
            ->withAddedSynonymRule('en', new SynonymRule('grinder', 'angle grinder', 'name'));

        $this->assertCount(2, $request->synonymRules['lt']);
        $this->assertCount(1, $request->synonymRules['en']);
    }

    public function testOtherWithMethodsPreserveSynonymRules(): void
    {
        $rules = ['lt' => [new SynonymRule('samet', 't-550', 'sku')]];
        $request = new SearchSettingsRequest('app_123', rawQueryConfig: self::QUERY_CONFIG, synonymRules: $rules);

        $this->assertSame($rules, $request->withAppId('app_456')->synonymRules);
        $this->assertSame($rules, $request->withScoringConfig(null)->synonymRules);
        $this->assertSame($rules, $request->withResponseConfig(null)->synonymRules);
        $this->assertSame($rules, $request->withSimilarity('boolean')->synonymRules);
    }

    public function testBuilderSetsAndAddsSynonymRules(): void
    {
        $request = (new SearchSettingsRequestBuilder())
            ->appId('app_123')
            ->rawQueryConfig(self::QUERY_CONFIG)
            ->synonymRules(['lt' => [new SynonymRule('samet', 't-550', 'sku')]])
            ->addSynonymRule('lt', new SynonymRule('james brown', 'jb', 'name'))
            ->addSynonymRule('en', new SynonymRule('grinder', 'angle grinder', 'name'))
            ->build();

        $json = $request->jsonSerialize();
        $this->assertCount(2, $json['synonym_rules']['lt']);
        $this->assertSame(['when' => 'grinder', 'match' => 'angle grinder', 'field' => 'name'], $json['synonym_rules']['en'][0]);
    }

    public function testBuilderResetClearsSynonymRules(): void
    {
        $builder = (new SearchSettingsRequestBuilder())
            ->appId('app_123')
            ->addSynonymRule('lt', new SynonymRule('samet', 't-550', 'sku'));

        $request = $builder->reset()->appId('app_123')->build();

        $this->assertNull($request->synonymRules);
    }
}
