<?php
/**
 * PHPUnit bootstrap for Zira Filtro por Color.
 *
 * Requires a WordPress test environment.
 * Set WP_TESTS_DIR to the WordPress tests library path.
 *
 * @see https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (! $_tests_dir) {
    fwrite(
        STDERR,
        "\nError: WordPress test environment not configured.\n\n"
        . "Set the WP_TESTS_DIR environment variable to the path of your\n"
        . "WordPress tests library before running the test suite.\n\n"
        . "Example:\n"
        . "  export WP_TESTS_DIR=/tmp/wordpress-tests-lib\n"
        . "  vendor/bin/phpunit\n\n"
        . "Unit tests can still run without this variable if you mock\n"
        . "WordPress functions in your test classes.\n\n"
    );
    exit(1);
}

// Load the WordPress test framework bootstrap.
require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';
