<?php
/**
 * Tests for Plugin class using WordPress test suite.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.1
 */

namespace RevalidatePosts\Tests\Unit;

use RevalidatePosts\Plugin;
use WP_UnitTestCase;

/**
 * Test case for Plugin class using real WordPress environment.
 *
 * @since 1.0.0
 */
class Plugin_Test extends WP_UnitTestCase {

	/**
	 * Test singleton instance creation.
	 *
	 * @return void
	 */
	public function test_instance_returns_singleton(): void {
		$instance1 = Plugin::instance();
		$instance2 = Plugin::instance();

		$this->assertInstanceOf( Plugin::class, $instance1 );
		$this->assertSame( $instance1, $instance2, 'Plugin::instance() should return the same instance' );
	}

	/**
	 * Test that plugin instance is properly initialized.
	 *
	 * @return void
	 */
	public function test_instance_is_plugin_class(): void {
		$instance = Plugin::instance();
		$this->assertInstanceOf( Plugin::class, $instance );
	}

	/**
	 * Test add_settings_link method adds settings link to plugin actions.
	 *
	 * @return void
	 */
	public function test_add_settings_link_adds_link(): void {
		$instance = Plugin::instance();
		$links    = [ 'deactivate' => '<a href="#">Deactivate</a>' ];
		$result   = $instance->add_settings_link( $links );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'Settings', $result[0] );
		$this->assertStringContainsString( 'silver-assist-revalidate', $result[0] );
	}

	/**
	 * Test that settings link contains proper URL.
	 *
	 * @return void
	 */
	public function test_settings_link_contains_proper_url(): void {
		$instance = Plugin::instance();
		$links    = [];
		$result   = $instance->add_settings_link( $links );

		$this->assertCount( 1, $result );
		// WordPress generates options-general.php for standalone settings pages.
		$this->assertStringContainsString( 'options-general.php', $result[0] );
		$this->assertStringContainsString( 'page=silver-assist-revalidate', $result[0] );
	}

	/**
	 * Test that settings link is properly escaped.
	 *
	 * @return void
	 */
	public function test_settings_link_is_properly_escaped(): void {
		$instance = Plugin::instance();
		$links    = [];
		$result   = $instance->add_settings_link( $links );

		$this->assertCount( 1, $result );
		$this->assertStringNotContainsString( '<script>', $result[0] );
		$this->assertMatchesRegularExpression( '/<a href="[^"]*"/', $result[0] );
	}

	/**
	 * Test that plugin constants are defined.
	 *
	 * @return void
	 */
	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'SILVER_ASSIST_REVALIDATE_VERSION' ) );
		$this->assertTrue( defined( 'SILVER_ASSIST_REVALIDATE_PLUGIN_DIR' ) );
		$this->assertNotEmpty( SILVER_ASSIST_REVALIDATE_VERSION );
		$this->assertNotEmpty( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR );
	}

	/**
	 * Test that plugin version constant is valid semver.
	 *
	 * @return void
	 */
	public function test_plugin_version_is_valid_semver(): void {
		$version = SILVER_ASSIST_REVALIDATE_VERSION;
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
	}

	/**
	 * Test that get_revalidate returns Revalidate instance.
	 *
	 * @return void
	 */
	public function test_get_revalidate_returns_instance(): void {
		$plugin     = Plugin::instance();
		$revalidate = $plugin->get_revalidate();

		$this->assertInstanceOf( \RevalidatePosts\Revalidate::class, $revalidate );
	}

	/**
	 * Test that get_admin_settings returns AdminSettings instance in admin.
	 *
	 * @return void
	 */
	public function test_get_admin_settings_returns_instance_in_admin(): void {
		// Set admin context.
		set_current_screen( 'dashboard' );

		$plugin = Plugin::instance();
		$admin  = $plugin->get_admin_settings();

		$this->assertInstanceOf( \RevalidatePosts\AdminSettings::class, $admin );
	}

	/**
	 * Test that get_updater returns Updater instance or null.
	 *
	 * @return void
	 */
	public function test_get_updater_returns_instance_or_null(): void {
		$plugin  = Plugin::instance();
		$updater = $plugin->get_updater();

		// Updater can be null in test environment.
		if ( null !== $updater ) {
			$this->assertInstanceOf( \RevalidatePosts\Updater::class, $updater );
		} else {
			$this->assertNull( $updater );
		}
	}

	/**
	 * Test that load_textdomain is callable.
	 *
	 * @return void
	 */
	public function test_load_textdomain_is_callable(): void {
		$plugin = Plugin::instance();
		$this->assertTrue( method_exists( $plugin, 'load_textdomain' ) );
		$this->assertTrue( is_callable( [ $plugin, 'load_textdomain' ] ) );
	}

	/**
	 * Test that plugin hooks are registered.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_are_registered(): void {
		$plugin = Plugin::instance();

		// Test that init hook is registered for textdomain.
		$this->assertNotFalse( has_action( 'init', [ $plugin, 'load_textdomain' ] ) );

		// Test that plugin_action_links filter is registered.
		$filter_name = 'plugin_action_links_' . plugin_basename( SILVER_ASSIST_REVALIDATE_PLUGIN_DIR . 'silver-assist-post-revalidate.php' );
		$this->assertNotFalse( has_filter( $filter_name, [ $plugin, 'add_settings_link' ] ) );
	}

	/**
	 * Test that components are initialized.
	 *
	 * @return void
	 */
	public function test_components_are_initialized(): void {
		$plugin = Plugin::instance();

		// Revalidate should always be initialized.
		$this->assertNotNull( $plugin->get_revalidate() );

		// AdminSettings should be initialized in admin.
		set_current_screen( 'dashboard' );
		$this->assertNotNull( $plugin->get_admin_settings() );
	}
}
