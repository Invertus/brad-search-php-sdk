Feature: SyncV2Sdk Data Passthrough
  As a developer using the Brad Search PHP SDK
  I want data to be passed through to the API without modification
  So that I have full control over the data sent and received

  Background:
    Given a valid SyncV2Sdk configuration with:
      | app_id   | 550e8400-e29b-41d4-a716-446655440000 |
      | api_url  | https://api.bradsearch.com           |
      | token    | test-bearer-token                    |

  # Request Data Passthrough

  Scenario: Field definitions are passed without modification
    Given I have complex field definitions:
      """
      [
        {
          "name": "categories",
          "type": "hierarchy",
          "settings": {
            "delimiter": " > ",
            "max_depth": 5,
            "nested": {
              "option1": true,
              "option2": ["a", "b", "c"]
            }
          }
        },
        {
          "name": "variants",
          "type": "variants",
          "attributes": ["color", "size"]
        }
      ]
      """
    When I call createIndex with these fields
    Then the exact field structure should be sent to the API
    And no fields should be added, removed, or modified

  Scenario: Configuration options are passed without modification
    Given I have configuration options:
      """
      {
        "search_fields": ["title", "description", "brand"],
        "fuzzy_matching": true,
        "custom_option": {
          "nested": "value",
          "array": [1, 2, 3]
        }
      }
      """
    When I call setConfiguration with these options
    Then the exact configuration should be sent to the API

  Scenario: Synonyms are passed without modification
    Given I have synonym groups:
      """
      [
        ["laptop", "notebook", "portable computer", "portable PC"],
        ["phone", "mobile", "smartphone", "cellphone", "cell"],
        ["TV", "television", "telly", "flat screen"]
      ]
      """
    When I call setSynonyms with language "en" and these synonyms
    Then the exact synonym structure should be sent to the API
    And the language should be included in the request body

  Scenario: Bulk operations are passed without modification
    Given I have bulk operations:
      """
      [
        {
          "type": "index_products",
          "payload": {
            "index_name": "products-v1",
            "products": [
              {"id": "prod-123", "name": "Product 1", "price": 99.99}
            ],
            "subfields": {
              "name": {"split_by": [" ", "-"], "max_count": 3}
            },
            "embeddablefields": {
              "description": "name"
            }
          }
        }
      ]
      """
    When I call bulkOperations with these operations
    Then the exact operations structure should be sent to the API
    And nested payload data should be preserved

  # Response Data Passthrough

  Scenario: API responses are returned without modification
    Given the API returns a response with extra fields:
      """
      {
        "status": "created",
        "version": 1,
        "index_name": "app_550e8400_v1",
        "extra_field": "extra_value",
        "nested_extra": {
          "key": "value"
        }
      }
      """
    When I call createIndex
    Then I should receive the exact API response
    And extra fields should be preserved in the response
    And nested extra fields should be preserved

  Scenario: Empty configuration is passed correctly
    When I call setConfiguration with an empty array
    Then an empty configuration should be sent to the API

  Scenario: Empty fields array is passed correctly
    When I call createIndex with an empty fields array
    Then an empty fields array should be sent to the API

  Scenario: Empty synonyms array is passed correctly
    When I call setSynonyms with an empty synonyms array
    Then an empty synonyms array should be sent to the API

  Scenario: Empty operations array is passed correctly
    When I call bulkOperations with an empty operations array
    Then an empty operations array should be sent to the API

  # Special Data Types

  Scenario: Numeric values preserve their types
    Given I have field definitions with numeric values:
      """
      [
        {"name": "price", "type": "float", "precision": 2},
        {"name": "quantity", "type": "integer", "min": 0, "max": 1000}
      ]
      """
    When I call createIndex with these fields
    Then numeric values should be preserved as numbers, not strings

  Scenario: Boolean values preserve their types
    Given I have configuration with boolean values:
      """
      {
        "fuzzy_matching": true,
        "case_sensitive": false,
        "enable_suggestions": true
      }
      """
    When I call setConfiguration with these options
    Then boolean values should be preserved as booleans, not strings
