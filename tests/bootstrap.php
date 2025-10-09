<?php
/**
 * PHPUnit bootstrap file for Silver Assist Post Revalidate plugin tests.
 *
 * This bootstrap loads the WordPress test suite environment for real integration testing.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.0
 */

// Composer autoloader for dependencies.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Yoast PHPUnit Polyfills.
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// WordPress tests directory - can be configured via environment variable.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if WordPress test suite exists.
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "\n";
	echo "========================================\n";
	echo "WordPress Test Suite Not Found\n";
	echo "========================================\n";
	echo "Location checked: {$_tests_dir}\n\n";
	echo "To install WordPress test suite:\n\n";
	echo "1. Install WordPress test library:\n";
	echo "   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n\n";
	echo "2. Or set WP_TESTS_DIR environment variable:\n";
	echo "   export WP_TESTS_DIR=/path/to/wordpress-tests-lib\n\n";
	echo "3. Or in phpunit.xml:\n";
	echo "   <env name=\"WP_TESTS_DIR\" value=\"/path/to/wordpress-tests-lib\"/>\n\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 *
 * @return void
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/silver-assist-post-revalidate.php';
}

// Load the plugin before the WordPress test environment.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WordPress testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Define plugin constants for testing if not already defined.
if ( ! defined( 'SILVER_ASSIST_REVALIDATE_VERSION' ) ) {
	define( 'SILVER_ASSIST_REVALIDATE_VERSION', '1.2.0' );
}
if ( ! defined( 'SILVER_ASSIST_REVALIDATE_PLUGIN_DIR' ) ) {
	define( 'SILVER_ASSIST_REVALIDATE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

echo "\n";
echo "========================================\n";
echo "WordPress Test Environment Loaded\n";
echo "========================================\n";
echo "WordPress Version: " . get_bloginfo( 'version' ) . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Plugin Version: " . SILVER_ASSIST_REVALIDATE_VERSION . "\n";
echo "========================================\n\n";


// Create WP_Post stub class for testing.
if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stub WP_Post class for unit testing.
	 *
	 * This is a minimal implementation of WordPress WP_Post class
	 * to allow unit tests to run without full WordPress installation.
	 *
	 * @since 1.0.0
	 */
	class WP_Post {
		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * ID of post author.
		 *
		 * @var int
		 */
		public $post_author = 0;

		/**
		 * Post content.
		 *
		 * @var string
		 */
		public $post_content = '';

		/**
		 * Post title.
		 *
		 * @var string
		 */
		public $post_title = '';

		/**
		 * Post excerpt.
		 *
		 * @var string
		 */
		public $post_excerpt = '';

		/**
		 * Post status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Post name (slug).
		 *
		 * @var string
		 */
		public $post_name = '';

		/**
		 * Post modified date.
		 *
		 * @var string
		 */
		public $post_modified = '0000-00-00 00:00:00';

		/**
		 * Post date.
		 *
		 * @var string
		 */
		public $post_date = '0000-00-00 00:00:00';

		/**
		 * Constructor.
		 *
		 * @param object|array $post Post data.
		 */
		public function __construct( $post = null ) {
			if ( is_object( $post ) ) {
				foreach ( get_object_vars( $post ) as $key => $value ) {
					$this->$key = $value;
				}
			} elseif ( is_array( $post ) ) {
				foreach ( $post as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}
}

// Note: Brain\Monkey will handle WordPress function mocking in tests.
// This allows us to mock WordPress functions for unit testing without full WP install.
