Feature: SyncV2Sdk URL Paths
  As a developer using the Brad Search PHP SDK
  I want the SDK to construct correct API URL paths
  So that my requests reach the correct endpoints

  Background:
    Given a valid SyncV2Sdk configuration with:
      | app_id   | 550e8400-e29b-41d4-a716-446655440000 |
      | api_url  | https://api.bradsearch.com           |
      | token    | test-bearer-token                    |

  Scenario: App ID is included in base API path
    When I create a SyncV2Sdk instance
    Then the base API path should be "api/v2/applications/550e8400-e29b-41d4-a716-446655440000/"
    And the app_id should be retrievable via getAppId()

  Scenario: All v2 API endpoints use correct base path
    Then createIndex should use endpoint "api/v2/applications/{app_id}/index"
    And getIndexInfo should use endpoint "api/v2/applications/{app_id}/index/info"
    And listIndexVersions should use endpoint "api/v2/applications/{app_id}/index/versions"
    And activateIndexVersion should use endpoint "api/v2/applications/{app_id}/index/activate"
    And deleteIndexVersion should use endpoint "api/v2/applications/{app_id}/index/version/{version}"
    And setConfiguration should use endpoint "api/v2/applications/{app_id}/configuration"
    And getConfiguration should use endpoint "api/v2/applications/{app_id}/configuration"
    And updateConfiguration should use endpoint "api/v2/applications/{app_id}/configuration"
    And deleteConfiguration should use endpoint "api/v2/applications/{app_id}/configuration"
    And setSynonyms should use endpoint "api/v2/applications/{app_id}/synonyms"
    And getSynonyms should use endpoint "api/v2/applications/{app_id}/synonyms?language={language}"
    And deleteSynonyms should use endpoint "api/v2/applications/{app_id}/synonyms?language={language}"
    And bulkOperations should use endpoint "api/v2/applications/{app_id}/sync/bulk-operations"

  Scenario: Search settings endpoints use global configuration path
    Then createSearchSettings should use endpoint "api/v2/configuration"
    And getSearchSettings should use endpoint "api/v2/configuration/{app_id}"
    And updateSearchSettings should use endpoint "api/v2/configuration/{app_id}"
    And deleteSearchSettings should use endpoint "api/v2/configuration/{app_id}"

  Scenario: Different app IDs produce different base paths
    Given a SyncV2Sdk with app_id "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"
    Then the base API path should be "api/v2/applications/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/"
    And getAppId should return "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"

  Scenario: URL paths follow v2 API version format
    When I inspect the base API path
    Then it should start with "api/v2/"
    And it should contain "/applications/"
    And it should end with a trailing slash
