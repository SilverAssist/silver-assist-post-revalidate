<?php
/**
 * Main Plugin Class
 *
 * Handles plugin initialization and coordinates between different components.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.8.0
 * @author Silver Assist
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

use SilverAssist\PluginKernel\AbstractPlugin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin initialization class
 *
 * Singleton access (instance()) and the priority-ordered component loading
 * loop are inherited from AbstractPlugin (silverassist/wp-plugin-kernel) —
 * this class only declares which components to load (get_components()) and
 * the plugin-specific setup that runs alongside them (init_hooks()).
 *
 * @since 1.0.0
 */
class Plugin extends AbstractPlugin
{
	/**
	 * Updater instance
	 *
	 * @var Updater|null
	 */
	private ?Updater $updater = null;

	/**
	 * List the component classes this plugin loads
	 *
	 * Loading order is determined by each component's get_priority(), not
	 * by the order they're listed here.
	 *
	 * @since 1.8.0
	 * @return array<class-string>
	 */
	protected function get_components(): array
	{
		return [
			Revalidate::class,
			AdminSettings::class,
			ManualRevalidation::class,
		];
	}

	/**
	 * Plugin-level setup that isn't itself a LoadableInterface component
	 *
	 * Runs after all components have loaded.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	protected function init_hooks(): void
	{
		\add_action( 'init', [ $this, 'load_textdomain' ] );
		\add_filter( 'plugin_action_links_' . \plugin_basename( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' ), [ $this, 'add_settings_link' ] );

		$this->init_updater();
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
	 * Add settings link to plugin action links
	 *
	 * @since 1.0.1
	 * @param array<string> $links Array of plugin action links.
	 * @return array<string> Modified array of plugin action links.
	 */
	public function add_settings_link( array $links ): array
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			\esc_url( \admin_url( 'admin.php?page=silver-assist-revalidate' ) ),
			\esc_html__( 'Settings', 'silver-assist-revalidate-posts' )
		);

		array_unshift( $links, $settings_link );

		return $links;
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

	/**
	 * Get Revalidate instance
	 *
	 * @deprecated 1.8.0 Call Revalidate::instance() directly instead.
	 * @since 1.0.0
	 * @return Revalidate
	 */
	public function get_revalidate(): Revalidate
	{
		return Revalidate::instance();
	}

	/**
	 * Get AdminSettings instance
	 *
	 * Preserves the pre-1.8.0 contract of returning null outside admin
	 * context. AdminSettings::instance() itself is always constructible
	 * (it's a lazy singleton, not gated by is_admin() at construction
	 * time) — should_load() is what now decides whether the plugin's own
	 * bootstrap initializes it, not whether the class can be instantiated.
	 *
	 * @deprecated 1.8.0 Call AdminSettings::instance() directly instead.
	 * @since 1.0.0
	 * @return AdminSettings|null
	 */
	public function get_admin_settings(): ?AdminSettings
	{
		return \is_admin() ? AdminSettings::instance() : null;
	}
}
