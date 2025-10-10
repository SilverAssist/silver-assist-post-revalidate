<?php
/**
 * Revalidate Core Functionality
 *
 * Handles post and category revalidation by listening to WordPress hooks
 * and sending revalidation requests to configured endpoint.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.2
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

use WP_Post;

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
	 * Posts already processed in this request to prevent duplicate revalidation
	 *
	 * @since 1.2.2
	 * @var array<int, bool>
	 */
	private array $processed_posts = [];

	/**
	 * Flag to disable transient cooldown for testing
	 *
	 * @since 1.2.2
	 * @var bool
	 */
	private bool $disable_cooldown = false;

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
	 * Reset processed posts cache
	 *
	 * Clears the internal cache of processed posts. Useful for testing
	 * or when you need to force revalidation of the same post again.
	 *
	 * @since 1.2.2
	 * @return void
	 */
	public function reset_processed_posts(): void
	{
		$this->processed_posts = [];
	}

	/**
	 * Set cooldown mode
	 *
	 * Enables or disables the transient-based cooldown. Useful for testing
	 * or when you need to force immediate revalidation without delays.
	 *
	 * @since 1.2.2
	 * @param bool $disable Whether to disable the cooldown.
	 * @return void
	 */
	public function set_cooldown_disabled( bool $disable ): void
	{
		$this->disable_cooldown = $disable;
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
		\add_action( 'delete_post', [ $this, 'on_post_deleted' ], 10, 1 );
		\add_action( 'transition_post_status', [ $this, 'on_post_status_changed' ], 10, 3 );

		// Hook into category update actions.
		\add_action( 'created_category', [ $this, 'on_category_updated' ], 10, 1 );
		\add_action( 'edited_category', [ $this, 'on_category_updated' ], 10, 1 );
		\add_action( 'delete_category', [ $this, 'on_category_updated' ], 10, 1 );

		// Hook into tag update actions.
		\add_action( 'created_post_tag', [ $this, 'on_tag_updated' ], 10, 1 );
		\add_action( 'edited_post_tag', [ $this, 'on_tag_updated' ], 10, 1 );
		\add_action( 'delete_post_tag', [ $this, 'on_tag_updated' ], 10, 1 );
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
		// Prevent duplicate revalidation within the same request.
		// WordPress often fires save_post multiple times for a single edit.
		if ( isset( $this->processed_posts[ $post_id ] ) ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = \get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		// Only handle standard posts for now (custom post types can be added in future versions).
		$allowed_post_types = [ 'post' ];
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		// Mark this post as processed.
		$this->processed_posts[ $post_id ] = true;

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

		// Get tag paths for this post.
		$tags = \get_the_tags( $post_id );
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_link = \get_tag_link( $tag->term_id );
				if ( $tag_link ) {
					$paths[] = $this->get_relative_path_from_url( $tag_link );
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
	 * Handles post deletion events to trigger revalidation
	 *
	 * When a post is deleted, this function revalidates the post permalink
	 * and all related category/tag archives.
	 *
	 * @since 1.2.0
	 * @param int $post_id The ID of the deleted post.
	 * @return void
	 */
	public function on_post_deleted( int $post_id ): void
	{
		// Skip autosaves and revisions.
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = \get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return;
		}

		$paths = [];

		// Get post permalink before deletion.
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

		// Get tag paths for this post.
		$tags = \get_the_tags( $post_id );
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_link = \get_tag_link( $tag->term_id );
				if ( $tag_link ) {
					$paths[] = $this->get_relative_path_from_url( $tag_link );
				}
			}
		}

		if ( ! empty( $paths ) ) {
			$this->revalidate_paths( $paths );
		}
	}

	/**
	 * Handles post status transitions to trigger revalidation
	 *
	 * When a post status changes (e.g., publish → draft, draft → publish),
	 * this function triggers revalidation of the post and related taxonomies.
	 * This is crucial for unpublishing content as it needs to invalidate caches
	 * even when the post is no longer in publish status.
	 *
	 * @since 1.2.0
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function on_post_status_changed( string $new_status, string $old_status, $post ): void
	{
		// Skip if status hasn't changed.
		if ( $new_status === $old_status ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( \wp_is_post_autosave( $post->ID ) || \wp_is_post_revision( $post->ID ) ) {
			return;
		}

		// Only handle standard posts.
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Only revalidate if transitioning to/from publish status.
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		// Collect paths to revalidate.
		$paths = [];

		// Add post permalink (using cached permalink if post is no longer published).
		$post_url = \get_permalink( $post->ID );
		if ( ! empty( $post_url ) ) {
			$paths[] = $this->get_relative_path_from_url( $post_url );
		}

		// Add category paths.
		$categories = \get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_link = \get_category_link( $category->term_id );
				if ( ! empty( $category_link ) ) {
					$paths[] = $this->get_relative_path_from_url( $category_link );
				}
			}
		}

		// Add tag paths.
		$tags = \get_the_tags( $post->ID );
		if ( ! empty( $tags ) && \is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_link = \get_tag_link( $tag->term_id );
				if ( ! empty( $tag_link ) ) {
					$paths[] = $this->get_relative_path_from_url( $tag_link );
				}
			}
		}

		// Revalidate all paths.
		if ( ! empty( $paths ) ) {
			$this->revalidate_paths( $paths );
		}
	}

	/**
	 * Handles tag updates to trigger revalidation
	 *
	 * When a tag is updated, this function identifies all affected paths
	 * and initiates the cache invalidation process.
	 *
	 * @since 1.2.0
	 * @param int $term_id The ID of the updated tag.
	 * @return void
	 */
	public function on_tag_updated( int $term_id ): void
	{
		$tag = \get_term( $term_id, 'post_tag' );
		if ( \is_wp_error( $tag ) || ! $tag ) {
			return;
		}

		$paths = [];

		// Get tag archive link.
		$tag_link = \get_tag_link( $term_id );
		if ( $tag_link ) {
			$paths[] = $this->get_relative_path_from_url( $tag_link );
		}

		// Get all posts with this tag.
		$posts = \get_posts(
			[
				'tag_id'      => $term_id,
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
	 * Uses transients to prevent duplicate revalidations of the same path within a short timeframe.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $paths Array of paths to revalidate.
	 * @return void
	 */
	public function revalidate_paths( array $paths ): void
	{
		// Remove duplicate paths.
		$paths = array_unique( $paths );

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
			// Check if this path was revalidated recently (within 5 seconds).
			// Skip cooldown check if disabled (e.g., during testing).
			if ( ! $this->disable_cooldown ) {
				$transient_key = 'sa_revalidate_' . md5( $path );
				if ( \get_transient( $transient_key ) ) {
					// Skip this path - it was already revalidated recently.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						/* translators: %s: path that was skipped */
						$message = \sprintf(
							// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- Translator comment is present above sprintf().
							\__( 'Skipping recent revalidation for path: %s', 'silver-assist-revalidate-posts' ),
							$path
						);
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( $message );
					}
					continue;
				}

				// Set transient to prevent duplicate revalidations for 5 seconds.
				\set_transient( $transient_key, true, 5 );
			}
			$url = \add_query_arg(
				[
					'token' => $revalidate_token,
					'path'  => $path,
				],
				$revalidate_url
			);

			$request_data = [
				'url'     => $url,
				'method'  => 'GET',
				'headers' => [
					'User-Agent' => 'Silver-Assist-Revalidate/' . SILVER_ASSIST_REVALIDATE_VERSION,
				],
				'timeout' => 30,
			];

			$response = \wp_remote_get(
				$url,
				[
					'timeout'    => 30,
					'sslverify'  => true,
					'user-agent' => 'Silver-Assist-Revalidate/' . SILVER_ASSIST_REVALIDATE_VERSION,
				]
			);

			// Prepare log entry.
			$log_entry = [
				'timestamp'   => \current_time( 'mysql' ),
				'path'        => $path,
				'request'     => $request_data,
				'response'    => [],
				'status'      => 'error',
				'status_code' => 0,
			];

			if ( \is_wp_error( $response ) ) {
				$log_entry['response'] = [
					'error'   => true,
					'message' => $response->get_error_message(),
					'code'    => $response->get_error_code(),
				];

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
			} else {
				$response_code = \wp_remote_retrieve_response_code( $response );
				$response_body = \wp_remote_retrieve_body( $response );
				$headers       = \wp_remote_retrieve_headers( $response );

				$log_entry['status']      = ( $response_code >= 200 && $response_code < 300 ) ? 'success' : 'error';
				$log_entry['status_code'] = $response_code;
				$log_entry['response']    = [
					'code'    => $response_code,
					'message' => \wp_remote_retrieve_response_message( $response ),
					'body'    => $response_body,
					'headers' => is_array( $headers ) ? $headers : ( method_exists( $headers, 'getAll' ) ? $headers->getAll() : [] ),
				];

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
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

			// Save log entry.
			$this->save_log_entry( $log_entry );
		}
	}

	/**
	 * Saves a log entry to the database
	 *
	 * Maintains a rotating log with a maximum of 100 entries (FIFO).
	 *
	 * @since 1.2.0
	 * @param array<string, mixed> $log_entry The log entry to save.
	 * @return void
	 */
	private function save_log_entry( array $log_entry ): void
	{
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );

		// Ensure $logs is an array.
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		// Add new log entry at the beginning (most recent first).
		array_unshift( $logs, $log_entry );

		// Limit to 100 entries (rotate old logs).
		$max_logs = 100;
		if ( count( $logs ) > $max_logs ) {
			$logs = array_slice( $logs, 0, $max_logs );
		}

		// Save updated logs.
		\update_option( 'silver_assist_revalidate_logs', $logs );
	}

	/**
	 * Clears all stored logs
	 *
	 * @since 1.2.0
	 * @return bool True if logs were cleared, false otherwise.
	 */
	public static function clear_logs(): bool
	{
		return \delete_option( 'silver_assist_revalidate_logs' );
	}
}
