<?php
/**
 * Admin Settings Page
 *
 * Creates and manages the admin settings page for configuring
 * revalidation endpoint and authentication token.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.3.1
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
		\add_action( 'wp_ajax_silver_assist_clear_logs', [ $this, 'ajax_clear_logs' ] );
		\add_action( 'wp_ajax_silver_assist_revalidate_check_version', [ $this, 'ajax_check_updates' ] );
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

		// Get actions array for plugin card.
		$actions = $this->get_hub_actions();

		// Register our plugin with the hub.
		$hub->register_plugin(
			$this->page_slug,
			\__( 'Post Revalidate', 'silver-assist-revalidate-posts' ),
			[ $this, 'render_settings_page' ],
			[
				'description' => \__( 'Automatic cache revalidation for posts and categories. Triggers revalidation requests when content is created, updated, or deleted.', 'silver-assist-revalidate-posts' ),
				'version'     => SILVER_ASSIST_REVALIDATE_VERSION,
				'tab_title'   => \__( 'Post Revalidate', 'silver-assist-revalidate-posts' ),
				'actions'     => $actions,
			]
		);
	}

	/**
	 * Get actions array for Settings Hub plugin card
	 *
	 * @since 1.2.1
	 * @return array<int, array{label: string, callback: callable, class?: string}> Array of action configurations
	 */
	private function get_hub_actions(): array
	{
		$actions = [];

		// Add "Check Updates" button if updater is available.
		$plugin = Plugin::instance();
		if ( $plugin->get_updater() ) {
			$actions[] = [
				'label'    => \__( 'Check Updates', 'silver-assist-revalidate-posts' ),
				'callback' => [ $this, 'render_check_updates_script' ],
				'class'    => 'button button-primary',
			];
		}

		return $actions;
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
				'sanitize_callback' => [ $this, 'sanitize_token' ],
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
	 * Render update check script for Settings Hub action button
	 *
	 * Enqueues external JavaScript file and localizes data for AJAX update checking.
	 * Follows WordPress best practices for script enqueuing.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function render_check_updates_script(): void
	{
		$plugin  = Plugin::instance();
		$updater = $plugin->get_updater();

		if ( ! $updater ) {
			return;
		}

		// Enqueue external JavaScript file.
		\wp_enqueue_script(
			'revalidate-check-updates',
			\plugins_url( 'assets/js/admin-check-updates.js', dirname( __DIR__ ) . '/silver-assist-post-revalidate.php' ),
			[ 'jquery' ],
			SILVER_ASSIST_REVALIDATE_VERSION,
			true
		);

		// Localize script with configuration data.
		\wp_localize_script(
			'revalidate-check-updates',
			'silverAssistRevalidateCheckUpdatesData',
			[
				'ajaxurl'   => \admin_url( 'admin-ajax.php' ),
				'nonce'     => \wp_create_nonce( 'silver_assist_revalidate_version_check' ),
				'updateUrl' => \admin_url( 'update-core.php' ),
				'strings'   => [
					'checking'        => \__( 'Checking for updates...', 'silver-assist-revalidate-posts' ),
					'updateAvailable' => \__( 'Update available! Redirecting to Updates page...', 'silver-assist-revalidate-posts' ),
					'upToDate'        => \__( "You're up to date!", 'silver-assist-revalidate-posts' ),
					'checkError'      => \__( 'Error checking updates. Please try again.', 'silver-assist-revalidate-posts' ),
					'connectError'    => \__( 'Error connecting to update server.', 'silver-assist-revalidate-posts' ),
				],
			]
		);

		// Echo JavaScript that will be executed by Settings Hub action button.
		// Settings Hub injects this into onclick="" attribute.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline JavaScript function call
		echo 'silverAssistRevalidateCheckUpdates(); return false;';
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
	 * Sanitize token field
	 *
	 * Preserves the existing token if the submitted value is masked (contains bullet points).
	 * This prevents the masked placeholder from overwriting the actual token.
	 *
	 * @since 1.2.3
	 * @param string $input The token value to sanitize.
	 * @return string Sanitized token or existing token if masked value submitted
	 */
	public function sanitize_token( string $input ): string
	{
		// Check if input contains bullet points (masked value).
		if ( strpos( $input, '•' ) !== false ) {
			// Return existing token, don't overwrite with masked value.
			return \get_option( 'revalidate_token', '' );
		}

		// Sanitize new token value.
		return \sanitize_text_field( $input );
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
	 * Displays the token field with security masking. Shows only last 4 characters
	 * when a token exists, with a toggle button to reveal/hide the full token.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_token_field(): void
	{
		$value         = \get_option( 'revalidate_token', '' );
		$has_token     = ! empty( $value );
		$masked_value  = '';
		$display_value = $value;

		if ( $has_token ) {
			// Show only last 4 characters for security.
			$token_length = strlen( $value );
			if ( $token_length > 4 ) {
				$masked_value  = str_repeat( '•', $token_length - 4 ) . substr( $value, -4 );
				$display_value = $masked_value;
			}
		}
		?>
		<div class="silver-assist-token-field-wrapper" style="display: flex; align-items: center; gap: 10px;">
			<input 
				type="password" 
				name="revalidate_token" 
				id="revalidate_token" 
				value="<?php echo \esc_attr( $display_value ); ?>" 
				class="regular-text"
				placeholder="<?php \esc_attr_e( 'Enter authentication token', 'silver-assist-revalidate-posts' ); ?>"
				data-original-value="<?php echo \esc_attr( $value ); ?>"
				data-masked-value="<?php echo \esc_attr( $masked_value ); ?>"
				autocomplete="off"
			/>
			<?php if ( $has_token ) : ?>
				<button 
					type="button" 
					id="toggle-token-visibility" 
					class="button button-secondary"
					aria-label="<?php \esc_attr_e( 'Toggle token visibility', 'silver-assist-revalidate-posts' ); ?>"
				>
					<span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
					<span class="toggle-text"><?php \esc_html_e( 'Show', 'silver-assist-revalidate-posts' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
		<p class="description">
			<?php \esc_html_e( 'The authentication token required by the revalidation endpoint. Token is masked for security.', 'silver-assist-revalidate-posts' ); ?>
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
					<li><?php \esc_html_e( 'A post status changes (publish/unpublish)', 'silver-assist-revalidate-posts' ); ?></li>
					<li><?php \esc_html_e( 'A category is created, updated, or deleted', 'silver-assist-revalidate-posts' ); ?></li>
					<li><?php \esc_html_e( 'A tag is created, updated, or deleted', 'silver-assist-revalidate-posts' ); ?></li>
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

		// Add inline script for token visibility toggle.
		\wp_add_inline_script(
			'silver-assist-debug-logs',
			"
			jQuery(document).ready(function($) {
				const tokenInput = $('#revalidate_token');
				const toggleBtn = $('#toggle-token-visibility');
				
				if (tokenInput.length && toggleBtn.length) {
					let isVisible = false;
					const originalValue = tokenInput.data('original-value');
					const maskedValue = tokenInput.data('masked-value');
					
					toggleBtn.on('click', function(e) {
						e.preventDefault();
						isVisible = !isVisible;
						
						if (isVisible) {
							tokenInput.attr('type', 'text').val(originalValue);
							toggleBtn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
							toggleBtn.find('.toggle-text').text('" . \esc_js( \__( 'Hide', 'silver-assist-revalidate-posts' ) ) . "');
						} else {
							tokenInput.attr('type', 'password').val(maskedValue);
							toggleBtn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
							toggleBtn.find('.toggle-text').text('" . \esc_js( \__( 'Show', 'silver-assist-revalidate-posts' ) ) . "');
						}
					});
					
					// Reset to masked value on input change (when user starts typing).
					tokenInput.on('input', function() {
						if ($(this).val() !== originalValue && $(this).val() !== maskedValue) {
							tokenInput.data('masked-value', '');
							toggleBtn.hide();
						}
					});
				}
			});
			"
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

	/**
	 * AJAX handler for checking plugin updates
	 *
	 * Implements complete WordPress update cache synchronization:
	 * 1. Clears plugin version cache (GitHub API cache)
	 * 2. Clears WordPress update cache (CRITICAL for update-core.php)
	 * 3. Forces WordPress to check for updates NOW
	 *
	 * This ensures the Updates page shows the plugin as updatable.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function ajax_check_updates(): void
	{
		// Validate nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'silver_assist_revalidate_version_check' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Security validation failed', 'silver-assist-revalidate-posts' ) ] );
		}

		// Check user capability - use update_plugins, not manage_options.
		if ( ! \current_user_can( 'update_plugins' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Insufficient permissions', 'silver-assist-revalidate-posts' ) ] );
		}

		$plugin  = Plugin::instance();
		$updater = $plugin->get_updater();

		if ( ! $updater ) {
			\wp_send_json_error( [ 'message' => \__( 'Updater not available', 'silver-assist-revalidate-posts' ) ] );
		}

		try {
			// STEP 1: Clear plugin version cache (GitHub API cache).
			$transient_key = 'silver-assist-revalidate-posts_version_check';
			\delete_transient( $transient_key );

			// STEP 2: Clear WordPress update cache (CRITICAL).
			// This forces WordPress to rebuild its update information.
			\delete_site_transient( 'update_plugins' );

			// STEP 3: Force WordPress to check for updates NOW.
			// This triggers the 'pre_set_site_transient_update_plugins' hook
			// which wp-github-updater listens to.
			\wp_update_plugins();

			// Get update status.
			$update_available = $updater->isUpdateAvailable();
			$current_version  = $updater->getCurrentVersion();
			$latest_version   = $updater->getLatestVersion();

			\wp_send_json_success(
				[
					'update_available' => $update_available,
					'current_version'  => $current_version,
					'latest_version'   => $latest_version,
					'message'          => $update_available
						? \__( 'Update available!', 'silver-assist-revalidate-posts' )
						: \__( "You're up to date!", 'silver-assist-revalidate-posts' ),
				]
			);
		} catch ( \Exception $e ) {
			\wp_send_json_error(
				[
					'message' => \__( 'Error checking for updates', 'silver-assist-revalidate-posts' ),
					'error'   => $e->getMessage(),
				]
			);
		}
	}
}
