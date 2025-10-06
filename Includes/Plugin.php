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
	 * Gets the singleton instance
	 *
	 * @since 1.0.0
	 * @return Plugin The single instance of the Plugin class
	 */
	public static function instance(): Plugin
	{
		if ( ! isset( self::$instance ) ) {
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
	 * Get AdminSettings instance
	 *
	 * @since 1.0.0
	 * @return AdminSettings|null
	 */
	public function get_admin_settings(): ?AdminSettings
	{
		return $this->admin_settings;
	}
}
