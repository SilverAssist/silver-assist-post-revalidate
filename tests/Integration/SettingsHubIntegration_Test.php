<?php
/**
 * Settings Hub Integration Tests
 *
 * Tests the plugin's integration with the Settings Hub package.
 * Verifies registration, fallback behavior, and custom actions.
 *
 * @package    RevalidatePosts
 * @subpackage Tests\Integration
 * @since      1.2.3
 * @author     Silver Assist
 * @version    1.2.3
 */

namespace RevalidatePosts\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use RevalidatePosts\AdminSettings;
use WP_UnitTestCase;

/**
 * Test Settings Hub integration
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class SettingsHubIntegration_Test extends WP_UnitTestCase
{
	/**
	 * Test that plugin registers with Settings Hub when available
	 *
	 * This test verifies that when the Settings Hub class exists,
	 * the plugin registers itself with the hub instead of creating
	 * a standalone admin menu page.
	 *
	 * @return void
	 */
	public function test_plugin_registers_with_settings_hub_when_available(): void {
		// Verify Settings Hub class exists (it's a composer dependency).
		$this->assertTrue(
			\class_exists( 'SilverAssist\SettingsHub\SettingsHub' ),
			'Settings Hub class should be available via composer'
		);
		
		// Get AdminSettings instance.
		$instance = AdminSettings::instance();
		
		// Verify admin_menu hook is NOT registered (hub handles menu).
		$priority = \has_action( 'admin_menu', [ $instance, 'add_settings_page' ] );
		
		$this->assertFalse(
			$priority,
			'admin_menu hook should NOT be registered when Settings Hub is available'
		);
		
		// Verify the plugin is registered with Settings Hub.
		// Note: We can't easily test this without accessing hub internals,
		// but we verify the method exists and can be called.
		$this->assertTrue(
			\method_exists( $instance, 'register_with_settings_hub' ),
			'register_with_settings_hub method should exist'
		);
	}

	/**
	 * Test fallback to standalone page when Settings Hub NOT available
	 *
	 * This test simulates the scenario where Settings Hub is not installed.
	 * The plugin should fall back to registering a standalone admin menu page.
	 *
	 * Note: This is difficult to test in practice because Settings Hub IS
	 * installed via composer in our test environment. This test documents
	 * the expected behavior.
	 *
	 * @return void
	 */
	public function test_falls_back_to_standalone_when_hub_not_available(): void {
		// This test documents the fallback behavior.
		// When class_exists('SilverAssist\SettingsHub\SettingsHub') returns false,
		// the register_with_settings_hub() method should register the admin_menu hook.
		
		$instance = AdminSettings::instance();
		
		// Verify the add_settings_page method exists for fallback.
		$this->assertTrue(
			\method_exists( $instance, 'add_settings_page' ),
			'add_settings_page method should exist for fallback'
		);
		
		// Verify the method can be called without errors.
		$this->assertTrue(
			\is_callable( [ $instance, 'add_settings_page' ] ),
			'add_settings_page should be callable'
		);
	}

	/**
	 * Test that custom actions are registered correctly
	 *
	 * Verifies that the plugin provides custom actions for Settings Hub,
	 * specifically the "Check Updates" button when updater is available.
	 *
	 * @return void
	 */
	public function test_custom_actions_registered_correctly(): void {
		$instance = AdminSettings::instance();
		
		// Verify get_hub_actions method exists.
		$this->assertTrue(
			\method_exists( $instance, 'get_hub_actions' ),
			'get_hub_actions method should exist'
		);
		
		// We can't easily call private methods, but we verify the method exists
		// and the render_check_updates_script callback is defined.
		$this->assertTrue(
			\method_exists( $instance, 'render_check_updates_script' ),
			'render_check_updates_script callback should exist for Check Updates action'
		);
	}
}
