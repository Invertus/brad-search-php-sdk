Feature: SyncV2Sdk
  As a developer using the Brad Search PHP SDK
  I want to interact with the v2 synchronization API
  So that I can manage search indices and synchronize products

  Background:
    Given a valid SyncV2Sdk configuration with:
      | app_id   | 550e8400-e29b-41d4-a716-446655440000 |
      | api_url  | https://api.bradsearch.com           |
      | token    | test-bearer-token                    |

  # Index Management

  Scenario: Create a new index with field definitions
    Given I have field definitions:
      | name  | type         |
      | id    | keyword      |
      | title | text_keyword |
      | price | float        |
    When I call createIndex with the fields
    Then the request should be sent to "api/v2/applications/{app_id}/index"
    And the request method should be "POST"
    And the request body should contain the fields array
    And I should receive the raw API response

  Scenario: Get index information
    When I call getIndexInfo
    Then the request should be sent to "api/v2/applications/{app_id}/index/info"
    And the request method should be "GET"
    And I should receive index details including:
      | alias_name     |
      | active_version |
      | active_index   |
      | all_versions   |

  Scenario: List all index versions
    When I call listIndexVersions
    Then the request should be sent to "api/v2/applications/{app_id}/index/versions"
    And the request method should be "GET"
    And I should receive a list of versions

  Scenario: Activate a specific index version
    Given I want to activate version 2
    When I call activateIndexVersion with version 2
    Then the request should be sent to "api/v2/applications/{app_id}/index/activate"
    And the request method should be "POST"
    And the request body should contain:
      | version | 2 |
    And I should receive activation confirmation

  Scenario: Delete an index version
    Given I want to delete version 1
    When I call deleteIndexVersion with version 1
    Then the request should be sent to "api/v2/applications/{app_id}/index/version/1"
    And the request method should be "DELETE"
    And I should receive deletion confirmation

  # Configuration Management

  Scenario: Set query configuration
    Given I have configuration options:
      | search_fields  | ["title", "description"] |
      | fuzzy_matching | true                     |
    When I call setConfiguration with the options
    Then the request should be sent to "api/v2/applications/{app_id}/configuration"
    And the request method should be "POST"
    And the configuration should be passed without modification

  Scenario: Get query configuration
    When I call getConfiguration
    Then the request should be sent to "api/v2/applications/{app_id}/configuration"
    And the request method should be "GET"
    And I should receive the current configuration

  Scenario: Update query configuration
    Given I have updated configuration options:
      | fuzzy_matching | false |
    When I call updateConfiguration with the options
    Then the request should be sent to "api/v2/applications/{app_id}/configuration"
    And the request method should be "PUT"
    And the updated configuration should be passed without modification

  Scenario: Delete query configuration
    When I call deleteConfiguration
    Then the request should be sent to "api/v2/applications/{app_id}/configuration"
    And the request method should be "DELETE"
    And I should receive deletion confirmation

  # Synonym Management

  Scenario: Set synonyms for a language
    Given I have synonyms for language "en":
      | laptop, notebook, portable computer |
      | phone, mobile, smartphone           |
    When I call setSynonyms with language "en" and the synonyms
    Then the request should be sent to "api/v2/applications/{app_id}/synonyms"
    And the request method should be "POST"
    And the request body should contain:
      | language | en |
    And the synonyms should be passed without modification

  Scenario: Get synonyms for a language
    When I call getSynonyms with language "en"
    Then the request should be sent to "api/v2/applications/{app_id}/synonyms?language=en"
    And the request method should be "GET"
    And I should receive the synonyms for the language

  Scenario: Delete synonyms for a language
    When I call deleteSynonyms with language "en"
    Then the request should be sent to "api/v2/applications/{app_id}/synonyms?language=en"
    And the request method should be "DELETE"
    And I should receive deletion confirmation

  # Bulk Operations

  Scenario: Perform bulk operations
    Given I have bulk operations:
      | type            | index_name   |
      | index_products  | products-v1  |
      | update_products | products-v1  |
      | delete_products | products-v1  |
    When I call bulkOperations with the operations
    Then the request should be sent to "api/v2/applications/{app_id}/sync/bulk-operations"
    And the request method should be "POST"
    And the operations should be passed without modification
    And I should receive operation results including:
      | total_operations      |
      | successful_operations |
      | failed_operations     |

  Scenario: Bulk operations with partial failure
    Given I have bulk operations that will partially fail
    When I call bulkOperations with the operations
    Then I should receive a partial success response
    And the response should contain failed operation details

  # Search Settings (Global Endpoints)

  Scenario: Create search settings
    Given I have search settings:
      | app_id         | 550e8400-e29b-41d4-a716-446655440000 |
      | search_fields  | ["title", "description"]             |
      | fuzzy_matching | true                                 |
    When I call createSearchSettings with the settings
    Then the request should be sent to "api/v2/configuration"
    And the request method should be "POST"
    And the settings should be passed without modification

  Scenario: Get search settings for an application
    Given I want to get settings for app_id "550e8400-e29b-41d4-a716-446655440000"
    When I call getSearchSettings with the app_id
    Then the request should be sent to "api/v2/configuration/{app_id}"
    And the request method should be "GET"
    And I should receive the application's search settings

  Scenario: Update search settings for an application
    Given I want to update settings for app_id "550e8400-e29b-41d4-a716-446655440000"
    And I have updated settings:
      | fuzzy_matching | false |
    When I call updateSearchSettings with the app_id and settings
    Then the request should be sent to "api/v2/configuration/{app_id}"
    And the request method should be "PUT"
    And the updated settings should be passed without modification

  Scenario: Delete search settings for an application
    Given I want to delete settings for app_id "550e8400-e29b-41d4-a716-446655440000"
    When I call deleteSearchSettings with the app_id
    Then the request should be sent to "api/v2/configuration/{app_id}"
    And the request method should be "DELETE"
    And I should receive deletion confirmation
