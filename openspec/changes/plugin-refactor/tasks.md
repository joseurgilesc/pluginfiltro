# Tasks: Plugin Refactor (OOP + 8 Improvements)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~590 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 |
| Delivery strategy | auto-chain |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| # | Goal | Base | Lines |
|---|------|------|-------|
| 1 | Test infra + class skeleton (REQ-2, REQ-6, REQ-8) | feature/plugin-refactor | ~190 |
| 2 | All features — render, filter, admin, CSS (REQ-1, REQ-3→REQ-7) | PR 1 branch | ~250 |
| 3 | Unit + integration tests (REQ-8) | PR 2 branch | ~150 |

## Phase 1: Test Infrastructure (REQ-8) — ~90 lines

- [x] 1.1 Create `composer.json` with dev deps `phpunit/phpunit:^9.0`, `wp-phpunit/wp-phpunit:^6.0`, `yoast/wp-test-utils:^1.0` and `composer test` script. Verify: `composer install` succeeds.
- [x] 1.2 Create `phpunit.xml.dist` with unit suite (`tests/Unit`), integration suite (`tests/Integration`), bootstrap `tests/bootstrap.php`. Verify: `vendor/bin/phpunit` boots cleanly.
- [x] 1.3 Create `tests/bootstrap.php` loading WP test framework via `WP_TESTS_DIR` with clear missing-env error. Verify: guided error when env absent.

## Phase 2: Core OOP Structure (REQ-2, REQ-6) — ~85 lines

- [x] 2.1 Rewrite `zira-filtro-color.php` with singleton `Zira_Color_Filter`: `get_instance()`, private `__construct`/`__clone`/`__wakeup`, `init()` → `setup_hooks()` (5 hooks), `private $taxonomy`. Bootstrap: `Zira_Color_Filter::get_instance()`. Verify: class instantiates once; plugin activates.
- [x] 2.2 Implement `load_textdomain()` on `plugins_loaded` for `zira-filtro-color`. Create `languages/zira-filtro-color.pot` with Spanish source strings. Verify: textdomain registered.

## Phase 3: CSS & Rendering (REQ-3, REQ-4, REQ-5, REQ-7) — ~180 lines

- [x] 3.1 Create `assets/css/zira-color-filter.css` with extracted ~80 lines of inline rules + `.zira-color-filter__swatch` (16px circle, `border-radius:50%`, `margin-right:6px`, 1px border). Verify: all existing pill styles preserved.
- [x] 3.2 Implement `should_load_assets()` guarding on `is_shop() || is_product_taxonomy()` AND taxonomy exists with terms. `enqueue_assets()` calls `wp_enqueue_style()` with `ZIRA_COLOR_FILTER_VERSION`. Verify: CSS loads as `<link>` only on qualified pages.
- [x] 3.3 Implement `render_filter()` producing backward-compatible HTML (`.zira-color-filter__item` pills); `get_color_meta($term_id)` reads `product_attribute_color` term meta and prepends `<span class="zira-color-filter__swatch">` when hex present; text-only fallback; "Todos" never gets swatch. All strings `__()`/`_e()`. Verify: swatches render when meta exists.

## Phase 4: Filter Logic & Admin (REQ-1) — ~125 lines

- [x] 4.1 Implement `get_taxonomy()` resolves `get_option('zira_color_filter_taxonomy', 'pa_color')` with fallback; `get_terms()` with `hide_empty`; `get_current_color()` sanitizes `$_GET['filter_color']`. Verify: defaults to `pa_color`.
- [x] 4.2 Implement `filter_products(WP_Query)` appending `tax_query` with configured taxonomy + slug; guards `is_admin()` and `!is_main_query()` and empty `$_GET`. Verify: shop filters by term; admin unaffected.
- [x] 4.3 Implement `add_settings_page()` via inner `WC_Settings_Page` class; `<select>` from `wc_get_attribute_taxonomies()`; saves to `zira_color_filter_taxonomy` with `taxonomy_exists()` validation. Inject via `woocommerce_get_settings_pages`. Verify: section under WooCommerce > Settings > Products.

## Phase 5: Unit Tests (REQ-8) — ~100 lines

- [x] 5.1 Create `tests/Unit/ColorFilterTest.php` (265 lines, 11 tests) — singleton, taxonomy resolution (default + saved), should_display on non-shop, should_load_assets when no terms, get_color_meta hex/null, filter_products tax_query + empty guard, get_current_color sanitize/empty. Uses WP_UnitTestCase + ReflectionMethod for private method access. Verify: `vendor/bin/phpunit --testsuite unit` green.
- [x] 5.2 Create `tests/Unit/SettingsTest.php` (247 lines, 5 tests) — settings page registration via woocommerce_get_settings_pages filter, default pa_color, fields with real attributes, graceful info message when no attributes, option persistence. Skips if WooCommerce not loaded. Verify: `vendor/bin/phpunit --testsuite unit` green.

## Phase 6: Integration Tests (REQ-8) — ~50 lines

- [x] 6.1 Create `tests/Integration/FilterFlowTest.php` (300 lines, 8 tests) — full render on shop with terms, skip when no terms / non-shop, query modification via WP_Query, URL param generation, Todos clear link, swatch rendered when color meta exists / skipped when absent. Requires WordPress + WooCommerce test environment. Verify: `composer test` green when env present; clear error when absent.
