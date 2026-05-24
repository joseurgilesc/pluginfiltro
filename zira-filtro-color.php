<?php
/**
 * Plugin Name: Zira Filtro por Color
 * Description: Agrega un filtro por colores en la tienda WooCommerce usando el atributo pa_color.
 * Version: 1.0.0
 * Author: Zira
 * Text Domain: zira-filtro-color
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

/**
 * Plugin version constant. Used for asset cache-busting.
 */
define('ZIRA_COLOR_FILTER_VERSION', '1.0.0');

if (! class_exists('Zira_Color_Filter')):

class Zira_Color_Filter
{
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Configured taxonomy. Defaults to pa_color.
     *
     * @var string
     */
    private $taxonomy = 'pa_color';

    /**
     * Private constructor. Call get_instance() instead.
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Block cloning of the singleton.
     */
    private function __clone()
    {
    }

    /**
     * Block unserialization of the singleton.
     */
    private function __wakeup()
    {
    }

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin: resolve taxonomy and register hooks.
     */
    public function init(): void
    {
        $this->taxonomy = $this->get_taxonomy();
        $this->setup_hooks();
    }

    /**
     * Register all WordPress hooks.
     */
    private function setup_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_before_shop_loop', [$this, 'render_filter'], 15);
        add_action('woocommerce_product_query', [$this, 'filter_products']);
        add_filter('woocommerce_get_settings_pages', [$this, 'add_settings_page']);
    }

    /**
     * Determine if the filter should be displayed.
     *
     * Returns true on shop/product-taxonomy pages only when the configured
     * taxonomy exists and has non-empty terms.
     *
     * @return bool
     */
    private function should_display(): bool
    {
        if (! is_shop() && ! is_product_taxonomy()) {
            return false;
        }

        if (! taxonomy_exists($this->get_taxonomy())) {
            return false;
        }

        $terms = get_terms([
            'taxonomy'   => $this->get_taxonomy(),
            'hide_empty' => true,
            'number'     => 1,
            'fields'     => 'ids',
        ]);

        return ! empty($terms) && ! is_wp_error($terms);
    }

    /**
     * Determine if assets should be enqueued on the current page.
     *
     * Assets load only on shop/product-taxonomy pages where the configured
     * taxonomy has non-empty terms.  Falls back gracefully if the taxonomy
     * does not exist.
     *
     * @return bool
     */
    private function should_load_assets(): bool
    {
        if (! is_shop() && ! is_product_taxonomy()) {
            return false;
        }

        if (! taxonomy_exists($this->get_taxonomy())) {
            return false;
        }

        $terms = get_terms([
            'taxonomy'   => $this->get_taxonomy(),
            'hide_empty' => true,
            'number'     => 1,
            'fields'     => 'ids',
        ]);

        return ! empty($terms) && ! is_wp_error($terms);
    }

    /**
     * Sanitize the current color filter query parameter.
     *
     * @return string Empty string when no filter is active.
     */
    private function get_current_color(): string
    {
        return isset($_GET['filter_color'])
            ? sanitize_text_field(wp_unslash($_GET['filter_color']))
            : '';
    }

    /**
     * Retrieve the color meta value for a given term.
     *
     * @param int $term_id
     * @return string|null Hex color string, or null if no color is set.
     */
    private function get_color_meta(int $term_id): ?string
    {
        $color = get_term_meta($term_id, 'product_attribute_color', true);
        return ! empty($color) ? $color : null;
    }

    /**
     * Load the plugin textdomain for i18n.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'zira-filtro-color',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Enqueue the plugin stylesheet on qualified pages.
     *
     * CSS is loaded from an external file (browser-cacheable) only when
     * the configured taxonomy has non-empty terms on shop/taxonomy pages.
     */
    public function enqueue_assets(): void
    {
        if (! $this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'zira-color-filter',
            plugin_dir_url(__FILE__) . 'assets/css/zira-color-filter.css',
            [],
            ZIRA_COLOR_FILTER_VERSION
        );
    }

    /**
     * Render the color filter pills above the shop loop.
     *
     * Produces backward-compatible HTML structure.  Terms with color meta
     * receive a swatch circle; text-only fallback for terms without.
     * The "Todos" reset pill never receives a swatch.
     */
    public function render_filter(): void
    {
        if (! $this->should_display()) {
            return;
        }

        $taxonomy = $this->get_taxonomy();
        $terms    = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            return;
        }

        $current_color = $this->get_current_color();
        $excluded      = $this->get_excluded_terms();
        $base_url      = remove_query_arg('filter_color');

        echo '<div class="zira-color-filter">';
        echo '<strong>' . __('Filtrar por color:', 'zira-filtro-color') . '</strong>';
        echo '<div class="zira-color-filter__items">';

        // "Todos" — reset / show all
        echo '<a class="zira-color-filter__item '
            . esc_attr(empty($current_color) ? 'active' : '')
            . '" href="' . esc_url($base_url) . '">'
            . __('Todos', 'zira-filtro-color') . '</a>';

        foreach ($terms as $term) {
            if (in_array($term->slug, $excluded, true)) {
                continue;
            }

            $color_meta   = $this->get_color_meta($term->term_id);
            $url          = add_query_arg('filter_color', $term->slug, $base_url);
            $active_class = $current_color === $term->slug ? 'active' : '';

            echo '<a class="zira-color-filter__item '
                . esc_attr($active_class)
                . '" href="' . esc_url($url) . '">';

            if ($color_meta !== null) {
                echo '<span class="zira-color-filter__swatch" style="background-color: '
                    . esc_attr($color_meta) . '"></span>';
            }

            echo esc_html($term->name) . '</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Filter the WooCommerce product query by the selected color term.
     *
     * @param WP_Query $query
     */
    public function filter_products($query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (empty($_GET['filter_color'])) {
            return;
        }

        $color = sanitize_text_field(wp_unslash($_GET['filter_color']));

        $tax_query   = (array) $query->get('tax_query');
        $tax_query[] = [
            'taxonomy' => $this->get_taxonomy(),
            'field'    => 'slug',
            'terms'    => [$color],
            'operator' => 'IN',
        ];

        $query->set('tax_query', $tax_query);
    }

    /**
     * Register the WooCommerce settings tab.
     *
     * Injects a WC_Settings_Page subclass that provides a dropdown for
     * selecting the product attribute taxonomy.  Appears as a top-level
     * "Color Filter" tab under WooCommerce > Settings.
     *
     * @param array $pages
     * @return array
     */
    public function add_settings_page(array $pages): array
    {
        if (class_exists('Zira_Color_Filter_Settings')) {
            $pages[] = new Zira_Color_Filter_Settings();
        }
        return $pages;
    }

    /**
     * Get the configured taxonomy with pa_color fallback.
     *
     * @return string
     */
    public function get_taxonomy(): string
    {
        return get_option('zira_color_filter_taxonomy', 'pa_color');
    }

    /**
     * Get the list of term slugs excluded from the frontend filter.
     *
     * @return array
     */
    public function get_excluded_terms(): array
    {
        $excluded = get_option('zira_color_filter_excluded_terms', []);
        return is_array($excluded) ? $excluded : [];
    }
}

endif;

// ---------------------------------------------------------------------------
// Admin Settings Page
// ---------------------------------------------------------------------------

if (! class_exists('Zira_Color_Filter_Settings') && class_exists('WC_Settings_Page')):

class Zira_Color_Filter_Settings extends \WC_Settings_Page
{
    /**
     * Constructor — registers the settings tab.
     */
    public function __construct()
    {
        $this->id    = 'zira_color_filter';
        $this->label = __('Color Filter', 'zira-filtro-color');
        parent::__construct();
    }

    /**
     * No sub-sections for this tab.
     *
     * @return array
     */
    public function get_sections(): array
    {
        return [];
    }

    /**
     * Build the settings fields.
     *
     * Returns a single select dropdown populated with available product
     * attribute taxonomies.  Gracefully shows an info message when none exist.
     *
     * @param string $current_section
     * @return array
     */
    public function get_settings($current_section = ''): array
    {
        $taxonomies = wc_get_attribute_taxonomies();
        $options    = [];

        if (! empty($taxonomies)) {
            foreach ($taxonomies as $tax) {
                $options['pa_' . $tax->attribute_name] = $tax->attribute_label;
            }
        }

        $settings = [
            [
                'title' => __('Color Filter Settings', 'zira-filtro-color'),
                'type'  => 'title',
                'desc'  => __(
                    'Select the product attribute taxonomy to use for the color filter.',
                    'zira-filtro-color'
                ),
                'id' => 'zira_color_filter_options',
            ],
        ];

        if (empty($options)) {
            $settings[] = [
                'type' => 'info',
                'text' => __(
                    'No product attributes found. Create at least one attribute under Products > Attributes.',
                    'zira-filtro-color'
                ),
            ];
        } else {
            $settings[] = [
                'title'   => __('Filter Taxonomy', 'zira-filtro-color'),
                'desc'    => __(
                    'Choose which attribute taxonomy to use for filtering products.',
                    'zira-filtro-color'
                ),
                'id'      => 'zira_color_filter_taxonomy',
                'default' => 'pa_color',
                'type'    => 'select',
                'options' => $options,
            ];

            // Excluded terms — multiselect populated from the saved taxonomy.
            $saved_taxonomy = get_option('zira_color_filter_taxonomy', 'pa_color');
            $term_options   = [];
            if (taxonomy_exists($saved_taxonomy)) {
                $all_terms = get_terms([
                    'taxonomy'   => $saved_taxonomy,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ]);
                if (! empty($all_terms) && ! is_wp_error($all_terms)) {
                    foreach ($all_terms as $t) {
                        $term_options[$t->slug] = $t->name;
                    }
                }
            }

            if (! empty($term_options)) {
                $settings[] = [
                    'title'   => __('Excluded Terms', 'zira-filtro-color'),
                    'desc'    => __(
                        'Select terms to hide from the frontend color filter.',
                        'zira-filtro-color'
                    ),
                    'id'      => 'zira_color_filter_excluded_terms',
                    'default' => [],
                    'type'    => 'multiselect',
                    'options' => $term_options,
                ];
            }
        }

        $settings[] = [
            'type' => 'sectionend',
            'id'   => 'zira_color_filter_options',
        ];

        return $settings;
    }
}

endif;

// Bootstrap — instantiate the singleton.
Zira_Color_Filter::get_instance();
