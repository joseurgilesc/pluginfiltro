# Proposal: plugin-refactor

## Intent

Transform the single-file procedural WooCommerce color filter plugin into a maintainable, configurable, and extensible OOP-based plugin. The current implementation works but is fragile: hardcoded taxonomy, no admin UI, inline-only CSS, no i18n, and no visual color swatches. This refactor addresses all 8 improvement requirements while preserving the existing behavior as the default.

## Scope

### In Scope

1. **OOP architecture**: Wrap all functionality in a `Zira_Color_Filter` class with singleton pattern
2. **Configurable taxonomy**: Admin settings page (under WooCommerce > Settings > Products) to select the product attribute to filter by, defaulting to `pa_color`
3. **Proper CSS loading**: Extract CSS to `assets/css/zira-color-filter.css` and enqueue with `wp_enqueue_style()`
4. **Color swatches**: Read `product_attribute_color` term meta and render colored circles next to filter pills; fall back to text-only if no color meta exists
5. **Conditional CSS loading**: Load CSS only on shop/archive pages AND only when the configured taxonomy has terms
6. **i18n support**: Add `zira-filtro-color` textdomain, `load_plugin_textdomain()`, wrapper functions (`__()`, `_e()`), and `.pot` template
7. **Separate asset file**: `assets/css/zira-color-filter.css` as a proper static file for browser caching
8. **Test infrastructure and coverage**: PHPUnit setup with `composer.json`, test bootstrap, unit tests for core logic, integration tests for WordPress interactions

### Out of Scope

- Custom taxonomy registration (the plugin only uses existing attribute taxonomies)
- AJAX filtering or live search
- Multiple simultaneous filters (color + size, etc.)
- Filter widget or Gutenberg block
- JavaScript interactivity (the plugin remains server-rendered)
- Migration/upgrade script (defaults preserve behavior)
- CI/CD pipeline (test runner configured locally; CI can be added later)

## Capabilities

### New Capabilities

- `testing`: Test infrastructure including PHPUnit, WordPress test bootstrap, unit tests, and integration tests

### Modified Capabilities

- `color-filter`: Added REQ-8 (testing) as a new requirement; existing REQ-1 through REQ-7 unchanged

## Approach

### File Structure

```
pluginfiltro/
├── zira-filtro-color.php          ← Main plugin file (class loader + bootstrap)
├── assets/
│   └── css/
│       └── zira-color-filter.css  ← Extracted stylesheet
├── languages/
│   └── zira-filtro-color.pot      ← i18n template
├── tests/
│   ├── bootstrap.php              ← WordPress test framework bootstrap
│   ├── Unit/
│   │   ├── ColorFilterTest.php    ← Tests for filter_products_by_color, get_terms, get_selected_taxonomy
│   │   └── SettingsTest.php       ← Tests for settings page, CSS loading conditions
│   └── Integration/
│       └── FilterFlowTest.php     ← Full filter flow with mock WP_Query
├── composer.json                  ← Composer dev dependencies (phpunit, wp-test-utils)
├── phpunit.xml.dist               ← PHPUnit configuration
└── openspec/                      ← SDD artifacts
```

### Class Architecture

```
Zira_Color_Filter
├── private static $instance       ← Singleton instance
├── private $taxonomy              ← Configured taxonomy (default: 'pa_color')
├── public static get_instance()   ← Singleton accessor
├── private __construct()          ← Register hooks
├── public init()                  ← Hook registration
├── public load_textdomain()       ← i18n init
├── public enqueue_assets()        ← CSS loading with conditional checks
├── public render_filter()         ← Filter HTML output (ex-zira_mostrar_filtro_colores)
├── public apply_filter_query()    ← Query modification (ex-zira_filtrar_productos_por_color)
├── public add_settings()          ← Admin settings page (WooCommerce > Settings > Products)
├── private get_taxonomy()         ← Resolve configured taxonomy with fallback
├── private get_terms()            ← Get terms with caching consideration
└── private get_term_color()       ← Read 'product_attribute_color' term meta
```

### Hook Registration (in `init()`)

| Hook | Method | Priority |
|------|--------|----------|
| `plugins_loaded` | `load_textdomain()` | 10 |
| `wp_enqueue_scripts` | `enqueue_assets()` | 10 |
| `woocommerce_before_shop_loop` | `render_filter()` | 15 |
| `woocommerce_product_query` | `apply_filter_query()` | 10 |
| `woocommerce_get_settings_pages` | `add_settings()` | 10 |

### Admin Settings

- Filter: `woocommerce_get_settings_pages` to inject a custom settings class
- Option name: `zira_color_filter_taxonomy`
- Setting type: `<select>` populated from `wc_get_attribute_taxonomies()`
- Default: `pa_color`
- Sanitization: validate taxonomy exists before saving

### Color Swatches

Read the `product_attribute_color` term meta key (WooCommerce de facto standard for attribute color values). Render inline as a 14px circle with the hex color as background. Fall back to text-only pill when no meta exists.

### Conditional CSS Loading

The `enqueue_assets()` method checks: (1) is this a shop or taxonomy page? (2) does the configured taxonomy exist? (3) does it have at least one term with products? If any check fails, no CSS is enqueued.

### i18n

- Textdomain: `zira-filtro-color`
- Load on `plugins_loaded` with priority 10
- POT file at `languages/zira-filtro-color.pot`
- All display strings wrapped: `__( 'Filtrar por color:', 'zira-filtro-color' )`
- Admin strings also wrapped

### Testing Infrastructure

- **PHPUnit**: Composer dev dependency with `phpunit/phpunit:^9.0`, `wp-phpunit/wp-phpunit:^6.0`, `yoast/wp-test-utils:^1.0`
- **Bootstrap**: `tests/bootstrap.php` loads the WordPress test framework
- **Unit tests** (`tests/Unit/`): `ColorFilterTest` covers `filter_products_by_color()`, `get_terms()`, `get_selected_taxonomy()`, `get_color_meta()`, and CSS loading conditions
- **Integration tests** (`tests/Integration/`): Full filter flow with mock `WP_Query`, settings page registration, i18n loading
- **Run**: `vendor/bin/phpunit` or `composer test`

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `zira-filtro-color.php` | Modified | Full OOP rewrite, all hooks consolidated |
| `assets/css/zira-color-filter.css` | New | Extracted stylesheet + swatch rules |
| `languages/zira-filtro-color.pot` | New | i18n template |
| `tests/` | New | PHPUnit tests (unit + integration) |
| `composer.json` | New | Dev dependencies for testing |
| `phpunit.xml.dist` | New | PHPUnit configuration |

## Risks

1. **`product_attribute_color` meta key variance**: While it's a de facto standard, some themes use `_color` or other keys. Mitigation: document the expected key; it matches WooCommerce core behavior.

2. **Settings page visibility**: The settings page only appears when WooCommerce is active. If WooCommerce is deactivated, the plugin should gracefully degrade (already guarded by `taxonomy_exists()`).

3. **Backwards compatibility**: Old installations upgrading from 1.0.0 will see the admin setting default to `pa_color`, preserving existing behavior. No migration needed.

4. **WordPress test framework dependency**: `wp-phpunit/wp-phpunit` requires a local WordPress install and database for integration tests. Mitigation: unit tests run in isolation; integration tests require `wp test env` setup documented in `tests/bootstrap.php`.

## Estimated Effort

| Requirement | Files | Lines (est.) | Complexity |
|-------------|-------|-------------|------------|
| 1. Configurable taxonomy | `zira-filtro-color.php` (settings class) | ~80 PHP | Medium |
| 2. OOP refactor | `zira-filtro-color.php` (main class) | ~200 PHP | Medium |
| 3. Fix CSS inline hack | `zira-filtro-color.php` (enqueue) | ~10 PHP | Low |
| 4. Color swatches | `zira-filtro-color.php` (render) | ~30 PHP + ~15 CSS | Low |
| 5. Conditional CSS loading | `zira-filtro-color.php` (enqueue) | ~20 PHP | Low |
| 6. i18n support | `zira-filtro-color.php` + `.pot` | ~5 PHP + POT file | Low |
| 7. Separate CSS file | `assets/css/zira-color-filter.css` | ~80 CSS | Low |
| 8. Testing infrastructure | `tests/`, `composer.json`, `phpunit.xml.dist` | ~150 PHP test code + ~30 JSON/XML config | Medium |
| **Total** | 7 files | ~495 PHP + ~95 CSS + config | **Medium-High** |

## Rollback Plan

- The refactored plugin is a drop-in replacement. If issues arise:
  1. Deactivate the new version
  2. Restore `zira-filtro-color.php` from backup (or git revert)
  3. Clear WooCommerce transients and any page cache
- No database changes are made (only a single `zira_color_filter_taxonomy` option is added).
- The CSS file is purely additive — removing the plugin removes the enqueue.
- Test files and `composer.json` are development-only and do not affect production.

## Dependencies

- WooCommerce 7.x+ (existing)
- PHPUnit 9.x (dev only)
- wp-phpunit/wp-phpunit 6.x (dev only, integration tests)
- yoast/wp-test-utils 1.x (dev only)

## Success Criteria

- [ ] Plugin loads as a singleton class with all hooks registered
- [ ] Admin can change filter taxonomy via WooCommerce settings
- [ ] CSS loads externally (not inline) only on applicable pages
- [ ] Color swatches render when term meta exists; text-only fallback works
- [ ] i18n strings are translatable with `.pot` template generated
- [ ] All existing filter behavior preserved under default settings
- [ ] `composer test` runs full test suite with no failures
- [ ] Unit tests cover `filter_products_by_color`, `get_terms`, `get_selected_taxonomy`, `get_color_meta`, and CSS conditions
- [ ] Integration tests cover full filter flow and settings page registration
