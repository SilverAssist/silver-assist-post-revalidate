<?php
/**
 * Tests for Revalidate class using WordPress test suite.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @version 1.2.0
 */

namespace RevalidatePosts\Tests\Unit;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * Test case for Revalidate class using real WordPress environment.
 *
 * @since 1.0.0
 */
class Revalidate_Test extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Test category ID.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * Set up before class runs.
	 *
	 * @param mixed $factory Factory instance.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		// Create test post.
		self::$post_id = $factory->post->create(
[
'post_title'  => 'Test Post for Revalidation',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		// Create test category.
		self::$category_id = $factory->category->create(
[
'name' => 'Test Category',
				'slug' => 'test-category',
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
		$instance1 = Revalidate::instance();
		$instance2 = Revalidate::instance();

		$this->assertInstanceOf( Revalidate::class, $instance1 );
		$this->assertSame( $instance1, $instance2, 'Revalidate::instance() should return the same instance' );
	}

	/**
	 * Test that draft posts do not trigger revalidation.
	 *
	 * @return void
	 */
	public function test_draft_post_does_not_trigger_revalidation(): void {
		$draft_post_id = $this->factory->post->create(
[
'post_title'  => 'Draft Post',
				'post_status' => 'draft',
				'post_type'   => 'post',
			]
		);

		// Configure endpoint and token.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( $draft_post_id );

		// Check that no logs were created for draft post.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs, 'Draft posts should not trigger revalidation' );
	}

	/**
	 * Test that published posts trigger revalidation.
	 *
	 * @return void
	 */
	public function test_published_post_creates_log_entry(): void {
		// Configure endpoint and token.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		// Check that log was created.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Published post should create log entry' );
		$this->assertCount( 1, $logs, 'Should have exactly one log entry' );
	}

	/**
	 * Test that log entry contains required fields.
	 *
	 * @return void
	 */
	public function test_log_entry_contains_required_fields(): void {
		// Clear existing logs.
		delete_option( 'silver_assist_revalidate_logs' );

		// Configure endpoint and token.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertCount( 1, $logs );

		$log = $logs[0];
		$this->assertArrayHasKey( 'timestamp', $log );
		$this->assertArrayHasKey( 'path', $log );
		$this->assertArrayHasKey( 'status', $log );
		$this->assertArrayHasKey( 'status_code', $log );
		$this->assertArrayHasKey( 'request', $log );
		$this->assertArrayHasKey( 'response', $log );
	}

	/**
	 * Test that logs respect FIFO rotation (max 100 entries).
	 *
	 * @return void
	 */
	public function test_logs_respect_fifo_rotation(): void {
		// Create 100 log entries.
		$logs = [];
		for ( $i = 0; $i < 100; $i++ ) {
			$logs[] = [
				'timestamp'   => current_time( 'mysql' ),
				'path'        => "/post-{$i}/",
				'status'      => 'success',
				'status_code' => 200,
				'request'     => [],
				'response'    => [],
			];
		}
		update_option( 'silver_assist_revalidate_logs', $logs );

		// Configure and trigger one more revalidation.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		// Check that we still have max 100 entries.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertCount( 100, $logs, 'Should maintain max 100 log entries (FIFO)' );

		// Newest log should be first.
		$newest_log = $logs[0];
		$this->assertStringContainsString( 'test-post-for-revalidation', $newest_log['path'] );
	}

	/**
	 * Test clearing logs.
	 *
	 * @return void
	 */
	public function test_clear_logs(): void {
		// Create test logs.
		$logs = [
			[
				'timestamp'   => current_time( 'mysql' ),
				'path'        => '/test/',
				'status'      => 'success',
				'status_code' => 200,
				'request'     => [],
				'response'    => [],
			],
		];
		update_option( 'silver_assist_revalidate_logs', $logs );

		// Clear logs.
		$result = Revalidate::clear_logs();

		$this->assertTrue( $result );

		// Verify logs are cleared.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs );
	}

	/**
	 * Test that empty endpoint skips revalidation.
	 *
	 * @return void
	 */
	public function test_empty_endpoint_skips_revalidation(): void {
		// Clear endpoint and token.
		delete_option( 'revalidate_endpoint' );
		delete_option( 'revalidate_token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		// Check that no logs were created.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs, 'Empty endpoint should skip revalidation' );
	}

	/**
	 * Test category save triggers revalidation.
	 *
	 * @return void
	 */
	public function test_category_save_triggers_revalidation(): void {
		// Configure endpoint and token.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_category_saved( self::$category_id );

		// Check that log was created.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Category save should create log entry' );
	}

	/**
	 * Test URL to relative path conversion.
	 *
	 * @return void
	 */
	public function test_url_to_relative_path_conversion(): void {
		$full_url = 'https://example.com/blog/my-post/'\;
		$expected_path = '/blog/my-post/';
		
		$result = parse_url( $full_url, PHP_URL_PATH );
		
		$this->assertSame( $expected_path, $result, 'Full URL should be converted to relative path' );
	}
}
