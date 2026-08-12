<?php
/**
 * Tests for Plugin class using WordPress test suite.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.1
 */

namespace RevalidatePosts\Tests\Unit;

use RevalidatePosts\AdminSettings;
use RevalidatePosts\ManualRevalidation;
use RevalidatePosts\Plugin;
use RevalidatePosts\Revalidate;
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
		// Settings Hub integration uses admin.php instead of options-general.php.
		$this->assertStringContainsString( 'admin.php', $result[0] );
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
	 * Test that Revalidate::instance() returns a Revalidate instance.
	 *
	 * Since silverassist/wp-plugin-kernel adoption, each component is
	 * reachable directly via its own singleton rather than only through
	 * Plugin's accessors.
	 *
	 * @return void
	 */
	public function test_revalidate_instance_returns_instance(): void {
		$this->assertInstanceOf( Revalidate::class, Revalidate::instance() );
	}

	/**
	 * Test that AdminSettings::instance() returns an AdminSettings instance.
	 *
	 * AdminSettings::instance() is always constructible directly (lazy
	 * singleton) — is_admin() only gates whether the plugin's own
	 * bootstrap loads and initializes it automatically via should_load(),
	 * not whether the class can be instantiated at all.
	 *
	 * @return void
	 */
	public function test_admin_settings_instance_returns_instance(): void {
		$this->assertInstanceOf( AdminSettings::class, AdminSettings::instance() );
	}

	/**
	 * Test that the deprecated Plugin::get_revalidate() forwarding
	 * accessor still works.
	 *
	 * @return void
	 */
	public function test_deprecated_get_revalidate_forwards_to_instance(): void {
		$this->assertSame( Revalidate::instance(), Plugin::instance()->get_revalidate() );
	}

	/**
	 * Test that the deprecated Plugin::get_admin_settings() forwarding
	 * accessor preserves the pre-1.8.0 null-outside-admin contract.
	 *
	 * @return void
	 */
	public function test_deprecated_get_admin_settings_returns_null_outside_admin(): void {
		$this->assertFalse( is_admin(), 'Precondition: this test runs outside admin context' );
		$this->assertNull( Plugin::instance()->get_admin_settings() );
	}

	/**
	 * Test that Plugin::get_components() lists the admin-only components.
	 *
	 * Regression coverage for the loader wiring itself: AdminSettings and
	 * ManualRevalidation must actually be registered with the plugin's
	 * component loader, not just implement LoadableInterface in isolation.
	 *
	 * @return void
	 */
	public function test_get_components_includes_admin_only_components(): void {
		$method = new \ReflectionMethod( Plugin::class, 'get_components' );
		$method->setAccessible( true );
		$components = $method->invoke( Plugin::instance() );

		$this->assertContains( Revalidate::class, $components );
		$this->assertContains( AdminSettings::class, $components );
		$this->assertContains( ManualRevalidation::class, $components );
	}

	/**
	 * Test that AdminSettings/ManualRevalidation should_load() correctly
	 * tracks is_admin(), both outside and inside admin context.
	 *
	 * This is the actual gate AbstractPlugin::load_components() relies on
	 * to decide whether to initialize these components — a regression
	 * here would silently break admin functionality without any of the
	 * other component test suites (which instantiate singletons directly,
	 * bypassing the loader) noticing.
	 *
	 * @return void
	 */
	public function test_admin_only_components_should_load_tracks_is_admin(): void {
		$this->assertFalse( is_admin(), 'Precondition: this test starts outside admin context' );
		$this->assertFalse( AdminSettings::instance()->should_load() );
		$this->assertFalse( ManualRevalidation::instance()->should_load() );

		set_current_screen( 'dashboard' );

		try {
			$this->assertTrue( is_admin(), 'Precondition: set_current_screen() should switch to admin context' );
			$this->assertTrue( AdminSettings::instance()->should_load() );
			$this->assertTrue( ManualRevalidation::instance()->should_load() );
		} finally {
			set_current_screen( 'front' );
		}
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
	 * Test that Revalidate's hooks are registered by the real plugin bootstrap.
	 *
	 * Deliberately does NOT call Plugin::instance()->init() here: doing so
	 * would make this test pass even if the real plugins_loaded bootstrap
	 * failed to initialize the plugin, since init() is idempotent and
	 * would silently repair the missing initialization right before the
	 * assertion. This must rely solely on tests/bootstrap.php having
	 * already loaded the plugin the same way production does (see
	 * muplugins_loaded → plugins_loaded → Plugin::instance()->init()).
	 *
	 * Revalidate::should_load() is always true, so it loads unconditionally
	 * as part of the real plugin bootstrap. AdminSettings/ManualRevalidation
	 * are admin-only (should_load() === is_admin()) and are covered by
	 * their own test suites instead, since is_admin() is false in this
	 * CLI test context.
	 *
	 * @return void
	 */
	public function test_revalidate_component_is_initialized(): void {
		$this->assertNotFalse(
			has_action( 'save_post', [ Revalidate::instance(), 'on_post_saved' ] ),
			'Revalidate should be initialized and its hooks registered by the real plugin bootstrap'
		);
	}
}
