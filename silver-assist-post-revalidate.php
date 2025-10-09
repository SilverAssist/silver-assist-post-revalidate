<?php
/**
 * Plugin Name: Silver Assist Post Revalidate
 * Plugin URI: https://github.com/SilverAssist/silver-assist-post-revalidate
 * Description: Automatically revalidates posts and categories when content changes, sending requests to a configured endpoint for cache invalidation.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Author: Silver Assist
 * Author URI: http://silverassist.com/
 * License: Polyform Noncommercial 1.0.0
 * License URI: https://polyformproject.org/licenses/noncommercial/1.0.0
 * Text Domain: silver-assist-revalidate-posts
 * Domain Path: /languages
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.1.0
 * @license Polyform Noncommercial 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'SILVER_ASSIST_REVALIDATE_VERSION', '1.1.0' );
define( 'SILVER_ASSIST_REVALIDATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Load composer autoloader.
if ( file_exists( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		// Initialize main plugin class.
		\RevalidatePosts\Plugin::instance();
	}
);

// Activation hook.
register_activation_hook(
	__FILE__,
	function () {
		// Set default options on activation.
		if ( ! get_option( 'revalidate_endpoint' ) ) {
			add_option( 'revalidate_endpoint', '' );
		}
		if ( ! get_option( 'revalidate_token' ) ) {
			add_option( 'revalidate_token', '' );
		}
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function () {
		// Cleanup if needed (currently nothing to clean up).
	}
);
