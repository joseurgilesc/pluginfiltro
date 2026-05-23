# Exploration: Plugin Refactor

## Current State

The plugin is a single-file procedural PHP plugin (140 lines, `zira-filtro-color.php`) that adds a color filter to WooCommerce shop pages using the `pa_color` taxonomy. It consists of three global functions hooked into WordPress/WooCommerce actions:

1. `zira_mostrar_filtro_colores()` — renders pill-style filter links in a `.zira-color-filter` container on `woocommerce_before_shop_loop` (priority 15). Filters by reading `$_GET['filter_color']`, builds URLs with `add_query_arg`/`remove_query_arg`.

2. `zira_filtrar_productos_por_color()` — modifies `woocommerce_product_query` to append a `tax_query` clause targeting `pa_color` term slugs.

3. `zira_filtro_color_estilos()` — conditionally loads inline CSS via `wp_register_style( 'zira-filtro-color', false )` + `wp_add_inline_style()` on `wp_enqueue_scripts`.

All strings are in Spanish, hardcoded. No textdomain. No admin configuration. No color swatches. The taxonomy `pa_color` is hardcoded in two separate functions (display and filtering).

## Affected Areas

- `/Users/jose/Documents/pluginfiltro/zira-filtro-color.php` — complete rewrite from procedural to OOP
- New: `assets/css/zira-color-filter.css` — extracted stylesheet for browser caching
- New: `languages/zira-filtro-color.pot` — i18n template
- WooCommerce admin: new settings section under WooCommerce > Settings > Products (or dedicated tab)

## Technical Investigation

### Taxonomy Hardcoding
- `pa_color` appears on lines 20 and 79 — both the display function and the query filter use it independently. Any change requires updating both.
- WooCommerce product attributes are stored as `pa_{slug}` taxonomies registered via `register_taxonomy()`.
- `taxonomy_exists()` is already used as a guard, but only for the hardcoded value.

### CSS Inline Hack Pattern
- `wp_register_style( 'zira-filtro-color', false )` creates a dummy stylesheet handle with no file.
- This works but means CSS is delivered inline on every page load — no browser caching, no minification by caching plugins.
- The proper pattern is either (a) `wp_enqueue_style()` with a real `.css` file, or (b) `wp_enqueue_style( 'zira-filtro-color', '' )` + `wp_add_inline_style()`. WordPress core supports empty-source enqueues for handle-only registration.

### WooCommerce Color Term Meta Convention
- When using the "Color" type for product attributes (WooCommerce > Attributes > Configure), WooCommerce core and most themes store the hex color value in term meta key `product_attribute_color`.
- The meta key pattern: `get_term_meta( $term_id, 'product_attribute_color', true )` returns a hex string like `#ff0000`.
- This is a de facto standard used by WooCommerce itself and popular themes (Storefront, Flatsome, Woodmart).

### Admin Settings API
- WordPress Settings API: `register_setting()`, `add_settings_section()`, `add_settings_field()`.
- WooCommerce extends this with `woocommerce_get_settings_pages` filter or `woocommerce_settings_tabs_array` for dedicated tabs.
- For a single option (attribute selector), hooking into WooCommerce > Settings > Products via `woocommerce_get_settings_pages` is cleaner than a standalone menu.

### i18n Requirements
- WordPress plugin i18n: `load_plugin_textdomain()` hooked to `init` or `plugins_loaded`.
- All strings currently in Spanish — must keep Spanish as default but make translatable.
- `.pot` file generated via WP-CLI (`wp i18n make-pot`) or tools like Poedit.

## Approaches

### 1. Full OOP Refactor with Class-based Architecture (Recommended)

- **Description**: Wrap all functionality in a `Zira_Color_Filter` class with singleton pattern (`get_instance()`). Each concern separated into methods. Settings page via `woocommerce_get_settings_pages` filter. Assets extracted to `.css` file. Color swatches from term meta. Full i18n.
- **Pros**:
  - Clean separation of concerns (display, filtering, settings, assets)
  - Follows WordPress plugin conventions
  - Singleton prevents multiple instantiation
  - Easy to unit test (no global state pollution)
  - Proper autoloading path for future expansion
- **Cons**:
  - More lines of code than procedural version (~300-400 lines of PHP + ~80 lines of CSS)
  - Must maintain backwards compatibility for hook names
- **Effort**: Medium

### 2. Incremental Procedural Refactor

- **Description**: Keep procedural style but fix each issue independently — add settings function, extract CSS file, add swatches, add i18n. Functions remain global.
- **Pros**:
  - Smaller, incremental changes
  - Familiar to WordPress developers used to procedural plugins
- **Cons**:
  - Global namespace pollution persists
  - No autoloading or testability improvement
  - Harder to reason about state
  - Goes against modern WordPress plugin best practices
- **Effort**: Low-Medium

## Recommendation

**Approach 1: Full OOP Refactor**. The request explicitly requires OOP (Critical #2). Incremental procedural changes would not satisfy the requirement and would miss the long-term maintainability benefits. The plugin is small enough (140 lines) that a full rewrite into a class is low risk.

## Risks

1. **Backwards compatibility**: Existing installations using filters/actions on the old function names would break. Mitigation: document the change in plugin changelog. The old hook names (`zira_mostrar_filtro_colores`, `zira_filtrar_productos_por_color`, `zira_filtro_color_estilos`) were never public API — they are internal implementation details.

2. **WooCommerce version compatibility**: The `woocommerce_get_settings_pages` filter and `product_attribute_color` term meta key are stable since WooCommerce 3.x. Risk is low for stores running supported versions (WooCommerce 7.x+).

3. **Term meta absence**: If a store uses `pa_color` but hasn't configured colors via WooCommerce's attribute "Color" type, `product_attribute_color` meta won't exist. The swatch feature must gracefully degrade to text-only pills. Already documented as requirement #4 fallback.

4. **Plugin upgrade path**: Users who update from v1.0.0 to v2.0.0 will get no migration — the admin setting defaults to `pa_color`, preserving existing behavior. Risk is minimal.

## Ready for Proposal

Yes. All 7 requirements are well-understood and technically feasible. Proceed to sdd-propose with change name `plugin-refactor`.
