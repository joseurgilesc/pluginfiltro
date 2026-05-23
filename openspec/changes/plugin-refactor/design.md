# Design: Plugin Refactor (OOP + 8 Improvements)

## Technical Approach

Full OOP rewrite of the 140-line procedural plugin into a `Zira_Color_Filter` singleton class. Each concern maps to a dedicated method. Admin settings inject via `woocommerce_get_settings_pages`. CSS extracted to `assets/css/zira-color-filter.css` with conditional enqueuing. Color swatches render from `product_attribute_color` term meta with text-only fallback. i18n uses `zira-filtro-color` textdomain with Spanish source strings. Test infrastructure via PHPUnit with unit (mock-based) and integration (WP-dependent) suites.

## Architecture Decisions

| Decision | Choice | Rejected | Rationale |
|----------|--------|----------|-----------|
| **Class pattern** | Singleton (`get_instance()`) | Static class, DI container | WordPress standard; prevents re-instantiation; private `__clone`/`__wakeup` for safety |
| **Namespace** | None (root) | `Zira\ColorFilter` | WordPress plugin convention; no autoloader config needed for single-class plugin |
| **Settings hook** | `woocommerce_get_settings_pages` | `admin_menu` standalone page | Co-locates with WooCommerce product settings; single dropdown field fits naturally |
| **Swatch meta key** | `product_attribute_color` | Custom `_zira_color` key | De facto WooCommerce standard for `pa_*` color attributes; zero-config for existing stores |
| **Asset version** | `ZIRA_COLOR_FILTER_VERSION` constant | `filemtime()` | Deterministic; cache-busts on plugin update; avoids filesystem calls per request |
| **i18n source** | Spanish strings as source | English as source | Plugin is Spanish-first; matches existing convention; `.pot` generated for translators |
| **Testing isolation** | Unit tests mock WP functions | Full WP bootstrap for all tests | Unit tests run without DB; integration tests require `wp test env`; documented in bootstrap |

## Data Flow

```
Browser (shop page)
  │
  ├── GET /shop?filter_color=rojo
  │
  ▼
WordPress
  │
  ├── plugins_loaded → load_textdomain()
  ├── wp_enqueue_scripts → enqueue_assets()
  │     └── should_load_assets()
  │           ├── is_shop() || is_product_taxonomy()?  → no: return
  │           └── configured taxonomy has terms?        → no: return
  │           └── wp_enqueue_style('zira-color-filter', ...)  → <link>
  │
  ├── woocommerce_before_shop_loop → render_filter()
  │     └── should_display()                     → no: return
  │     └── get_terms({taxonomy})                → term list
  │     └── get_current_color()                  → $_GET['filter_color']
  │     └── foreach term:
  │           └── get_color_meta(term_id)         → hex || null
  │           └── render pill + optional swatch
  │
  └── woocommerce_product_query → filter_products($query)
        └── is_admin? || !$query->is_main_query()?  → return
        └── empty $_GET['filter_color']?             → return
        └── append tax_query {taxonomy, slug}        → filtered results
```

## Class Diagram

```
Zira_Color_Filter
├── private static ?Zira_Color_Filter $instance = null
├── private string $taxonomy           ← get_option('zira_color_filter_taxonomy', 'pa_color')
├── public static get_instance(): self
├── public init(): void                ← called from constructor
│   └── $this->setup_hooks()
├── private setup_hooks(): void
│   ├── add_action('plugins_loaded', [$this, 'load_textdomain'])
│   ├── add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'])
│   ├── add_action('woocommerce_before_shop_loop', [$this, 'render_filter'], 15)
│   ├── add_action('woocommerce_product_query', [$this, 'filter_products'])
│   └── add_filter('woocommerce_get_settings_pages', [$this, 'add_settings_page'])
├── public load_textdomain(): void
├── public enqueue_assets(): void       ← wp_enqueue_style with conditional guard
├── public render_filter(): void        ← HTML output with swatches
├── public filter_products(WP_Query): void  ← tax_query injection
├── public add_settings_page(array): array  ← WooCommerce settings extension
├── private get_taxonomy(): string      ← configured || 'pa_color'
├── private get_terms(): array          ← get_terms() with hide_empty
├── private get_current_color(): string ← from $_GET, sanitized
├── private get_color_meta(int): ?string ← get_term_meta('product_attribute_color')
├── private should_display(): bool      ← page type + taxonomy exists + has terms
└── private should_load_assets(): bool  ← shop/archive page + taxonomy has terms
```

## Settings Page Design

WooCommerce injects settings via `woocommerce_get_settings_pages`. A custom `WC_Settings_Page`-compatible class adds:

- **Section**: "Color Filter" under Products tab
- **Field**: `<select>` dropdown via `woocommerce_admin_field_select`
- **Options source**: `wc_get_attribute_taxonomies()` → label/value pairs
- **Option key**: `zira_color_filter_taxonomy` → `get_option('zira_color_filter_taxonomy', 'pa_color')`
- **Sanitization**: validate taxonomy exists via `taxonomy_exists()` before save

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `zira-filtro-color.php` | Rewrite | Single class `Zira_Color_Filter` + bootstrap `Zira_Color_Filter::get_instance()` |
| `assets/css/zira-color-filter.css` | Create | Extracted styles inline CSS (80 lines) + swatch rules |
| `languages/zira-filtro-color.pot` | Create | i18n template for `zira-filtro-color` textdomain |
| `composer.json` | Create | Dev deps: phpunit ^9.0, wp-phpunit ^6.0, wp-test-utils ^1.0 |
| `phpunit.xml.dist` | Create | PHPUnit config: unit + integration suites, bootstrap |
| `tests/bootstrap.php` | Create | WordPress test framework bootstrap |
| `tests/Unit/ColorFilterTest.php` | Create | Unit tests: query filter, term retrieval, meta read, CSS conditions |
| `tests/Unit/SettingsTest.php` | Create | Unit tests: settings registration, taxonomy validation |
| `tests/Integration/FilterFlowTest.php` | Create | Integration: full filter flow with WP_Query, i18n, settings page |

## Testing Strategy

| Layer | Coverage | Approach |
|-------|----------|----------|
| Unit | `filter_products()`, `get_terms()`, `get_color_meta()`, `should_load_assets()` | Mock `WP_Query`, `get_terms`, `get_term_meta`, `is_shop`; assert tax_query modifications, return values, early exits |
| Unit | Settings page registration, taxonomy dropdown | Mock `wc_get_attribute_taxonomies`, `get_option`; assert filter hook registration, default values |
| Integration | Full filter flow | Real WP env required; test render + filter + asset load on shop pages; assert HTML structure, query modification |
| Integration | i18n loading, admin settings | Verify textdomain loads, settings page renders in WooCommerce admin |

Unit tests run without database (`vendor/bin/phpunit --testsuite unit`). Integration tests require `wp test env` setup (documented in `tests/bootstrap.php`). `composer test` runs both suites when the environment is available.

## CSS Swatch Design

```css
.zira-color-filter__swatch {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
    border: 1px solid rgba(0,0,0,0.1);
}
```

Rendered inline via PHP: `style="background-color: #{$hex}"` on a `<span>` inside `.zira-color-filter__item`. Falls back to text-only pill when `get_color_meta()` returns null/empty. The "Todos" reset link never renders a swatch.

## Backward Compatibility

- Query param `filter_color` unchanged
- CSS classes `.zira-color-filter`, `.zira-color-filter__item`, `.zira-color-filter__items` preserved
- HTML structure preserved (`.zira-color-filter > strong + .zira-color-filter__items > a.zira-color-filter__item`)
- Plugin slug/file name unchanged → activation survives update
- Default taxonomy `pa_color` preserved via option fallback

## Migration / Rollout

No migration required. On upgrade from v1.0.0, the admin setting defaults to `pa_color` (existing behavior). Rollback: deactivate, restore previous `zira-filtro-color.php`, clear caches. No database changes beyond a single `zira_color_filter_taxonomy` option.

## Open Questions

None.
