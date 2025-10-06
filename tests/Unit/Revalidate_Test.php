<?php
/**
 * Unit tests for Revalidate class.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 */

namespace RevalidatePosts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RevalidatePosts\Revalidate;
use Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test case for Revalidate class.
 *
 * @since 1.0.0
 */
class Revalidate_Test extends TestCase {
	use AssertIsType;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		
		// Define constants needed for tests.
		if ( ! defined( 'SILVER_ASSIST_REVALIDATE_VERSION' ) ) {
			define( 'SILVER_ASSIST_REVALIDATE_VERSION', '1.0.0' );
		}
		
		// Mock translation function globally to return the text as-is.
		Functions\when( '__' )->returnArg( 1 );
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
		$instance1 = Revalidate::instance();
		$instance2 = Revalidate::instance();

		$this->assertInstanceOf( Revalidate::class, $instance1 );
		$this->assertSame( $instance1, $instance2, 'Revalidate::instance() should return the same instance' );
	}

	/**
	 * Test that revalidation is triggered when post is saved.
	 *
	 * @return void
	 */
	public function test_post_save_triggers_revalidation(): void {
		$post_id = 123;
		$post_permalink = 'https://example.com/blog/test-post/';
		$relative_path = '/blog/test-post/';
		
		// Mock WordPress functions.
		Functions\expect( 'wp_is_post_autosave' )
			->once()
			->with( $post_id )
			->andReturn( false );
		
		Functions\expect( 'wp_is_post_revision' )
			->once()
			->with( $post_id )
			->andReturn( false );
		
		// Create WP_Post instance (now using our stub).
		$mock_post = new \WP_Post(
			(object) [
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
			]
		);
		
		Functions\expect( 'get_post' )
			->once()
			->with( $post_id )
			->andReturn( $mock_post );
		
		Functions\expect( 'get_permalink' )
			->once()
			->with( $post_id )
			->andReturn( $post_permalink );
		
		Functions\expect( 'get_the_category' )
			->once()
			->with( $post_id )
			->andReturn( [] ); // No categories for simplicity.
		
		Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );
		
		// Mock both get_option calls with specific return values (called with 1 param only).
		Functions\when( 'get_option' )->alias( function ( $option ) {
			if ( 'revalidate_endpoint' === $option ) {
				return 'https://api.example.com/revalidate';
			}
			if ( 'revalidate_token' === $option ) {
				return 'test-token';
			}
			return false;
		} );
		
		Functions\expect( 'add_query_arg' )
			->once()
			->andReturn( 'https://api.example.com/revalidate?token=test-token&path=/blog/test-post/' );
		
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'response' => [ 'code' => 200 ] ] );
		
		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );
		
		// Simulate post save.
		$instance = Revalidate::instance();
		$instance->on_post_saved( $post_id );
		
		// If we reach here without exceptions, the test passes.
		$this->assertTrue( true, 'Post save should trigger revalidation process' );
	}

	/**
	 * Test URL to relative path conversion.
	 *
	 * @return void
	 */
	public function test_url_to_relative_path_conversion(): void {
		$full_url = 'https://example.com/blog/my-post/';
		$expected_path = '/blog/my-post/';
		
		// wp_parse_url() is a native PHP function, not WordPress - it works without mocks.
		$result = parse_url( $full_url, PHP_URL_PATH );
		
		$this->assertSame( $expected_path, $result, 'Full URL should be converted to relative path' );
	}

	/**
	 * Test that draft posts do not trigger revalidation.
	 *
	 * @return void
	 */
	public function test_draft_post_does_not_trigger_revalidation(): void {
		$post_id = 456;
		
		Functions\expect( 'wp_is_post_autosave' )
			->once()
			->with( $post_id )
			->andReturn( false );
		
		Functions\expect( 'wp_is_post_revision' )
			->once()
			->with( $post_id )
			->andReturn( false );
		
		// Create draft WP_Post instance.
		$mock_draft_post = new \WP_Post(
			(object) [
				'ID'          => $post_id,
				'post_status' => 'draft',
				'post_type'   => 'post',
				'post_title'  => 'Draft Post',
			]
		);
		
		Functions\expect( 'get_post' )
			->once()
			->with( $post_id )
			->andReturn( $mock_draft_post );
		
		// Should NOT call get_permalink or wp_remote_get for draft posts.
		Functions\expect( 'get_permalink' )
			->never();
		
		Functions\expect( 'wp_remote_get' )
			->never();
		
		$instance = Revalidate::instance();
		$instance->on_post_saved( $post_id );
		
		$this->assertTrue( true, 'Draft posts should not trigger revalidation' );
	}

	/**
	 * Test that revalidation handles empty endpoint gracefully.
	 *
	 * @return void
	 */
	public function test_empty_endpoint_skips_revalidation(): void {
		$post_id = 789;
		$post_permalink = 'https://example.com/test/';
		
		Functions\expect( 'wp_is_post_autosave' )
			->once()
			->andReturn( false );
		
		Functions\expect( 'wp_is_post_revision' )
			->once()
			->andReturn( false );
		
		// Create WP_Post instance.
		$mock_post = new \WP_Post(
			(object) [
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
			]
		);
		
		Functions\expect( 'get_post' )
			->once()
			->andReturn( $mock_post );
		
		Functions\expect( 'get_permalink' )
			->once()
			->andReturn( $post_permalink );
		
		Functions\expect( 'get_the_category' )
			->once()
			->andReturn( [] );
		
		Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );
		
		// Mock get_option to return false for endpoint (simulates empty config).
		Functions\when( 'get_option' )->justReturn( false );
		
		// These should NEVER be called after empty endpoint check.
		Functions\expect( 'wp_remote_get' )
			->never();
		
		$instance = Revalidate::instance();
		$instance->on_post_saved( $post_id );
		
		$this->assertTrue( true, 'Empty endpoint should skip revalidation' );
	}
}
