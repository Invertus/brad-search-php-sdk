<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2;

use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperation;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\BulkOperationsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\Product;
use BradSearch\SyncSdk\V2\ValueObjects\BulkOperations\ProductVariant;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldDefinition;
use BradSearch\SyncSdk\V2\ValueObjects\Index\FieldType;
use BradSearch\SyncSdk\V2\ValueObjects\Index\IndexCreateRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Index\VariantAttribute;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ImageUrl;
use BradSearch\SyncSdk\V2\ValueObjects\Product\ProductPricing;
use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MatchMode;
use BradSearch\SyncSdk\V2\ValueObjects\Search\MultiWordOperator;
use BradSearch\SyncSdk\V2\ValueObjects\Search\PopularityBoostConfig;
use BradSearch\SyncSdk\V2\ValueObjects\Search\QueryConfigurationRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Search\SearchFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\BoostMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\FunctionScoreModifier;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\MultiMatchType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\NestedFieldConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ResponseConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoreMode;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\ScoringConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehavior;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchBehaviorType;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchConfig;
use BradSearch\SyncSdk\V2\ValueObjects\SearchSettings\SearchSettingsRequest;
use BradSearch\SyncSdk\V2\ValueObjects\Synonym\SynonymConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * API Payload Verification Tests
 *
 * These tests verify that SDK-generated JSON payloads exactly match
 * the documented OpenAPI examples. Each test loads expected JSON from
 * fixtures and compares against SDK-built equivalent structures.
 *
 * The tests ensure that any structural difference between SDK output
 * and documented examples will cause test failures.
 */
class ApiPayloadVerificationTest extends TestCase
{
    private const FIXTURES_PATH = __DIR__ . '/../fixtures/openapi-examples/';

    /**
     * Load and decode a JSON fixture file.
     *
     * @param string $filename The fixture filename
     * @return array<string, mixed> The decoded JSON data
     */
    private function loadFixture(string $filename): array
    {
        $path = self::FIXTURES_PATH . $filename;
        $this->assertFileExists($path, "Fixture file not found: {$filename}");

        $content = file_get_contents($path);
        $this->assertNotFalse($content, "Could not read fixture file: {$filename}");

        $data = json_decode($content, true);
        $this->assertIsArray($data, "Invalid JSON in fixture file: {$filename}");

        return $data;
    }

    /**
     * Test: IndexCreateRequest JSON matches 'Darbo drabuziai client' example exactly.
     *
     * This test verifies the SDK produces the exact structure documented
     * in the OpenAPI specification for index creation requests.
     */
    public function testIndexCreateRequestMatchesDarboDrabuziaiExample(): void
    {
        $expected = $this->loadFixture('index-create-darbo-drabuziai.json');

        $request = new IndexCreateRequest(
            ['lt-LT'],
            [
                new FieldDefinition('id', FieldType::KEYWORD),
                new FieldDefinition('name_lt-LT', FieldType::TEXT),
                new FieldDefinition('brand_lt-LT', FieldType::TEXT),
                new FieldDefinition('sku', FieldType::KEYWORD),
                new FieldDefinition('imageUrl', FieldType::IMAGE_URL),
                new FieldDefinition('description_lt-LT', FieldType::TEXT),
                new FieldDefinition('categories_lt-LT', FieldType::TEXT),
                new FieldDefinition('price', FieldType::DOUBLE),
                new FieldDefinition('variants', FieldType::VARIANTS, [
                    new VariantAttribute('size', FieldType::KEYWORD, true),
                    new VariantAttribute('color', FieldType::KEYWORD, true),
                ]),
            ]
        );

        $actual = $request->jsonSerialize();

        $this->assertEquals(
            $expected,
            $actual,
            'IndexCreateRequest JSON does not match Darbo drabuziai client example'
        );

        // Also verify JSON encoding/decoding roundtrip
        $encodedDecoded = json_decode(json_encode($request), true);
        $this->assertEquals(
            $expected,
            $encodedDecoded,
            'IndexCreateRequest JSON roundtrip does not match expected'
        );
    }

    /**
     * Test: QueryConfigurationRequest JSON matches 'advanced' example exactly.
     *
     * This test verifies the SDK produces the exact structure documented
     * in the OpenAPI specification for advanced query configurations.
     */
    public function testQueryConfigurationRequestMatchesAdvancedExample(): void
    {
        $expected = $this->loadFixture('configuration-advanced.json');

        $request = new QueryConfigurationRequest(
            [
                new SearchFieldConfig('name_lt-LT', 1, MatchMode::PHRASE_PREFIX),
                new SearchFieldConfig('brand_lt-LT', 2, MatchMode::FUZZY),
                new SearchFieldConfig('description_lt-LT', 3, MatchMode::FUZZY),
                new SearchFieldConfig('sku', 4, MatchMode::EXACT),
            ],
            new PopularityBoostConfig(true, 'sales_count', BoostAlgorithm::LOGARITHMIC, 3.0),
            MultiWordOperator::AND,
            0.1
        );

        $actual = $request->jsonSerialize();

        $this->assertEquals(
            $expected,
            $actual,
            'QueryConfigurationRequest JSON does not match advanced example'
        );

        // Also verify JSON encoding/decoding roundtrip
        $encodedDecoded = json_decode(json_encode($request), true);
        $this->assertEquals(
            $expected,
            $encodedDecoded,
            'QueryConfigurationRequest JSON roundtrip does not match expected'
        );
    }

    /**
     * Test: SynonymConfiguration JSON matches 'ecommerce-en' example exactly.
     *
     * This test verifies the SDK produces the exact structure documented
     * in the OpenAPI specification for synonym configurations.
     */
    public function testSynonymConfigurationMatchesEcommerceEnExample(): void
    {
        $expected = $this->loadFixture('synonyms-ecommerce-en.json');

        $config = new SynonymConfiguration('en', [
            ['laptop', 'notebook', 'computer'],
            ['phone', 'mobile', 'smartphone'],
            ['shoes', 'footwear', 'sneakers'],
        ]);

        $actual = $config->jsonSerialize();

        $this->assertEquals(
            $expected,
            $actual,
            'SynonymConfiguration JSON does not match ecommerce-en example'
        );

        // Also verify JSON encoding/decoding roundtrip
        $encodedDecoded = json_decode(json_encode($config), true);
        $this->assertEquals(
            $expected,
            $encodedDecoded,
            'SynonymConfiguration JSON roundtrip does not match expected'
        );
    }

    /**
     * Test: BulkOperationsRequest JSON matches 'darbo-drabuziai-indexing' example exactly.
     *
     * This test verifies the SDK produces the exact structure documented
     * in the OpenAPI specification for bulk operations with products and variants.
     *
     * The expected format uses:
     * - 'variants' array (not locale-specific like 'variants_lt-LT')
     * - 'attrs' with numeric keys and locale-value objects: {"0": {"lt-LT": "8"}}
     */
    public function testBulkOperationsRequestMatchesDarboDrabuziaiIndexingExample(): void
    {
        $expected = $this->loadFixture('bulk-operations-darbo-drabuziai.json');

        // Build variants in the new format with attrs having numeric keys and locale values
        $variant1 = [
            'id' => '4107',
            'sku' => 'GLOVES-4107',
            'price' => 1.64,
            'basePrice' => 2.05,
            'priceTaxExcluded' => 1.36,
            'basePriceTaxExcluded' => 1.69,
            'productUrl' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4107',
            'imageUrl' => [
                'small' => 'https://www.darbodrabuziai.lt/img/4107-s.jpg',
                'medium' => 'https://www.darbodrabuziai.lt/img/4107.jpg',
            ],
            'attrs' => [
                '101' => ['lt-LT' => '8'],
                '102' => ['lt-LT' => 'Juoda'],
            ],
        ];

        $variant2 = [
            'id' => '4108',
            'sku' => 'GLOVES-4108',
            'price' => 1.64,
            'basePrice' => 2.05,
            'priceTaxExcluded' => 1.36,
            'basePriceTaxExcluded' => 1.69,
            'productUrl' => 'https://www.darbodrabuziai.lt/produktai/pirstines/4108',
            'imageUrl' => [
                'small' => 'https://www.darbodrabuziai.lt/img/4108-s.jpg',
                'medium' => 'https://www.darbodrabuziai.lt/img/4108.jpg',
            ],
            'attrs' => [
                '101' => ['lt-LT' => '9'],
                '102' => ['lt-LT' => 'Juoda'],
            ],
        ];

        $productPricing = new ProductPricing(9.99, 12.99, 8.26, 10.74);
        $product = new Product(
            'prod-123',
            'MAIN-SKU',
            $productPricing,
            new ImageUrl(
                'https://www.darbodrabuziai.lt/img/main-s.jpg',
                'https://www.darbodrabuziai.lt/img/main.jpg'
            ),
            null,
            null,
            [
                'name_lt-LT' => 'Darbo pirštinės',
                'brand_lt-LT' => 'SafetyFirst',
                'productUrl_lt-LT' => 'https://www.darbodrabuziai.lt/produktai/pirstines',
                'variants' => [$variant1, $variant2],
            ]
        );

        $operation = BulkOperation::indexProducts([$product]);
        $request = new BulkOperationsRequest([$operation]);

        $actual = $request->jsonSerialize();

        $this->assertEquals(
            $expected,
            $actual,
            'BulkOperationsRequest JSON does not match darbo-drabuziai-indexing example'
        );

        // Also verify JSON encoding/decoding roundtrip
        $encodedDecoded = json_decode(json_encode($request), true);
        $this->assertEquals(
            $expected,
            $encodedDecoded,
            'BulkOperationsRequest JSON roundtrip does not match expected'
        );
    }

    /**
     * Test: SearchSettingsRequest JSON matches 'full configuration' example exactly.
     *
     * This test verifies the SDK produces the exact structure documented
     * in the OpenAPI specification for complete search settings configurations.
     */
    public function testSearchSettingsRequestMatchesFullConfigurationExample(): void
    {
        $expected = $this->loadFixture('search-settings-full.json');

        $searchBehaviors = [
            new SearchBehavior(SearchBehaviorType::FUZZY, 'keyword', 'and', 2.0, 1, 2),
            new SearchBehavior(SearchBehaviorType::PHRASE_PREFIX),
        ];

        $fields = [
            new FieldConfig('name_field', 'name', 'en', $searchBehaviors),
            new FieldConfig('description_field', 'description', 'en'),
        ];

        $nestedFields = [
            new NestedFieldConfig(
                'variants_config',
                'variants',
                null,
                ScoreMode::MAX,
                [new FieldConfig('variant_sku', 'sku')]
            ),
        ];

        $multiMatchConfigs = [
            new MultiMatchConfig(
                'name_desc_multi',
                ['name_field', 'description_field'],
                MultiMatchType::CROSS_FIELDS,
                'and',
                1.5
            ),
        ];

        $searchConfig = new SearchConfig($fields, $nestedFields, $multiMatchConfigs);

        $functionScore = new FunctionScoreConfig(
            'sales_count',
            FunctionScoreModifier::LOG1P,
            1.5,
            1.0,
            BoostMode::MULTIPLY,
            10.0
        );

        $scoringConfig = new ScoringConfig($functionScore, 0.1);

        $responseConfig = new ResponseConfig(
            ['id', 'name', 'price', 'description'],
            ['price', 'created_at', 'sales_count']
        );

        $request = new SearchSettingsRequest(
            'my_app_123',
            $searchConfig,
            $scoringConfig,
            $responseConfig
        );

        $actual = $request->jsonSerialize();

        $this->assertEquals(
            $expected,
            $actual,
            'SearchSettingsRequest JSON does not match full configuration example'
        );

        // Also verify JSON encoding/decoding roundtrip
        $encodedDecoded = json_decode(json_encode($request), true);
        $this->assertEquals(
            $expected,
            $encodedDecoded,
            'SearchSettingsRequest JSON roundtrip does not match expected'
        );
    }

    /**
     * Test that all fixture files exist and are valid JSON.
     *
     * This test ensures fixture files are maintained and accessible.
     */
    public function testAllFixtureFilesExistAndAreValid(): void
    {
        $expectedFixtures = [
            'index-create-darbo-drabuziai.json',
            'configuration-advanced.json',
            'synonyms-ecommerce-en.json',
            'bulk-operations-darbo-drabuziai.json',
            'search-settings-full.json',
        ];

        foreach ($expectedFixtures as $fixture) {
            $path = self::FIXTURES_PATH . $fixture;
            $this->assertFileExists($path, "Missing fixture: {$fixture}");

            $content = file_get_contents($path);
            $this->assertNotFalse($content, "Could not read: {$fixture}");

            $decoded = json_decode($content, true);
            $this->assertNotNull($decoded, "Invalid JSON in: {$fixture}");
            $this->assertIsArray($decoded, "Fixture not an array: {$fixture}");
        }
    }

    /**
     * Test that SDK output produces valid JSON with proper structure.
     *
     * This test ensures JSON encoding doesn't introduce any encoding issues.
     */
    public function testSdkOutputProducesValidJsonWithProperStructure(): void
    {
        $indexRequest = new IndexCreateRequest(
            ['lt-LT'],
            [new FieldDefinition('id', FieldType::KEYWORD)]
        );

        $queryRequest = new QueryConfigurationRequest(
            [new SearchFieldConfig('name', 1)]
        );

        $synonymConfig = new SynonymConfiguration('en', [['test', 'example']]);

        $bulkRequest = new BulkOperationsRequest([
            BulkOperation::indexProducts([
                new Product(
                    '1',
                    'SKU-1',
                    new ProductPricing(10.0, 10.0, 10.0, 10.0),
                    new ImageUrl('https://example.com/s.jpg', 'https://example.com/m.jpg')
                )
            ])
        ]);

        $searchSettings = new SearchSettingsRequest('app_123');

        // Verify each produces valid JSON
        $objects = [
            'IndexCreateRequest' => $indexRequest,
            'QueryConfigurationRequest' => $queryRequest,
            'SynonymConfiguration' => $synonymConfig,
            'BulkOperationsRequest' => $bulkRequest,
            'SearchSettingsRequest' => $searchSettings,
        ];

        foreach ($objects as $name => $object) {
            $json = json_encode($object);
            $this->assertNotFalse($json, "{$name} failed to encode to JSON");
            $this->assertJson($json, "{$name} produced invalid JSON");

            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded, "{$name} JSON did not decode to array");
        }
    }
}
