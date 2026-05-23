<?php
/**
 * Integration tests for the full Zira color-filter flow.
 *
 * Requires a full WordPress + WooCommerce test environment
 * (`wp test env`).  If the environment is absent the bootstrap
 * script will exit with a clear message before these tests run.
 *
 * @package Zira_Filtro_Color
 */

require_once __DIR__ . '/../../zira-filtro-color.php';

/**
 * FilterFlowTest
 *
 * Exercises the complete filter pipeline: render, enqueue, query
 * modification, URL generation, swatch rendering, and the "Todos"
 * clear link.
 */
class FilterFlowTest extends \WP_UnitTestCase
{
    /**
     * Term IDs created during setUp for reuse across tests.
     *
     * @var int[]
     */
    private $term_ids = [];

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public function setUp(): void
    {
        parent::setUp();

        // Reset the singleton for a clean state.
        $this->reset_singleton();

        // Clean the option.
        delete_option('zira_color_filter_taxonomy');

        // Register a 'pa_color' taxonomy if not already present.
        if (! taxonomy_exists('pa_color')) {
            register_taxonomy('pa_color', 'product', [
                'label'  => 'Color',
                'public' => true,
            ]);
        }

        // Create test terms — one with color meta, one without.
        $this->term_ids['rojo'] = $this->factory->term->create([
            'taxonomy' => 'pa_color',
            'name'     => 'Rojo',
            'slug'     => 'rojo',
        ]);

        $this->term_ids['azul'] = $this->factory->term->create([
            'taxonomy' => 'pa_color',
            'name'     => 'Azul',
            'slug'     => 'azul',
        ]);

        // Set color meta on "Rojo" only.
        add_term_meta($this->term_ids['rojo'], 'product_attribute_color', '#ff0000');

        // Create at least one product so the shop page has content.
        $this->factory->post->create([
            'post_type'  => 'product',
            'post_title' => 'Test Product',
            'post_status'=> 'publish',
        ]);

        // Set the correct term for the product.
        wp_set_object_terms(
            get_the_ID() ?: $this->get_latest_product_id(),
            ['rojo'],
            'pa_color'
        );

        // Reset the singleton so it picks up the new term data.
        $this->reset_singleton();
    }

    public function tearDown(): void
    {
        delete_option('zira_color_filter_taxonomy');
        unset($_GET['filter_color']);
        $this->term_ids = [];
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Render guard tests
    // -----------------------------------------------------------------------

    /** @test */
    public function test_render_filter_on_shop_with_terms(): void
    {
        $this->go_to(get_post_type_archive_link('product'));

        $output = $this->capture_render();

        $this->assertStringContainsString(
            'zira-color-filter',
            $output,
            'Filter container must be rendered on shop page with terms.'
        );
        $this->assertStringContainsString('Filtrar por color:', $output);
    }

    /** @test */
    public function test_render_filter_skipped_when_no_terms(): void
    {
        // Switch to a taxonomy that has no terms.
        if (! taxonomy_exists('pa_empty')) {
            register_taxonomy('pa_empty', 'product', ['label' => 'Empty']);
        }
        update_option('zira_color_filter_taxonomy', 'pa_empty');
        $this->reset_singleton();

        $this->go_to(get_post_type_archive_link('product'));

        $output = $this->capture_render();
        $this->assertEmpty($output, 'No output when configured taxonomy has zero terms.');
    }

    /** @test */
    public function test_render_filter_skipped_on_non_shop(): void
    {
        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));

        $output = $this->capture_render();
        $this->assertEmpty($output, 'No output on non-shop / non-taxonomy pages.');
    }

    // -----------------------------------------------------------------------
    // Query modification
    // -----------------------------------------------------------------------

    /** @test */
    public function test_filter_modifies_product_query(): void
    {
        $_GET['filter_color'] = 'rojo';

        $query = new \WP_Query([
            'post_type' => 'product',
        ]);

        $filter = Zira_Color_Filter::get_instance();
        $filter->filter_products($query);

        $tax_query = $query->get('tax_query');
        $this->assertIsArray($tax_query);

        $clause = $tax_query[0] ?? null;
        $this->assertIsArray($clause);
        $this->assertSame('pa_color', $clause['taxonomy']);
        $this->assertSame('slug', $clause['field']);
        $this->assertContains('rojo', $clause['terms']);
    }

    // -----------------------------------------------------------------------
    // URL generation
    // -----------------------------------------------------------------------

    /** @test */
    public function test_filter_url_contains_color_param(): void
    {
        $this->go_to(get_post_type_archive_link('product'));

        $output = $this->capture_render();

        // Each term link (except "Todos") must carry ?filter_color=
        $this->assertStringContainsString('filter_color=rojo', $output);
        $this->assertStringContainsString('filter_color=azul', $output);
    }

    /** @test */
    public function test_todos_link_removes_color_param(): void
    {
        $_GET['filter_color'] = 'rojo';

        $this->go_to(add_query_arg('filter_color', 'rojo', get_post_type_archive_link('product')));

        $output = $this->capture_render();

        // The "Todos" link must NOT include filter_color.
        $link_pattern = '/<a[^>]*class="[^"]*zira-color-filter__item[^"]*active[^"]*"[^>]*href="([^"]*)"/';
        $this->assertDoesNotMatchRegularExpression(
            '#' . preg_quote('filter_color=', '#') . '#',
            $output,
            'Todos link must not contain filter_color query param.'
        );
        $this->assertStringContainsString('Todos', $output);
    }

    // -----------------------------------------------------------------------
    // Swatch rendering
    // -----------------------------------------------------------------------

    /** @test */
    public function test_swatch_rendered_when_color_meta_exists(): void
    {
        $this->go_to(get_post_type_archive_link('product'));

        $output = $this->capture_render();

        $this->assertStringContainsString(
            'zira-color-filter__swatch',
            $output,
            'Swatch span must be rendered for terms with color meta.'
        );
        $this->assertStringContainsString('background-color: #ff0000', $output);
    }

    /** @test */
    public function test_swatch_skipped_when_no_color_meta(): void
    {
        $this->go_to(get_post_type_archive_link('product'));

        $output = $this->capture_render();

        // "Azul" has no color meta → no swatch.
        // The text "Azul" must still appear as a simple pill.
        $this->assertStringContainsString('>Azul<', $output);

        // But the swatch for Azul must NOT appear.
        $this->assertStringNotContainsString(
            'azul',
            $this->extract_swatch_colors($output),
            'Azul has no color meta — its slug must not appear in any swatch style.'
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Capture the output of render_filter().
     *
     * @return string
     */
    private function capture_render(): string
    {
        ob_start();
        $filter = Zira_Color_Filter::get_instance();
        $filter->render_filter();
        return (string) ob_get_clean();
    }

    /**
     * Reset the Zira_Color_Filter singleton.
     */
    private function reset_singleton(): void
    {
        $ref  = new \ReflectionClass(Zira_Color_Filter::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Get the ID of the most recent product.
     *
     * @return int
     */
    private function get_latest_product_id(): int
    {
        $products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        return ! empty($products) ? (int) $products[0] : 0;
    }

    /**
     * Extract hex color values from swatch inline styles in the output.
     *
     * @param string $output HTML output.
     * @return string Concatenated styles for assertion.
     */
    private function extract_swatch_colors(string $output): string
    {
        $matches = [];
        preg_match_all(
            '/background-color:\s*([^;"]+)/',
            $output,
            $matches
        );
        return implode(' ', $matches[1] ?? []);
    }
}
