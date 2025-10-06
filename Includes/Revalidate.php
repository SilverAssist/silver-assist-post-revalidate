<?php
/**
 * Revalidate Core Functionality
 *
 * Handles post and category revalidation by listening to WordPress hooks
 * and sending revalidation requests to configured endpoint.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.0.0
 * @author Silver Assist
 */

namespace RevalidatePosts;

defined( 'ABSPATH' ) || exit;

/**
 * Core revalidation class
 *
 * Listens to post and category update events and triggers cache revalidation.
 *
 * @since 1.0.0
 */
class Revalidate
{
	/**
	 * Singleton instance
	 *
	 * @var Revalidate|null
	 */
	private static ?Revalidate $instance = null;

	/**
	 * Gets the singleton instance
	 *
	 * @since 1.0.0
	 * @return Revalidate The single instance of the Revalidate class
	 */
	public static function instance(): Revalidate
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Revalidate constructor
	 *
	 * Sets up WordPress hooks for post and category events.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void
	{
		// Hook into post save actions.
		\add_action( 'save_post', [ $this, 'on_post_saved' ], 10, 1 );

		// Hook into category update actions.
		\add_action( 'created_category', [ $this, 'on_category_updated' ], 10, 1 );
		\add_action( 'edited_category', [ $this, 'on_category_updated' ], 10, 1 );
		\add_action( 'delete_category', [ $this, 'on_category_updated' ], 10, 1 );
	}

	/**
	 * Handles post saving events to trigger revalidation
	 *
	 * When a post is saved or updated, this function identifies the affected paths
	 * and initiates the cache invalidation process.
	 *
	 * @since 1.0.0
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function on_post_saved( int $post_id ): void
	{
		// Skip autosaves and revisions.
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = \get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		// Only handle standard posts for now (custom post types can be added in future versions).
		$allowed_post_types = [ 'post' ];
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		$paths = [];

		// Get post permalink as relative path.
		$permalink = \get_permalink( $post_id );
		if ( $permalink ) {
			$paths[] = $this->get_relative_path_from_url( $permalink );
		}

		// Get category paths for this post.
		$categories = \get_the_category( $post_id );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_link = \get_category_link( $category->term_id );
				if ( $category_link ) {
					$paths[] = $this->get_relative_path_from_url( $category_link );
				}
			}
		}

		if ( ! empty( $paths ) ) {
			$this->revalidate_paths( $paths );
		}
	}

	/**
	 * Handles category updates to trigger revalidation
	 *
	 * When a category is updated, this function identifies all affected paths
	 * and initiates the cache invalidation process.
	 *
	 * @since 1.0.0
	 * @param int $term_id The ID of the updated category.
	 * @return void
	 */
	public function on_category_updated( int $term_id ): void
	{
		$category = \get_term( $term_id, 'category' );
		if ( \is_wp_error( $category ) || ! $category ) {
			return;
		}

		$paths = [];

		// Get category archive link.
		$category_link = \get_category_link( $term_id );
		if ( $category_link ) {
			$paths[] = $this->get_relative_path_from_url( $category_link );
		}

		// Get all posts in this category.
		$posts = \get_posts(
			[
				'category'    => $term_id,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'numberposts' => -1,
			]
		);

		foreach ( $posts as $post ) {
			$post_link = \get_permalink( $post->ID );
			if ( $post_link ) {
				$paths[] = $this->get_relative_path_from_url( $post_link );
			}
		}

		if ( ! empty( $paths ) ) {
			$this->revalidate_paths( $paths );
		}
	}

	/**
	 * Converts a full URL to a relative path
	 *
	 * Removes the domain from a URL and returns only the path portion.
	 *
	 * @since 1.0.0
	 * @param string $url The full URL to convert.
	 * @return string The relative path from the URL.
	 */
	private function get_relative_path_from_url( string $url ): string
	{
		$home_url = \home_url();
		$path     = str_replace( $home_url, '', $url );
		$path     = '/' . trim( $path, '/' ) . '/';
		return $path;
	}

	/**
	 * Revalidates specified paths by sending requests to configured endpoint
	 *
	 * Sends GET requests to the revalidation endpoint with token and path parameters.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $paths Array of paths to revalidate.
	 * @return void
	 */
	public function revalidate_paths( array $paths ): void
	{
		$revalidate_url   = \get_option( 'revalidate_endpoint' );
		$revalidate_token = \get_option( 'revalidate_token' );

		if ( ! $revalidate_url || ! $revalidate_token ) {
			// Log error if WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( \__( 'Silver Assist Post Revalidate: Endpoint or token not configured', 'silver-assist-revalidate-posts' ) );
			}
			return;
		}

		foreach ( $paths as $path ) {
			$url = \add_query_arg(
				[
					'token' => $revalidate_token,
					'path'  => $path,
				],
				$revalidate_url
			);

			$response = \wp_remote_get(
				$url,
				[
					'timeout'    => 30,
					'sslverify'  => true,
					'user-agent' => 'Silver-Assist-Revalidate/' . SILVER_ASSIST_REVALIDATE_VERSION,
				]
			);

			if ( \is_wp_error( $response ) ) {
				// Log error if WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					/* translators: 1: path, 2: error message */
					$message = \sprintf(
						// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- Translator comment is present above sprintf().
						\__( 'Error revalidating path %1$s: %2$s', 'silver-assist-revalidate-posts' ),
						$path,
						$response->get_error_message()
					);
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $message );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Log success if WP_DEBUG is enabled.
				/* translators: %s: revalidated path */
				$message = \sprintf(
					// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- Translator comment is present above sprintf().
					\__( 'Successfully revalidated path: %s', 'silver-assist-revalidate-posts' ),
					$path
				);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $message );
			}
		}
	}
}
