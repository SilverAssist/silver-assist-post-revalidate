<?php
/**
 * Main Plugin Class
 *
 * Handles plugin initialization and coordinates between different components.
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
 * Plugin initialization class
 *
 * Implements singleton pattern to ensure only one instance exists.
 * Coordinates initialization of Revalidate and AdminSettings classes.
 *
 * @since 1.0.0
 */
class Plugin
{
	/**
	 * Singleton instance
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Revalidate instance
	 *
	 * @var Revalidate|null
	 */
	private ?Revalidate $revalidate = null;

	/**
	 * AdminSettings instance
	 *
	 * @var AdminSettings|null
	 */
	private ?AdminSettings $admin_settings = null;

	/**
	 * Updater instance
	 *
	 * @var Updater|null
	 */
	private ?Updater $updater = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return Plugin
	 */
	public static function instance(): Plugin
	{
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor
	 *
	 * Initializes plugin components.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void
	{
		\add_action( 'init', [ $this, 'load_textdomain' ] );
		\add_filter( 'plugin_action_links_' . \plugin_basename( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' ), [ $this, 'add_settings_link' ] );
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain(): void
	{
		\load_plugin_textdomain(
			'silver-assist-revalidate-posts',
			false,
			dirname( \plugin_basename( (string) SILVER_ASSIST_REVALIDATE_PLUGIN_DIR ) ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_components(): void
	{
		// Initialize revalidation functionality.
		$this->revalidate = Revalidate::instance();

		// Initialize admin settings only in admin area.
		if ( \is_admin() ) {
			$this->admin_settings = AdminSettings::instance();
		}

		// Initialize GitHub updater for automatic updates.
		$this->init_updater();
	}

	/**
	 * Initialize GitHub updater
	 *
	 * Sets up automatic updates from GitHub releases.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_updater(): void
	{
		// Skip updater initialization in test environment.
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'test' === WP_ENVIRONMENT_TYPE ) {
			return;
		}

		// Only initialize updater if the class exists (composer dependency installed).
		if ( class_exists( 'SilverAssist\\WpGithubUpdater\\Updater' ) ) {
			$plugin_file = SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php';
			$github_repo = 'SilverAssist/silver-assist-post-revalidate';

			$this->updater = new Updater( $plugin_file, $github_repo );
		}
	}

	/**
	 * Get Revalidate instance
	 *
	 * @since 1.0.0
	 * @return Revalidate|null
	 */
	public function get_revalidate(): ?Revalidate
	{
		return $this->revalidate;
	}

	/**
	 * Add settings link to plugin action links
	 *
	 * @since 1.0.0
	 * @param array<string> $links Array of plugin action links.
	 * @return array<string> Modified array of plugin action links.
	 */
	public function add_settings_link( array $links ): array
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			\esc_url( \admin_url( 'options-general.php?page=silver-assist-revalidate' ) ),
			\esc_html__( 'Settings', 'silver-assist-revalidate-posts' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get AdminSettings instance
	 *
	 * @since 1.0.0
	 * @return AdminSettings|null
	 */
	public function get_admin_settings(): ?AdminSettings
	{
		return $this->admin_settings;
	}

	/**
	 * Get Updater instance
	 *
	 * @since 1.0.0
	 * @return Updater|null
	 */
	public function get_updater(): ?Updater
	{
		return $this->updater;
	}
}
