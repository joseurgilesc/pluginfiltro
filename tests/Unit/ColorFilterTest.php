<?php
/**
 * Unit tests for Zira_Color_Filter core class.
 *
 * Tests the singleton pattern, taxonomy resolution, conditional guards,
 * term meta retrieval, query modification, and query-param sanitization.
 *
 * @package Zira_Filtro_Color
 */

/**
 * Require the plugin file so the class and constants are defined.
 * `require_once` is safe — the file guards its own definitions with
 * class_exists / defined checks.
 */
require_once __DIR__ . '/../../zira-filtro-color.php';

/**
 * ColorFilterTest
 *
 * Extends WP_UnitTestCase for access to WordPress factories and
 * built-in WordPress functions.  Private methods are reached via
 * Reflection when a public caller would add too much noise.
 */
class ColorFilterTest extends \WP_UnitTestCase
{
    /**
     * Helper for resetting the singleton between tests.
     */
    private static function reset_singleton(): void
    {
        $ref  = new \ReflectionClass(Zira_Color_Filter::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Helper to make a private/protected method callable.
     *
     * @param string $name Method name.
     * @return \ReflectionMethod
     */
    private function get_private_method(string $name): \ReflectionMethod
    {
        $ref    = new \ReflectionClass(Zira_Color_Filter::class);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public function setUp(): void
    {
        parent::setUp();

        // Reset singleton so each test gets a fresh instance.
        self::reset_singleton();

        // Clean the saved-option state.
        delete_option('zira_color_filter_taxonomy');
    }

    public function tearDown(): void
    {
        delete_option('zira_color_filter_taxonomy');
        unset($_GET['filter_color']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Singleton
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_instance_returns_same_instance(): void
    {
        $a = Zira_Color_Filter::get_instance();
        $b = Zira_Color_Filter::get_instance();

        $this->assertInstanceOf(Zira_Color_Filter::class, $a);
        $this->assertSame($a, $b, 'get_instance() must return the identical object.');
    }

    // -----------------------------------------------------------------------
    // Taxonomy resolution (public method — tested directly)
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_taxonomy_returns_default_when_no_option(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $this->assertSame('pa_color', $filter->get_taxonomy());
    }

    /** @test */
    public function test_get_taxonomy_returns_saved_option(): void
    {
        update_option('zira_color_filter_taxonomy', 'pa_talle');
        $filter = Zira_Color_Filter::get_instance();

        $this->assertSame('pa_talle', $filter->get_taxonomy());
    }

    // -----------------------------------------------------------------------
    // should_display() — private, tested via Reflection
    // -----------------------------------------------------------------------

    /** @test */
    public function test_should_display_returns_false_on_non_shop(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $method = $this->get_private_method('should_display');

        // Visit a single post — neither shop nor product taxonomy.
        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));

        $this->assertFalse($method->invoke($filter));
    }

    // -----------------------------------------------------------------------
    // should_load_assets() — private, tested via Reflection
    // -----------------------------------------------------------------------

    /** @test */
    public function test_should_load_assets_returns_false_when_no_terms(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $method = $this->get_private_method('should_load_assets');

        // We are on the shop page but pa_color has zero terms → false.
        $this->go_to(get_post_type_archive_link('product'));

        $this->assertFalse($method->invoke($filter));
    }

    // -----------------------------------------------------------------------
    // get_color_meta() — private, tested via Reflection
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_color_meta_returns_hex(): void
    {
        $filter  = Zira_Color_Filter::get_instance();
        $method  = $this->get_private_method('get_color_meta');
        $term_id = $this->factory->term->create(['taxonomy' => 'pa_color', 'name' => 'Rojo']);

        add_term_meta($term_id, 'product_attribute_color', '#ff0000');

        $this->assertSame('#ff0000', $method->invoke($filter, $term_id));
    }

    /** @test */
    public function test_get_color_meta_returns_null_when_empty(): void
    {
        $filter  = Zira_Color_Filter::get_instance();
        $method  = $this->get_private_method('get_color_meta');
        $term_id = $this->factory->term->create(['taxonomy' => 'pa_color', 'name' => 'Azul']);
        // No term meta set.

        $this->assertNull($method->invoke($filter, $term_id));
    }

    // -----------------------------------------------------------------------
    // filter_products() — public, tested with a real WP_Query
    // -----------------------------------------------------------------------

    /** @test */
    public function test_filter_products_adds_tax_query(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $_GET['filter_color'] = 'rojo';

        $query = $this->getMockBuilder(\WP_Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['is_main_query', 'get', 'set'])
            ->getMock();

        $query->method('is_main_query')->willReturn(true);

        // Wire get/set on a real array so the mock behaves sensibly.
        $internal = [];
        $query->method('get')
            ->willReturnCallback(function ($key, $default = null) use (&$internal) {
                return $internal[$key] ?? $default;
            });
        $query->method('set')
            ->willReturnCallback(function ($key, $value) use (&$internal) {
                $internal[$key] = $value;
            });

        $filter->filter_products($query);

        $tax_query = $internal['tax_query'] ?? null;
        $this->assertIsArray($tax_query, 'tax_query must be an array after filter_products.');

        $clause = $tax_query[0] ?? null;
        $this->assertIsArray($clause);
        $this->assertSame('pa_color', $clause['taxonomy']);
        $this->assertSame('slug', $clause['field']);
        $this->assertContains('rojo', $clause['terms']);
    }

    /** @test */
    public function test_filter_products_ignores_when_filter_color_empty(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        // No $_GET['filter_color'] set.

        $query = $this->getMockBuilder(\WP_Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['is_main_query', 'get', 'set'])
            ->getMock();

        $query->method('is_main_query')->willReturn(true);

        $internal = [];
        $query->method('get')
            ->willReturnCallback(function ($key, $default = null) use (&$internal) {
                return $internal[$key] ?? $default;
            });
        $query->method('set')
            ->willReturnCallback(function ($key, $value) use (&$internal) {
                $internal[$key] = $value;
            });

        $filter->filter_products($query);

        $this->assertArrayNotHasKey('tax_query', $internal, 'No tax_query clause when filter_color is empty.');
    }

    // -----------------------------------------------------------------------
    // get_current_color() — private, tested via Reflection
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_current_color_returns_sanitized_value(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $method = $this->get_private_method('get_current_color');

        $_GET['filter_color'] = ' Verde Claro ';

        $result = $method->invoke($filter);

        // sanitize_text_field strips extra whitespace and tags.
        $this->assertSame('Verde Claro', $result);
    }

    /** @test */
    public function test_get_current_color_returns_empty_when_no_param(): void
    {
        $filter = Zira_Color_Filter::get_instance();
        $method = $this->get_private_method('get_current_color');

        unset($_GET['filter_color']);

        $this->assertSame('', $method->invoke($filter));
    }
}
