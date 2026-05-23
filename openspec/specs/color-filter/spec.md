# Color Filter — Main Spec

## Overview
A WooCommerce product filter that displays product attribute terms as clickable pill links on shop and taxonomy archive pages. Filters products by matching term slug via `woocommerce_product_query`.

## Current Behavior

### Display
- Hook: `woocommerce_before_shop_loop` (priority 15)
- Targets: `is_shop()` and `is_product_taxonomy()` pages only
- Hardcoded taxonomy: `pa_color`
- Renders pill-style filter links in a `.zira-color-filter` container
- Active filter highlighted with `.active` class
- "Todos" link to reset filter

### Filtering
- Hook: `woocommerce_product_query`
- Reads `$_GET['filter_color']` parameter
- Appends `tax_query` to main query with `pa_color` taxonomy
- Exits early if admin, not main query, or no filter param

### Styling
- Hook: `wp_enqueue_scripts`
- Conditional loading: only on `is_shop()` and `is_product_taxonomy()`
- CSS injected inline via `wp_add_inline_style()` on a dummy `wp_register_style( 'zira-filtro-color', false )`
- Uses pill/badge design with rounded corners, hover/active dark background

### Missing
- No admin settings or configuration UI
- No i18n / textdomain support
- No color swatches (text-only pills)
- No separate CSS file (inline only — no browser caching)
- No OOP structure — global functions in global namespace
- No loading optimization beyond page-type check
