<?php
/**
 * Configuration Class
 *
 * Manages plugin configuration including enabled post types.
 * This class provides a centralized way to check which post types
 * have revalidation functionality enabled.
 *
 * @package RevalidatePosts
 * @since 1.4.0
 * @version 1.4.0
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

defined( 'ABSPATH' ) || exit;

use WP_Post_Type;

/**
 * Configuration management class
 *
 * Implements singleton pattern to ensure only one instance exists.
 * Provides methods to check enabled post types and retrieve post type information.
 *
 * @since 1.4.0
 */
class Configuration {

	/**
	 * Singleton instance
	 *
	 * @since 1.4.0
	 * @var Configuration|null
	 */
	private static ?Configuration $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.4.0
	 * @return Configuration The single instance of the Configuration class
	 */
	public static function instance(): Configuration {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Configuration constructor
	 *
	 * Private constructor to enforce singleton pattern.
	 *
	 * @since 1.4.0
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Get enabled post types
	 *
	 * Returns an array of post type slugs that have revalidation enabled.
	 * Currently hardcoded to 'post' only. In the future, this will read from
	 * WordPress options to allow configuration via settings page.
	 *
	 * @since 1.4.0
	 * @todo Add get_option() call when settings UI is implemented
	 * @return string[] Array of enabled post type slugs
	 */
	public function get_enabled_post_types(): array {
		// @todo Future: return get_option('silver_assist_revalidate_post_types', ['post']);
		return [ 'post' ];
	}

	/**
	 * Check if post type is enabled
	 *
	 * Determines whether a specific post type has revalidation functionality enabled.
	 *
	 * @since 1.4.0
	 * @param string $post_type Post type slug to check.
	 * @return bool True if post type is enabled, false otherwise
	 */
	public function is_post_type_enabled( string $post_type ): bool {
		return in_array( $post_type, $this->get_enabled_post_types(), true );
	}

	/**
	 * Get post type object for enabled type
	 *
	 * Returns the WordPress post type object if the post type is enabled,
	 * or null if the post type is disabled or doesn't exist.
	 *
	 * @since 1.4.0
	 * @param string $post_type Post type slug.
	 * @return WP_Post_Type|null Post type object or null
	 */
	public function get_post_type_object( string $post_type ): ?WP_Post_Type {
		if ( ! $this->is_post_type_enabled( $post_type ) ) {
			return null;
		}

		return \get_post_type_object( $post_type );
	}

	/**
	 * Get post type label (singular)
	 *
	 * Returns the singular label for a post type. If the post type is not enabled
	 * or doesn't exist, returns the post type slug as fallback.
	 *
	 * @since 1.4.0
	 * @param string $post_type Post type slug.
	 * @return string Singular label or post type slug
	 */
	public function get_post_type_label( string $post_type ): string {
		$type_obj = $this->get_post_type_object( $post_type );

		if ( ! $type_obj ) {
			return $post_type;
		}

		return $type_obj->labels->singular_name;
	}

	/**
	 * Get post type label (plural)
	 *
	 * Returns the plural label for a post type. If the post type is not enabled
	 * or doesn't exist, returns the post type slug as fallback.
	 *
	 * @since 1.4.0
	 * @param string $post_type Post type slug.
	 * @return string Plural label or post type slug
	 */
	public function get_post_type_label_plural( string $post_type ): string {
		$type_obj = $this->get_post_type_object( $post_type );

		if ( ! $type_obj ) {
			return $post_type;
		}

		return $type_obj->labels->name;
	}
}
