<?php
/**
 * Admin Settings Page
 *
 * Creates and manages the admin settings page for configuring
 * revalidation endpoint and authentication token.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.0.0
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page class
 *
 * Handles the creation and management of the plugin settings page in WordPress admin.
 *
 * @since 1.0.0
 */
class AdminSettings
{
	/**
	 * Singleton instance
	 *
	 * @var AdminSettings|null
	 */
	private static ?AdminSettings $instance = null;

	/**
	 * Option group name
	 *
	 * @var string
	 */
	private string $option_group = 'silver_assist_revalidate_settings';

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private string $page_slug = 'silver-assist-revalidate';

	/**
	 * Gets the singleton instance
	 *
	 * @since 1.0.0
	 * @return AdminSettings The single instance of the AdminSettings class
	 */
	public static function instance(): AdminSettings
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * AdminSettings constructor
	 *
	 * Sets up WordPress admin hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress admin hooks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void
	{
		\add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		\add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add settings page to WordPress admin menu
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_settings_page(): void
	{
		\add_options_page(
			\__( 'Post Revalidate Settings', 'silver-assist-revalidate-posts' ),
			\__( 'Post Revalidate', 'silver-assist-revalidate-posts' ),
			'manage_options',
			$this->page_slug,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings with WordPress Settings API
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void
	{
		// Register settings.
		\register_setting(
			$this->option_group,
			'revalidate_endpoint',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_url',
				'default'           => '',
			]
		);

		\register_setting(
			$this->option_group,
			'revalidate_token',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		// Add settings section.
		\add_settings_section(
			'silver_assist_revalidate_main',
			\__( 'Revalidation Settings', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_section_description' ],
			$this->page_slug
		);

		// Add endpoint field.
		\add_settings_field(
			'revalidate_endpoint',
			\__( 'Revalidate Endpoint', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_endpoint_field' ],
			$this->page_slug,
			'silver_assist_revalidate_main'
		);

		// Add token field.
		\add_settings_field(
			'revalidate_token',
			\__( 'Revalidate Token', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_token_field' ],
			$this->page_slug,
			'silver_assist_revalidate_main'
		);
	}

	/**
	 * Render settings section description
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_section_description(): void
	{
		echo '<p>';
		\esc_html_e(
			'Configure the endpoint and authentication token for post and category revalidation.',
			'silver-assist-revalidate-posts'
		);
		echo '</p>';
	}

	/**
	 * Render endpoint field
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_endpoint_field(): void
	{
		$value = \get_option( 'revalidate_endpoint', '' );
		?>
		<input 
			type="url" 
			name="revalidate_endpoint" 
			id="revalidate_endpoint" 
			value="<?php echo \esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="https://example.com/api/revalidate"
		/>
		<p class="description">
			<?php \esc_html_e( 'The URL endpoint that will receive revalidation requests.', 'silver-assist-revalidate-posts' ); ?>
		</p>
		<?php
	}

	/**
	 * Render token field
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_token_field(): void
	{
		$value = \get_option( 'revalidate_token', '' );
		?>
		<input 
			type="text" 
			name="revalidate_token" 
			id="revalidate_token" 
			value="<?php echo \esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="<?php \esc_attr_e( 'Enter authentication token', 'silver-assist-revalidate-posts' ); ?>"
		/>
		<p class="description">
			<?php \esc_html_e( 'The authentication token required by the revalidation endpoint.', 'silver-assist-revalidate-posts' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page(): void
	{
		// Check user capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		// Display admin notices if settings were updated.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking for settings update, no action taken.
		if ( isset( $_GET['settings-updated'] ) ) {
			\add_settings_error(
				'silver_assist_revalidate_messages',
				'silver_assist_revalidate_message',
				\__( 'Settings saved successfully.', 'silver-assist-revalidate-posts' ),
				'updated'
			);
		}

		// Show error/update messages.
		\settings_errors( 'silver_assist_revalidate_messages' );
		?>
		<div class="wrap">
			<h1>
				<?php echo \esc_html( \get_admin_page_title() ); ?>
				<span style="font-size: 0.6em; font-weight: normal; color: #666; margin-left: 10px;">
					v<?php echo \esc_html( SILVER_ASSIST_REVALIDATE_VERSION ); ?>
				</span>
			</h1>
			<form method="post" action="options.php">
				<?php
				\settings_fields( $this->option_group );
				\do_settings_sections( $this->page_slug );
				\submit_button( \__( 'Save Settings', 'silver-assist-revalidate-posts' ) );
				?>
			</form>

			<div class="card">
				<h2><?php \esc_html_e( 'How It Works', 'silver-assist-revalidate-posts' ); ?></h2>
				<p><?php \esc_html_e( 'This plugin automatically revalidates your content when:', 'silver-assist-revalidate-posts' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php \esc_html_e( 'A post is created, updated, or deleted', 'silver-assist-revalidate-posts' ); ?></li>
					<li><?php \esc_html_e( 'A category is created, updated, or deleted', 'silver-assist-revalidate-posts' ); ?></li>
				</ul>
				<p>
					<?php
					\esc_html_e(
						'The plugin sends revalidation requests to your configured endpoint with the affected paths (without domain) and authentication token.',
						'silver-assist-revalidate-posts'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
