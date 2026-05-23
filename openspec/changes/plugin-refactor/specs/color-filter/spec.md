# Delta for Color Filter

## ADDED Requirements

### REQ-1: Configurable Taxonomy
The plugin MUST provide an admin settings page under WooCommerce > Settings > Products. Admin SHALL select from available product attribute taxonomies via dropdown. Default MUST be `pa_color`. Configured taxonomy SHALL drive both display and query filtering.

#### Scenario: Admin selects different taxonomy
- GIVEN admin has `pa_talle` attribute with terms "S", "M", "L"
- WHEN admin sets "Talle" as filter taxonomy in settings
- THEN shop page renders pills for "S", "M", "L" instead of color terms
- AND selecting "M" filters by `pa_talle` taxonomy

#### Scenario: Fallback on missing setting or deleted taxonomy
- GIVEN plugin has no saved setting OR saved taxonomy was deleted
- WHEN a customer visits the shop page
- THEN plugin falls back to `pa_color` without errors
- AND renders filters only if `pa_color` exists with terms

### REQ-2: OOP Architecture
The plugin MUST encapsulate all functionality in a singleton `Zira_Color_Filter` class. Hooks SHALL register in a dedicated `init()` method. All methods SHALL have appropriate visibility. Main plugin file SHALL bootstrap via `Zira_Color_Filter::get_instance()`.

#### Scenario: Plugin activated
- GIVEN WooCommerce is active and plugin is activated
- WHEN WordPress loads the plugin file
- THEN a single `Zira_Color_Filter` instance is created
- AND all hooks are registered exactly once

### REQ-3: External CSS Loading
The plugin MUST enqueue CSS from `assets/css/zira-color-filter.css` via `wp_enqueue_style()` with a version query string matching the plugin version. Visual output SHALL match the current inline appearance.

#### Scenario: Shop page loads
- GIVEN configured taxonomy exists with terms
- WHEN visitor loads the shop page
- THEN CSS is enqueued as `<link>` tag with `?ver=` query string
- AND filter pills appear identical to the procedural version

### REQ-4: Color Swatches
The plugin MUST render colored circles before term names when `product_attribute_color` term meta is available. Terms without meta SHALL render as text-only pills. The "Todos" reset link SHALL NOT receive a swatch.

#### Scenario: Term has color meta
- GIVEN `pa_color` term "Rojo" has meta `product_attribute_color = #ff0000`
- WHEN shop page renders the filter
- THEN a red circle with inline `background-color: #ff0000` precedes the term name

#### Scenario: Term lacks color meta
- GIVEN configured taxonomy terms have no `product_attribute_color` meta
- WHEN filter renders
- THEN pills show only term names without visual errors

### REQ-5: Conditional CSS Loading
CSS MUST load only on `is_shop()` / `is_product_taxonomy()` pages AND only when the configured taxonomy has non-empty terms.

#### Scenario: Empty taxonomy or non-shop page
- GIVEN configured taxonomy has 0 terms OR visitor is on a single product page
- WHEN page loads
- THEN no CSS is enqueued and no filter is rendered

### REQ-6: i18n Support
Textdomain MUST be `zira-filtro-color`. `load_plugin_textdomain()` SHALL fire on `plugins_loaded`. All user-facing strings SHALL use `__()` or `_e()`. `.pot` SHALL exist at `languages/zira-filtro-color.pot`. Spanish SHALL be the default language.

#### Scenario: Translation available
- GIVEN `en_US` locale with `zira-filtro-color-en_US.mo` present
- WHEN shop page loads
- THEN filter label reads "Filter by color:" instead of "Filtrar por color:"

#### Scenario: No translation file
- GIVEN no `.mo` file exists for current locale
- WHEN plugin renders output
- THEN Spanish strings display as fallback

### REQ-7: Separate Assets Directory
CSS MUST exist as a static file at `assets/css/zira-color-filter.css`. The file SHALL contain all current inline rules plus swatch styles and SHALL be browser-cacheable with version-based cache busting.

#### Scenario: Browser caching
- GIVEN visitor returns to shop page
- WHEN browser receives 304 Not Modified for CSS
- THEN cached copy is used with zero network transfer

### Non-Functional Requirements
| Aspect | Requirement |
|--------|------------|
| Compatibility | WordPress 6.x+, WooCommerce 7.x+, PHP 7.4+ |
| Performance | No extra DB queries on non-shop pages |
| Naming | Class `Zira_Color_Filter`, prefix `zira_`, option `zira_color_filter_taxonomy` |
