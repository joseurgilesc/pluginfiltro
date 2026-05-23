<?php
/**
 * Unit tests for Zira_Color_Filter_Settings admin page.
 *
 * Tests settings registration, default taxonomy value, field rendering
 * with and without attributes, and option persistence.
 *
 * @package Zira_Filtro_Color
 */

/**
 * The plugin file must be loaded so the settings class is available.
 * The file guards its own definitions — safe to require after the
 * ColorFilterTest or independently.
 */
require_once __DIR__ . '/../../zira-filtro-color.php';

/**
 * SettingsTest
 *
 * Tests the WooCommerce settings tab integration.  When WC is
 * not loaded, the settings class is not defined and these tests
 * will be skipped.
 */
class SettingsTest extends \WP_UnitTestCase
{
    /**
     * Version constant is defined by the plugin entry-point.
     *
     * @var string
     */
    private const PLUGIN_VERSION = '1.0.0';

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public function setUp(): void
    {
        parent::setUp();

        if (! class_exists('Zira_Color_Filter_Settings')) {
            $this->markTestSkipped(
                'Zira_Color_Filter_Settings requires WooCommerce to be loaded.'
            );
        }
    }

    public function tearDown(): void
    {
        delete_option('zira_color_filter_taxonomy');
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    /** @test */
    public function test_settings_page_is_registered(): void
    {
        $pages = apply_filters('woocommerce_get_settings_pages', []);

        $found = false;
        foreach ($pages as $page) {
            if ($page instanceof Zira_Color_Filter_Settings) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Zira_Color_Filter_Settings must appear in woocommerce_get_settings_pages.'
        );
    }

    /** @test */
    public function test_default_taxonomy_is_pa_color(): void
    {
        $settings = new Zira_Color_Filter_Settings();
        $fields   = $settings->get_settings();

        $select = null;
        foreach ($fields as $field) {
            if (($field['id'] ?? '') === 'zira_color_filter_taxonomy') {
                $select = $field;
                break;
            }
        }

        $this->assertNotNull($select, 'zira_color_filter_taxonomy field must exist.');
        $this->assertSame('pa_color', $select['default']);
    }

    // -----------------------------------------------------------------------
    // Fields rendering
    // -----------------------------------------------------------------------

    /** @test */
    public function test_settings_returns_fields_with_attributes(): void
    {
        // Create a real product attribute so wc_get_attribute_taxonomies()
        // returns at least one entry.
        $this->create_test_attribute('Color', 'color');

        $settings = new Zira_Color_Filter_Settings();
        $fields   = $settings->get_settings();

        // The select field must be present when attributes exist.
        $select = $this->find_field($fields, 'zira_color_filter_taxonomy');
        $this->assertNotNull($select, 'Select field must exist when attributes are available.');
        $this->assertSame('select', $select['type']);
        $this->assertArrayHasKey('pa_color', $select['options']);
    }

    /** @test */
    public function test_settings_handles_no_attributes(): void
    {
        // Delete ALL product attributes so none exist.
        $this->delete_all_attributes();

        $settings = new Zira_Color_Filter_Settings();
        $fields   = $settings->get_settings();

        // The info field must appear; the select field must NOT.
        $info   = $this->find_field_by_type($fields, 'info');
        $select = $this->find_field($fields, 'zira_color_filter_taxonomy');

        $this->assertNotNull($info, 'Info message must appear when no attributes exist.');
        $this->assertNull($select, 'Select field must NOT appear when no attributes exist.');
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    /** @test */
    public function test_taxonomy_option_is_saved(): void
    {
        // Simulate the option being saved through the settings API.
        update_option('zira_color_filter_taxonomy', 'pa_talle');

        $this->assertSame(
            'pa_talle',
            get_option('zira_color_filter_taxonomy'),
            'Custom taxonomy option must persist.'
        );

        // Also confirm the main plugin class picks it up.
        // The singleton may have been created before this test — re-init.
        $filter = Zira_Color_Filter::get_instance();
        $this->assertSame('pa_talle', $filter->get_taxonomy());

        // Clean up.
        delete_option('zira_color_filter_taxonomy');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Find a settings field by its 'id' key.
     *
     * @param array[] $fields Settings array.
     * @param string  $id     Field id to search for.
     * @return array|null
     */
    private function find_field(array $fields, string $id): ?array
    {
        foreach ($fields as $field) {
            if (($field['id'] ?? '') === $id) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Find a settings field by its 'type' key.
     *
     * @param array[] $fields Settings array.
     * @param string  $type   Field type to search for.
     * @return array|null
     */
    private function find_field_by_type(array $fields, string $type): ?array
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === $type) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Register a test attribute taxonomy.
     *
     * @param string $label Human-readable label.
     * @param string $name  Machine name (without 'pa_' prefix).
     */
    private function create_test_attribute(string $label, string $name): void
    {
        if (! function_exists('wc_create_attribute')) {
            $this->markTestSkipped('WooCommerce functions not available for attribute creation.');
        }

        $id = wc_create_attribute([
            'name'         => $label,
            'slug'         => $name,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ]);

        if (is_wp_error($id)) {
            $this->markTestSkipped('Could not create test attribute: ' . $id->get_error_message());
        }

        // Register the taxonomy so it is queryable.
        register_taxonomy('pa_' . $name, 'product', [
            'label' => $label,
        ]);
    }

    /**
     * Remove all product attribute taxonomies.
     */
    private function delete_all_attributes(): void
    {
        if (! function_exists('wc_get_attribute_taxonomies')) {
            return;
        }

        $taxonomies = wc_get_attribute_taxonomies();
        if (empty($taxonomies)) {
            return;
        }

        foreach ($taxonomies as $tax) {
            if (function_exists('wc_delete_attribute')) {
                wc_delete_attribute($tax->attribute_id);
            }
        }
    }
}
