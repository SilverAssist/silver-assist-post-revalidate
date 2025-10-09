<?php
/**
 * Admin Settings Page
 *
 * Creates and manages the admin settings page for configuring
 * revalidation endpoint and authentication token.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.1
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

use SilverAssist\SettingsHub\SettingsHub;

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
		\add_action( 'init', [ $this, 'register_with_settings_hub' ] );
		\add_action( 'admin_init', [ $this, 'register_settings' ] );
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_check_updates_script' ] );
		\add_action( 'wp_ajax_silver_assist_clear_logs', [ $this, 'ajax_clear_logs' ] );
	}

	/**
	 * Register plugin with Settings Hub
	 *
	 * If the Settings Hub is available, register this plugin with it.
	 * Otherwise, fall back to standalone settings page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_with_settings_hub(): void
	{
		// Check if Settings Hub is available.
		if ( ! class_exists( SettingsHub::class ) ) {
			// Fallback: Register standalone settings page if hub is not available.
			\add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
			return;
		}

		// Get the hub instance.
		$hub = SettingsHub::get_instance();

		// Register our plugin with the hub.
		$hub->register_plugin(
			$this->page_slug,
			\__( 'Post Revalidate', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_settings_page' ],
			[
				'description' => \__( 'Automatic cache revalidation for posts and categories. Triggers revalidation requests when content is created, updated, or deleted.', 'silver-assist-revalidate-posts' ),
				'version'     => SILVER_ASSIST_REVALIDATE_VERSION,
				'tab_title'   => \__( 'Post Revalidate', 'silver-assist-revalidate-posts' ),
				'actions'     => [
					[
						'label'    => \__( 'Check Updates', 'silver-assist-revalidate-posts' ),
						'callback' => [ $this, 'render_check_updates_script' ],
						'class'    => 'button button-secondary',
					],
				],
			]
		);
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
	 * Render JavaScript for Check Updates button
	 *
	 * Outputs inline JavaScript call to the external script function.
	 *
	 * @since 1.2.1
	 * @param string $plugin_slug The plugin slug for context.
	 * @return void
	 */
	public function render_check_updates_script( string $plugin_slug ): void
	{
		// Get nonce for AJAX request (must match Updater.php configuration).
		$nonce     = \wp_create_nonce( 'silver_assist_revalidate_version_check' );
		$button_id = "sa-action-{$plugin_slug}-Check-Updates";

		// Call the external JavaScript function.
		echo "silverAssistCheckUpdates('" . \esc_js( $button_id ) . "', '" . \esc_js( $nonce ) . "');";
	}

	/**
	 * Enqueue check updates script
	 *
	 * Loads the JavaScript file for handling check updates button.
	 *
	 * @since 1.2.1
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_check_updates_script( string $hook ): void
	{
		// Only load on Silver Assist dashboard page.
		if ( 'toplevel_page_silver-assist' !== $hook ) {
			return;
		}

		// Enqueue the check updates script.
		\wp_enqueue_script(
			'silver-assist-check-updates',
			\plugin_dir_url( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' ) . 'assets/js/admin-check-updates.js',
			[ 'jquery' ],
			SILVER_ASSIST_REVALIDATE_VERSION,
			true
		);

		// Localize script with translations and data.
		\wp_localize_script(
			'silver-assist-check-updates',
			'silverAssistCheckUpdatesData',
			[
				'ajaxUrl'              => \admin_url( 'admin-ajax.php' ),
				'ajaxAction'           => 'silver_assist_revalidate_check_version',
				'updatesPageUrl'       => \admin_url( 'plugins.php?plugin_status=upgrade' ),
				'checkUpdatesText'     => \__( 'Check Updates', 'silver-assist-revalidate-posts' ),
				'checkingText'         => \__( 'Checking...', 'silver-assist-revalidate-posts' ),
				'updateAvailableText'  => \__( 'Update Available!', 'silver-assist-revalidate-posts' ),
				'upToDateText'         => \__( 'Up to Date', 'silver-assist-revalidate-posts' ),
				'errorText'            => \__( 'Error', 'silver-assist-revalidate-posts' ),
				'errorCheckingMessage' => \__( 'Error checking for updates. Please try again.', 'silver-assist-revalidate-posts' ),
				'networkErrorMessage'  => \__( 'Network error. Please check your connection.', 'silver-assist-revalidate-posts' ),
			]
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

			<?php $this->render_debug_logs_section(); ?>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since 1.2.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void
	{
		// Only load on our settings page.
		if ( false === strpos( $hook, $this->page_slug ) ) {
			return;
		}

		// Enqueue CSS for debug logs section.
		\wp_enqueue_style(
			'silver-assist-debug-logs',
			\plugin_dir_url( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' ) . 'assets/css/admin-debug-logs.css',
			[],
			SILVER_ASSIST_REVALIDATE_VERSION,
			'all'
		);

		// Enqueue JavaScript for debug logs section.
		\wp_enqueue_script(
			'silver-assist-debug-logs',
			\plugin_dir_url( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' ) . 'assets/js/admin-debug-logs.js',
			[ 'jquery' ],
			SILVER_ASSIST_REVALIDATE_VERSION,
			true
		);

		// Localize script with translations and AJAX data.
		\wp_localize_script(
			'silver-assist-debug-logs',
			'silverAssistDebugLogs',
			[
				'ajaxUrl'        => \admin_url( 'admin-ajax.php' ),
				'nonce'          => \wp_create_nonce( 'silver_assist_clear_logs' ),
				'confirmMessage' => \__( 'Are you sure you want to clear all revalidation logs? This action cannot be undone.', 'silver-assist-revalidate-posts' ),
				'clearingText'   => \__( 'Clearing...', 'silver-assist-revalidate-posts' ),
				'errorMessage'   => \__( 'Error clearing logs.', 'silver-assist-revalidate-posts' ),
			]
		);
	}

	/**
	 * Render debug logs section
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_debug_logs_section(): void
	{
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		?>
		<div class="card sa-debug-section">
			<div class="sa-debug-header">
				<h2><?php \esc_html_e( 'Revalidation Debug Logs', 'silver-assist-revalidate-posts' ); ?></h2>
				<?php if ( ! empty( $logs ) ) : ?>
					<button type="button" id="sa-clear-logs-btn" class="button sa-clear-logs">
						<?php \esc_html_e( 'Clear All Logs', 'silver-assist-revalidate-posts' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<p>
				<?php
				/* translators: %d: number of log entries */
				echo \esc_html(
					\sprintf(
						// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- Translator comment is present above sprintf().
						\_n(
							'Showing %d revalidation request (most recent first). Maximum 100 entries are kept.',
							'Showing %d revalidation requests (most recent first). Maximum 100 entries are kept.',
							count( $logs ),
							'silver-assist-revalidate-posts'
						),
						count( $logs )
					)
				);
				?>
			</p>

			<?php if ( empty( $logs ) ) : ?>
				<div class="sa-no-logs">
					<p><?php \esc_html_e( 'No revalidation requests logged yet. Logs will appear here when posts or categories are saved.', 'silver-assist-revalidate-posts' ); ?></p>
				</div>
			<?php else : ?>
				<div class="sa-logs-container">
					<?php foreach ( $logs as $log ) : ?>
						<?php
						$status      = $log['status'] ?? 'error';
						$status_code = $log['status_code'] ?? 0;
						$path        = $log['path'] ?? '';
						$timestamp   = $log['timestamp'] ?? '';
						$request     = $log['request'] ?? [];
						$response    = $log['response'] ?? [];

						// Format JSON for display.
						$request_json  = \wp_json_encode( $request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
						$response_json = \wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
						?>
						<div class="sa-log-item">
							<div class="sa-log-header <?php echo \esc_attr( $status ); ?>">
								<div class="sa-log-path"><?php echo \esc_html( $path ); ?></div>
								<div class="sa-log-meta">
									<span class="sa-log-status <?php echo \esc_attr( $status ); ?>">
										<?php echo \esc_html( $status ); ?>
										<?php if ( $status_code > 0 ) : ?>
											(<?php echo \esc_html( $status_code ); ?>)
										<?php endif; ?>
									</span>
									<span class="sa-log-time"><?php echo \esc_html( $timestamp ); ?></span>
								</div>
							</div>
							<div class="sa-log-content">
								<div class="sa-log-section">
									<h4><?php \esc_html_e( 'Request', 'silver-assist-revalidate-posts' ); ?></h4>
									<textarea readonly rows="8"><?php echo \esc_textarea( false !== $request_json ? $request_json : '' ); ?></textarea>
								</div>
								<div class="sa-log-section">
									<h4><?php \esc_html_e( 'Response', 'silver-assist-revalidate-posts' ); ?></h4>
									<textarea readonly rows="12"><?php echo \esc_textarea( false !== $response_json ? $response_json : '' ); ?></textarea>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler to clear logs
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_clear_logs(): void
	{
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'silver_assist_clear_logs' ) ) {
			\wp_send_json_error( \__( 'Invalid security token.', 'silver-assist-revalidate-posts' ) );
		}

		// Check user capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \__( 'You do not have permission to perform this action.', 'silver-assist-revalidate-posts' ) );
		}

		// Clear logs.
		$result = Revalidate::clear_logs();

		if ( $result ) {
			\wp_send_json_success( \__( 'Logs cleared successfully.', 'silver-assist-revalidate-posts' ) );
		} else {
			\wp_send_json_error( \__( 'Failed to clear logs.', 'silver-assist-revalidate-posts' ) );
		}
	}
}
