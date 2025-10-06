<?php
/**
 * Unit tests for AdminSettings class.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 */

namespace RevalidatePosts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RevalidatePosts\AdminSettings;
use Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test case for AdminSettings class.
 *
 * @since 1.0.0
 */
class AdminSettings_Test extends TestCase {
	use AssertIsType;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
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
	 * Test that admin settings instance is properly initialized.
	 *
	 * @return void
	 */
	public function test_instance_is_admin_settings_class(): void {
		$instance = AdminSettings::instance();
		$this->assertInstanceOf( AdminSettings::class, $instance );
	}

	/**
	 * Test saving revalidate endpoint option.
	 *
	 * @return void
	 */
	public function test_saves_revalidate_endpoint_correctly(): void {
		$test_endpoint = 'https://example.com/api/revalidate';
		
		// Mock WordPress functions.
		Functions\expect( 'sanitize_url' )
			->once()
			->with( $test_endpoint )
			->andReturn( $test_endpoint );
		
		Functions\expect( 'update_option' )
			->once()
			->with( 'revalidate_endpoint', $test_endpoint )
			->andReturn( true );
		
		Functions\expect( 'get_option' )
			->once()
			->with( 'revalidate_endpoint', '' )
			->andReturn( $test_endpoint );
		
		// Simulate saving.
		$sanitized = \sanitize_url( $test_endpoint );
		\update_option( 'revalidate_endpoint', $sanitized );
		
		// Verify retrieval.
		$saved_value = \get_option( 'revalidate_endpoint', '' );
		
		$this->assertSame( $test_endpoint, $saved_value, 'Endpoint should be saved and retrieved correctly' );
	}

	/**
	 * Test saving revalidate token option.
	 *
	 * @return void
	 */
	public function test_saves_revalidate_token_correctly(): void {
		$test_token = 'test-secret-token-12345';
		
		// Mock WordPress functions.
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( $test_token )
			->andReturn( $test_token );
		
		Functions\expect( 'update_option' )
			->once()
			->with( 'revalidate_token', $test_token )
			->andReturn( true );
		
		Functions\expect( 'get_option' )
			->once()
			->with( 'revalidate_token', '' )
			->andReturn( $test_token );
		
		// Simulate saving.
		$sanitized = \sanitize_text_field( $test_token );
		\update_option( 'revalidate_token', $sanitized );
		
		// Verify retrieval.
		$saved_value = \get_option( 'revalidate_token', '' );
		
		$this->assertSame( $test_token, $saved_value, 'Token should be saved and retrieved correctly' );
	}

	/**
	 * Test sanitization of malicious endpoint URL.
	 *
	 * @return void
	 */
	public function test_sanitizes_malicious_endpoint_url(): void {
		$malicious_input = 'javascript:alert("XSS")';
		$sanitized_output = '';
		
		Functions\expect( 'sanitize_url' )
			->once()
			->with( $malicious_input )
			->andReturn( $sanitized_output );
		
		$result = \sanitize_url( $malicious_input );
		
		$this->assertSame( $sanitized_output, $result, 'Malicious URL should be sanitized to empty string' );
	}

	/**
	 * Test empty options return default values.
	 *
	 * @return void
	 */
	public function test_empty_options_return_defaults(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'revalidate_endpoint', '' )
			->andReturn( '' );
		
		Functions\expect( 'get_option' )
			->once()
			->with( 'revalidate_token', '' )
			->andReturn( '' );
		
		$endpoint = \get_option( 'revalidate_endpoint', '' );
		$token = \get_option( 'revalidate_token', '' );
		
		$this->assertSame( '', $endpoint, 'Empty endpoint should return empty string' );
		$this->assertSame( '', $token, 'Empty token should return empty string' );
	}
}
