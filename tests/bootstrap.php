<?php
/**
 * PHPUnit bootstrap file for Silver Assist Post Revalidate plugin tests.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 */

// Load Composer autoloader (includes WordPress stubs).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Yoast PHPUnit Polyfills.
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// DO NOT load WordPress stubs here - Brain\Monkey needs to define functions first.
// require_once dirname( __DIR__ ) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';

// Define basic WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Set test environment constants.
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	define( 'WP_ENVIRONMENT_TYPE', 'test' );
}

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
