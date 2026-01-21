Feature: SyncV2Sdk Authentication
  As a developer using the Brad Search PHP SDK
  I want the SDK to handle authentication correctly
  So that my API requests are properly authenticated

  Background:
    Given a valid SyncV2Sdk configuration with:
      | app_id   | 550e8400-e29b-41d4-a716-446655440000 |
      | api_url  | https://api.bradsearch.com           |
      | token    | my-secret-bearer-token               |

  Scenario: Bearer token is used for authentication
    When I make any API request
    Then the Authorization header should be "Bearer my-secret-bearer-token"
    And the Content-Type header should be "application/json"

  Scenario: Token is passed to HttpClient during construction
    Given I create a new SyncV2Sdk instance
    Then the HttpClient should be configured with the bearer token
    And the HttpClient should use the configured API URL as base URL

  Scenario: All requests use Bearer authentication
    When I call createIndex with any fields
    Then the request should include Bearer token authentication

    When I call getIndexInfo
    Then the request should include Bearer token authentication

    When I call setConfiguration with any config
    Then the request should include Bearer token authentication

    When I call bulkOperations with any operations
    Then the request should include Bearer token authentication
