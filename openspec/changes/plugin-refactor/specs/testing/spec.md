# Testing Specification

## Purpose
PHPUnit test infrastructure providing unit and integration test coverage for the Zira Color Filter plugin. Tests validate core filter logic, settings, term meta handling, and full WooCommerce integration flow.

## Requirements

### REQ-8: Test Infrastructure and Coverage
The project MUST include a PHPUnit test framework with unit and integration test suites.

- `composer.json` SHALL declare `phpunit/phpunit:^9.0`, `wp-phpunit/wp-phpunit:^6.0`, and `yoast/wp-test-utils:^1.0` as dev dependencies.
- `phpunit.xml.dist` SHALL configure PHPUnit with bootstrap and test suites.
- `tests/bootstrap.php` SHALL load the WordPress test framework.
- Unit tests (`tests/Unit/`) SHALL cover: filter logic (`filter_products_by_color`), term retrieval (`get_terms`), taxonomy resolution (`get_selected_taxonomy`), term meta (`get_color_meta`), and CSS loading conditions.
- Integration tests (`tests/Integration/`) SHALL cover: full filter flow with mock `WP_Query`, settings page registration, and i18n loading.
- `composer test` SHALL execute the full test suite.

#### Scenario: Running unit tests in isolation
- GIVEN Composer dependencies installed
- WHEN developer runs `vendor/bin/phpunit --testsuite unit`
- THEN all unit tests pass without WordPress database connection
- AND mock objects replace real WordPress objects

#### Scenario: Full suite execution
- GIVEN WordPress test environment configured (`wp test env`)
- WHEN developer runs `composer test`
- THEN all unit and integration tests pass
- AND suite exits with code 0

#### Scenario: Integration tests without environment
- GIVEN WordPress test environment NOT configured
- WHEN developer runs `vendor/bin/phpunit --testsuite integration`
- THEN bootstrap reports clear error about missing `WP_TESTS_DIR`
- AND unit tests remain independently runnable

### Non-Functional Requirements
- **Testability**: All public methods MUST be independently testable; no hardcoded WordPress globals in core logic
