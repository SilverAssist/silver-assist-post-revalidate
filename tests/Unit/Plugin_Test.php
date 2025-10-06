<?php
/**
 * Unit tests for Plugin class.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 */

namespace RevalidatePosts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RevalidatePosts\Plugin;
use Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test case for Plugin class.
 *
 * @since 1.0.0
 */
class Plugin_Test extends TestCase {
	use AssertIsType;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		
		// Mock is_admin to return false for unit tests.
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
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
}
