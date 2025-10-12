<?php
/**
 * Manual Revalidation Functionality
 *
 * Handles manual revalidation from post list and post editor.
 * Provides row actions and meta box for triggering revalidation.
 *
 * @package RevalidatePosts
 * @since 1.4.0
 * @version 1.4.0
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Manual revalidation class
 *
 * Provides UI elements and AJAX handlers for manual post revalidation.
 *
 * @since 1.4.0
 */
class ManualRevalidation
{
	/**
	 * Singleton instance
	 *
	 * @var ManualRevalidation|null
	 */
	private static ?ManualRevalidation $instance = null;

	/**
	 * Gets the singleton instance
	 *
	 * @since 1.4.0
	 * @return ManualRevalidation The single instance of the ManualRevalidation class
	 */
	public static function instance(): ManualRevalidation
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ManualRevalidation constructor
	 *
	 * Sets up WordPress hooks for manual revalidation.
	 *
	 * @since 1.4.0
	 */
	private function __construct()
	{
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function init_hooks(): void
	{
		// Add row action to post list.
		\add_filter( 'post_row_actions', [ $this, 'add_revalidate_row_action' ], 10, 2 );

		// Add meta box to post editor.
		\add_action( 'add_meta_boxes', [ $this, 'add_revalidate_meta_box' ] );

		// Handle AJAX revalidation request.
		\add_action( 'wp_ajax_silver_assist_manual_revalidate', [ $this, 'ajax_manual_revalidate' ] );

		// Handle admin action (for non-AJAX row action clicks).
		\add_action( 'admin_action_revalidate_post', [ $this, 'handle_revalidate_action' ] );

		// Enqueue admin scripts for meta box.
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Add "Revalidate" action to post row actions
	 *
	 * @since 1.4.0
	 * @param array<string, string> $actions Existing row actions.
	 * @param WP_Post               $post    Current post object.
	 * @return array<string, string> Modified row actions
	 */
	public function add_revalidate_row_action( array $actions, WP_Post $post ): array
	{
		// Only show for published posts.
		if ( 'publish' !== $post->post_status ) {
			return $actions;
		}

		// Check if user can edit posts.
		if ( ! \current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		// Only show for post type 'post' for now.
		if ( 'post' !== $post->post_type ) {
			return $actions;
		}

		// Build revalidation URL with nonce.
		$url = \wp_nonce_url(
			\admin_url( 'admin.php?action=revalidate_post&post=' . $post->ID ),
			'revalidate_post_' . $post->ID
		);

		// Add the action.
		$actions['revalidate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			\esc_url( $url ),
			\esc_attr( sprintf(
				/* translators: %s: post title */
				\__( 'Revalidate "%s"', 'silver-assist-revalidate-posts' ),
				$post->post_title
			) ),
			\esc_html__( 'Revalidate', 'silver-assist-revalidate-posts' )
		);

		return $actions;
	}

	/**
	 * Add revalidate meta box to post editor
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function add_revalidate_meta_box(): void
	{
		$post = \get_post();

		// Only show for published posts.
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		// Only for post type 'post' for now.
		if ( 'post' !== $post->post_type ) {
			return;
		}

		\add_meta_box(
			'silver_assist_revalidate_meta_box',
			\__( 'Revalidate', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_revalidate_meta_box' ],
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render revalidate meta box content
	 *
	 * @since 1.4.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_revalidate_meta_box( WP_Post $post ): void
	{
		// Add nonce for security.
		\wp_nonce_field( 'revalidate_post_' . $post->ID, 'revalidate_post_nonce' );
		?>
		<div class="silver-assist-revalidate-meta-box">
			<p><?php \esc_html_e( 'Manually trigger cache revalidation for this post.', 'silver-assist-revalidate-posts' ); ?></p>
			<button type="button" class="button button-primary" id="silver-assist-revalidate-button" data-post-id="<?php echo \esc_attr( $post->ID ); ?>">
				<?php \esc_html_e( 'Revalidate this post', 'silver-assist-revalidate-posts' ); ?>
			</button>
			<div id="silver-assist-revalidate-message" style="margin-top: 10px;"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts for meta box
	 *
	 * @since 1.4.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void
	{
		// Only load on post edit screen.
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		\wp_enqueue_script(
			'silver-assist-manual-revalidate',
			\plugins_url( 'assets/js/manual-revalidate.js', dirname( __DIR__ ) . '/silver-assist-post-revalidate.php' ),
			[ 'jquery' ],
			SILVER_ASSIST_REVALIDATE_VERSION,
			true
		);

		\wp_localize_script(
			'silver-assist-manual-revalidate',
			'silverAssistManualRevalidate',
			[
				'ajaxurl' => \admin_url( 'admin-ajax.php' ),
				'strings' => [
					'revalidating' => \__( 'Revalidating...', 'silver-assist-revalidate-posts' ),
					'success'      => \__( 'Post revalidated successfully!', 'silver-assist-revalidate-posts' ),
					'error'        => \__( 'Error revalidating post. Please try again.', 'silver-assist-revalidate-posts' ),
				],
			]
		);
	}

	/**
	 * Handle AJAX manual revalidation request
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function ajax_manual_revalidate(): void
	{
		// Verify nonce.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$nonce   = isset( $_POST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! \wp_verify_nonce( $nonce, 'revalidate_post_' . $post_id ) ) {
			\wp_send_json_error(
				[
					'message' => \__( 'Security check failed.', 'silver-assist-revalidate-posts' ),
				]
			);
		}

		// Check user capability.
		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_send_json_error(
				[
					'message' => \__( 'You do not have permission to perform this action.', 'silver-assist-revalidate-posts' ),
				]
			);
		}

		// Get post.
		$post = \get_post( $post_id );
		if ( ! $post ) {
			\wp_send_json_error(
				[
					'message' => \__( 'Post not found.', 'silver-assist-revalidate-posts' ),
				]
			);
		}

		// Trigger revalidation.
		$result = $this->trigger_revalidation( $post );

		if ( $result ) {
			\wp_send_json_success(
				[
					'message' => \__( 'Post revalidated successfully!', 'silver-assist-revalidate-posts' ),
				]
			);
		} else {
			\wp_send_json_error(
				[
					'message' => \__( 'Error revalidating post.', 'silver-assist-revalidate-posts' ),
				]
			);
		}
	}

	/**
	 * Handle non-AJAX revalidate action from row action
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function handle_revalidate_action(): void
	{
		// Get post ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ), 'revalidate_post_' . $post_id ) ) {
			\wp_die( \esc_html__( 'Security check failed.', 'silver-assist-revalidate-posts' ) );
		}

		// Check user capability.
		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_die( \esc_html__( 'You do not have permission to perform this action.', 'silver-assist-revalidate-posts' ) );
		}

		// Get post.
		$post = \get_post( $post_id );
		if ( ! $post ) {
			\wp_die( \esc_html__( 'Post not found.', 'silver-assist-revalidate-posts' ) );
		}

		// Trigger revalidation.
		$result = $this->trigger_revalidation( $post );

		// Redirect back with message.
		$redirect_url = \admin_url( 'edit.php?post_type=post' );

		if ( $result ) {
			$redirect_url = \add_query_arg( 'revalidated', '1', $redirect_url );
		} else {
			$redirect_url = \add_query_arg( 'revalidate_error', '1', $redirect_url );
		}

		\wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Trigger revalidation for a post
	 *
	 * @since 1.4.0
	 * @param WP_Post $post Post object.
	 * @return bool True on success, false on failure
	 */
	private function trigger_revalidation( WP_Post $post ): bool
	{
		// Get post permalink.
		$permalink = \get_permalink( $post );
		if ( ! $permalink ) {
			return false;
		}

		// Convert to relative path.
		$revalidate = Revalidate::instance();
		$path       = $revalidate->url_to_relative_path( $permalink );

		// Collect all paths to revalidate.
		$paths = [ $path ];

		// Add category paths.
		$categories = \get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_link = \get_category_link( $category->term_id );
				if ( $category_link ) {
					$paths[] = $revalidate->url_to_relative_path( $category_link );
				}
			}
		}

		// Add tag paths.
		$tags = \get_the_tags( $post->ID );
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_link = \get_tag_link( $tag->term_id );
				if ( $tag_link ) {
					$paths[] = $revalidate->url_to_relative_path( $tag_link );
				}
			}
		}

		// Remove duplicates.
		$paths = array_unique( $paths );

		// Trigger revalidation with manual trigger flag.
		foreach ( $paths as $path_to_revalidate ) {
			$revalidate->revalidate_path( $path_to_revalidate, 'manual' );
		}

		return true;
	}
}
