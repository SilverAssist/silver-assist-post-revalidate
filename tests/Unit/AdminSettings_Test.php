<?php
/**
 * Tests for AdminSettings class using WordPress test suite.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.1
 */

namespace RevalidatePosts\Tests\Unit;

use RevalidatePosts\AdminSettings;
use WP_UnitTestCase;

/**
 * Test case for AdminSettings class using real WordPress environment.
 *
 * @since 1.0.0
 */
class AdminSettings_Test extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private static $admin_user_id;

	/**
	 * Set up before class runs.
	 *
	 * @param mixed $factory Factory instance.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$admin_user_id = $factory->user->create(
[
'role' => 'administrator',
			]
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'revalidate_endpoint' );
		delete_option( 'revalidate_token' );
		delete_option( 'silver_assist_revalidate_logs' );
		parent::tearDown();
	}

	/**
	 * Test singleton instance creation.
	 *
	 * @return void
	 */
	public function test_instance_returns_singleton(): void {
		$instance1 = AdminSettings::instance();
		$instance2 = AdminSettings::instance();

		$this->assertInstanceOf( AdminSettings::class, $instance1 );
		$this->assertSame( $instance1, $instance2, 'AdminSettings::instance() should return the same instance' );
	}

	/**
	 * Test saving revalidate endpoint option.
	 *
	 * @return void
	 */
	public function test_saves_revalidate_endpoint_correctly(): void {
		$test_endpoint = 'https://example.com/api/revalidate';
		
		update_option( 'revalidate_endpoint', $test_endpoint );
		$saved_value = get_option( 'revalidate_endpoint', '' );
		
		$this->assertSame( $test_endpoint, $saved_value, 'Endpoint should be saved and retrieved correctly' );
	}

	/**
	 * Test saving revalidate token option.
	 *
	 * @return void
	 */
	public function test_saves_revalidate_token_correctly(): void {
		$test_token = 'test-secret-token-12345';
		
		update_option( 'revalidate_token', $test_token );
		$saved_value = get_option( 'revalidate_token', '' );
		
		$this->assertSame( $test_token, $saved_value, 'Token should be saved and retrieved correctly' );
	}

	/**
	 * Test sanitization of malicious endpoint URL.
	 *
	 * @return void
	 */
	public function test_sanitizes_malicious_endpoint_url(): void {
		$malicious_input = 'javascript:alert("XSS")';
		$sanitized_output = sanitize_url( $malicious_input );
		
		$this->assertSame( '', $sanitized_output, 'Malicious URL should be sanitized to empty string' );
	}

	/**
	 * Test empty options return default values.
	 *
	 * @return void
	 */
	public function test_empty_options_return_defaults(): void {
		$endpoint = get_option( 'revalidate_endpoint', '' );
		$token    = get_option( 'revalidate_token', '' );
		
		$this->assertSame( '', $endpoint, 'Empty endpoint should return empty string' );
		$this->assertSame( '', $token, 'Empty token should return empty string' );
	}

	/**
	 * Test that settings are registered properly.
	 *
	 * @return void
	 */
	public function test_settings_are_registered(): void {
		global $wp_registered_settings;
		
		$instance = AdminSettings::instance();
		$instance->register_settings();
		
		$this->assertArrayHasKey( 'revalidate_endpoint', $wp_registered_settings );
		$this->assertArrayHasKey( 'revalidate_token', $wp_registered_settings );
	}

	/**
	 * Test that logs can be cleared successfully.
	 *
	 * @return void
	 */
	public function test_logs_can_be_cleared(): void {
		// Create test logs.
		$test_logs = [
			[
				'timestamp'   => current_time( 'mysql' ),
				'path'        => '/test-post/',
				'status'      => 'success',
				'status_code' => 200,
				'request'     => [ 'url' => 'https://api.example.com' ],
				'response'    => [ 'body' => 'OK' ],
			],
		];
		
		update_option( 'silver_assist_revalidate_logs', $test_logs );
		
		// Verify logs exist.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertCount( 1, $logs );
		
		// Clear logs.
		$result = \RevalidatePosts\Revalidate::clear_logs();
		
		$this->assertTrue( $result );
		
		// Verify logs are empty.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs );
	}

	/**
	 * Test that render_check_updates_script enqueues external JavaScript file.
	 *
	 * Tests that the method properly enqueues the external JavaScript file
	 * with localized data following WordPress best practices.
	 *
	 * @return void
	 */
	public function test_render_check_updates_script_enqueues_script(): void {
		$instance = AdminSettings::instance();

		// Call the method (doesn't output anything).
		$instance->render_check_updates_script();

		// Verify script is enqueued.
		$this->assertTrue( wp_script_is( 'silver-assist-check-updates', 'enqueued' ) );
		
		// Verify localized data is present.
		global $wp_scripts;
		$this->assertArrayHasKey( 'silver-assist-check-updates', $wp_scripts->registered );
		
		$localized_data = $wp_scripts->registered['silver-assist-check-updates']->extra['data'] ?? '';
		$this->assertStringContainsString( 'silverAssistCheckUpdatesData', $localized_data );
		$this->assertStringContainsString( 'ajaxurl', $localized_data );
		$this->assertStringContainsString( 'nonce', $localized_data );
		$this->assertStringContainsString( 'updateUrl', $localized_data );
	}

	/**
	 * Test that admin scripts are enqueued on settings page.
	 *
	 * @return void
	 */
	public function test_admin_scripts_enqueued_on_settings_page(): void {
		wp_set_current_user( self::$admin_user_id );
		
		$instance = AdminSettings::instance();
		$instance->enqueue_admin_scripts( 'toplevel_page_silver-assist-revalidate' );
		
		$this->assertTrue( wp_style_is( 'silver-assist-debug-logs', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'silver-assist-debug-logs', 'enqueued' ) );
	}
}
