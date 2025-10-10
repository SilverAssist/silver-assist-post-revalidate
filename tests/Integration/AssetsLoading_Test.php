<?php
/**
 * Asset Management Tests
 *
 * Tests that CSS and JavaScript files are loaded correctly on appropriate pages
 * and NOT loaded on front-end or irrelevant admin pages.
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
 * Test asset management
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class AssetsLoading_Test extends WP_UnitTestCase
{
	/**
	 * AdminSettings instance
	 *
	 * @var AdminSettings
	 */
	private $admin_settings;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->admin_settings = AdminSettings::instance();
		
		// Set current user as administrator.
		\wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Dequeue styles and scripts to prevent cross-test contamination.
		\wp_dequeue_style( 'silver-assist-debug-logs' );
		\wp_dequeue_script( 'silver-assist-debug-logs' );
		\wp_deregister_style( 'silver-assist-debug-logs' );
		\wp_deregister_script( 'silver-assist-debug-logs' );
		
		parent::tearDown();
	}

	/**
	 * Test debug logs CSS loads on settings page
	 *
	 * Verifies that the debug logs CSS file is enqueued
	 * when on the plugin's settings page.
	 *
	 * @return void
	 */
	public function test_debug_logs_css_loads_on_settings_page(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Verify CSS is enqueued.
		$this->assertTrue(
			\wp_style_is( 'silver-assist-debug-logs', 'enqueued' ),
			'Debug logs CSS should be enqueued on settings page'
		);
	}

	/**
	 * Test debug logs JS loads on settings page
	 *
	 * Verifies that the debug logs JavaScript file is enqueued
	 * when on the plugin's settings page.
	 *
	 * @return void
	 */
	public function test_debug_logs_js_loads_on_settings_page(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Verify JavaScript is enqueued.
		$this->assertTrue(
			\wp_script_is( 'silver-assist-debug-logs', 'enqueued' ),
			'Debug logs JS should be enqueued on settings page'
		);
	}

	/**
	 * Test assets NOT loaded on other admin pages
	 *
	 * Verifies that plugin assets are NOT loaded on other
	 * admin pages like posts, dashboard, etc.
	 *
	 * @return void
	 */
	public function test_assets_not_loaded_on_other_admin_pages(): void {
		// Simulate being on a different admin page.
		$hook = 'edit.php';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Verify CSS is NOT enqueued.
		$this->assertFalse(
			\wp_style_is( 'silver-assist-debug-logs', 'enqueued' ),
			'Debug logs CSS should NOT be enqueued on other admin pages'
		);
		
		// Verify JavaScript is NOT enqueued.
		$this->assertFalse(
			\wp_script_is( 'silver-assist-debug-logs', 'enqueued' ),
			'Debug logs JS should NOT be enqueued on other admin pages'
		);
	}

	/**
	 * Test CSS has version string for cache busting
	 *
	 * Verifies that the CSS file URL includes a version parameter
	 * to prevent browser caching issues.
	 *
	 * @return void
	 */
	public function test_css_has_version_string(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Get registered style.
		global $wp_styles;
		$style = $wp_styles->registered['silver-assist-debug-logs'] ?? null;
		
		// Verify style exists.
		$this->assertNotNull( $style, 'Debug logs CSS should be registered' );
		
		// Verify version is set.
		$this->assertNotEmpty( $style->ver, 'CSS should have version string' );
		
		// Verify version matches plugin version.
		$this->assertSame(
			SILVER_ASSIST_REVALIDATE_VERSION,
			$style->ver,
			'CSS version should match plugin version'
		);
	}

	/**
	 * Test JS has version string for cache busting
	 *
	 * Verifies that the JavaScript file URL includes a version parameter
	 * to prevent browser caching issues.
	 *
	 * @return void
	 */
	public function test_js_has_version_string(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Get registered script.
		global $wp_scripts;
		$script = $wp_scripts->registered['silver-assist-debug-logs'] ?? null;
		
		// Verify script exists.
		$this->assertNotNull( $script, 'Debug logs JS should be registered' );
		
		// Verify version is set.
		$this->assertNotEmpty( $script->ver, 'JS should have version string' );
		
		// Verify version matches plugin version.
		$this->assertSame(
			SILVER_ASSIST_REVALIDATE_VERSION,
			$script->ver,
			'JS version should match plugin version'
		);
	}

	/**
	 * Test localized script data structure
	 *
	 * Verifies that the localized script data contains
	 * all required fields for AJAX functionality.
	 *
	 * @return void
	 */
	public function test_localized_script_data_structure(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Get localized data.
		global $wp_scripts;
		$script = $wp_scripts->registered['silver-assist-debug-logs'] ?? null;
		
		// Verify script exists.
		$this->assertNotNull( $script, 'Debug logs JS should be registered' );
		
		// Check for localized data.
		$localized = $script->extra['data'] ?? '';
		
		// Verify localized data is not empty.
		$this->assertNotEmpty( $localized, 'Script should have localized data' );
		
		// Verify it contains the expected variable name.
		$this->assertStringContainsString(
			'silverAssistDebugLogs',
			$localized,
			'Localized data should contain silverAssistDebugLogs variable'
		);
	}

	/**
	 * Test nonce in localized data
	 *
	 * Verifies that the localized script data includes
	 * a nonce for AJAX security.
	 *
	 * @return void
	 */
	public function test_nonce_in_localized_data(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Get localized data.
		global $wp_scripts;
		$script = $wp_scripts->registered['silver-assist-debug-logs'] ?? null;
		
		// Verify script exists.
		$this->assertNotNull( $script, 'Debug logs JS should be registered' );
		
		// Check for localized data.
		$localized = $script->extra['data'] ?? '';
		
		// Verify nonce is present in localized data.
		$this->assertStringContainsString(
			'"nonce"',
			$localized,
			'Localized data should contain nonce field'
		);
	}

	/**
	 * Test jQuery dependency is declared
	 *
	 * Verifies that the JavaScript file properly declares
	 * jQuery as a dependency.
	 *
	 * @return void
	 */
	public function test_jquery_dependency_declared(): void {
		// Simulate being on settings page.
		$hook = 'settings_page_silver-assist-revalidate';
		
		// Trigger enqueue action.
		$this->admin_settings->enqueue_admin_scripts( $hook );
		
		// Get registered script.
		global $wp_scripts;
		$script = $wp_scripts->registered['silver-assist-debug-logs'] ?? null;
		
		// Verify script exists.
		$this->assertNotNull( $script, 'Debug logs JS should be registered' );
		
		// Verify jQuery dependency.
		$this->assertContains(
			'jquery',
			$script->deps,
			'Debug logs JS should declare jQuery as dependency'
		);
	}
}
