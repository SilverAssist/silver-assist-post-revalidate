<?php
/**
 * Tests for Plugin class using WordPress test suite.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.0
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
}
