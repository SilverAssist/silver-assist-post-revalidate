<?php
/**
 * Status Transitions Tests
 *
 * Tests that post status transitions trigger revalidation appropriately.
 * Covers publish→trash, trash→publish, draft→draft (no revalidation),
 * pending→publish, future→publish, and hook parameter validation.
 *
 * @package    RevalidatePosts
 * @subpackage Tests\Integration
 * @since      1.2.3
 * @author     Silver Assist
 * @version    1.2.3
 */

namespace RevalidatePosts\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * Test post status transitions
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class StatusTransitions_Test extends WP_UnitTestCase
{
	/**
	 * Revalidate instance
	 *
	 * @var Revalidate
	 */
	private $revalidate;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->revalidate = Revalidate::instance();
		$this->revalidate->set_cooldown_disabled( true );
		$this->revalidate->reset_processed_posts();
		
		// Clear logs before each test.
		Revalidate::clear_logs();
		
		// Configure endpoint and token.
		\update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		\update_option( 'revalidate_token', 'test-token-123' );
		
		// Mock HTTP requests to prevent real API calls.
		\add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		\remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		
		// Clean up options.
		\delete_option( 'revalidate_endpoint' );
		\delete_option( 'revalidate_token' );
		Revalidate::clear_logs();
		
		parent::tearDown();
	}

	/**
	 * Mock HTTP request to prevent real API calls
	 *
	 * @param false  $preempt A preemptive return value of an HTTP request.
	 * @param array  $args HTTP request arguments.
	 * @param string $url The request URL.
	 * @return array Fake HTTP response.
	 */
	public function mock_http_request( $preempt, $args, $url ): array {
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => '{"revalidated":true}',
		];
	}

	/**
	 * Test Publish → Trash triggers revalidation
	 *
	 * When a published post is moved to trash, the cache
	 * must be revalidated to remove it from listings.
	 *
	 * @return void
	 */
	public function test_publish_to_trash_triggers_revalidation(): void {
		// Create a published post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post to Trash',
			]
		);
		
		// Clear logs from post creation.
		$this->revalidate->clear_logs();
		
		// Trash the post.
		\wp_trash_post( $post_id );
		
		// Verify revalidation was triggered.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		$this->assertNotEmpty( $logs, 'Publish → Trash should trigger revalidation' );
		$this->assertGreaterThan( 0, count( $logs ), 'Should have at least one log entry' );
	}

	/**
	 * Test Trash → Publish triggers revalidation
	 *
	 * When a trashed post is restored to publish status,
	 * the cache must be revalidated to add it back to listings.
	 *
	 * @return void
	 */
	public function test_trash_to_publish_triggers_revalidation(): void {
		// Create a published post and trash it.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post from Trash',
			]
		);
		
		\wp_trash_post( $post_id );
		
		// Clear logs from creation and trashing.
		$this->revalidate->clear_logs();
		
		// Restore from trash (WordPress restores to draft by default).
		\wp_untrash_post( $post_id );
		
		// Manually publish to trigger status transition.
		\wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);
		
		// Verify revalidation was triggered.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		$this->assertNotEmpty( $logs, 'Trash → Publish should trigger revalidation' );
		$this->assertGreaterThan( 0, count( $logs ), 'Should have at least one log entry' );
	}

	/**
	 * Test Draft → Draft does NOT trigger revalidation
	 *
	 * When a draft post is updated but remains a draft,
	 * no revalidation should occur (no public cache impact).
	 *
	 * @return void
	 */
	public function test_draft_to_draft_no_revalidation(): void {
		// Create a draft post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'draft',
				'post_title'  => 'Test Draft Post',
			]
		);
		
		// Clear logs from creation.
		$this->revalidate->clear_logs();
		
		// Update the draft post (status unchanged).
		\wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated Draft Post',
			]
		);
		
		// Verify NO revalidation was triggered.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		$this->assertEmpty( $logs, 'Draft → Draft should NOT trigger revalidation' );
	}

	/**
	 * Test Pending → Publish triggers revalidation
	 *
	 * When a pending post is approved and published,
	 * the cache must be revalidated to show the new content.
	 *
	 * @return void
	 */
	public function test_pending_to_publish_triggers_revalidation(): void {
		// Create a pending post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'pending',
				'post_title'  => 'Test Pending Post',
			]
		);
		
		// Clear logs from creation.
		$this->revalidate->clear_logs();
		
		// Publish the post.
		\wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);
		
		// Verify revalidation was triggered.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		$this->assertNotEmpty( $logs, 'Pending → Publish should trigger revalidation' );
		$this->assertGreaterThan( 0, count( $logs ), 'Should have at least one log entry' );
	}

	/**
	 * Test Future (scheduled) → Publish triggers revalidation
	 *
	 * When a scheduled post is published (future → publish),
	 * the cache must be revalidated to show the new content.
	 *
	 * @return void
	 */
	public function test_future_to_publish_triggers_revalidation(): void {
		// Create a scheduled post.
		$future_date = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'future',
				'post_date'   => $future_date,
				'post_title'  => 'Test Future Post',
			]
		);
		
		// Clear logs from creation.
		$this->revalidate->clear_logs();
		
		// Manually trigger status transition (simulating WordPress cron).
		\wp_publish_post( $post_id );
		
		// Verify revalidation was triggered.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		$this->assertNotEmpty( $logs, 'Future → Publish should trigger revalidation' );
		$this->assertGreaterThan( 0, count( $logs ), 'Should have at least one log entry' );
	}

	/**
	 * Test status transition hook receives 3 arguments
	 *
	 * WordPress transition_post_status action passes 3 arguments:
	 * $new_status, $old_status, $post. This verifies the hook is
	 * registered with correct priority and argument count.
	 *
	 * @return void
	 */
	public function test_status_transition_hook_has_correct_arguments(): void {
		global $wp_filter;
		
		// Verify the hook is registered.
		$this->assertArrayHasKey(
			'transition_post_status',
			$wp_filter,
			'transition_post_status hook should be registered'
		);
		
		// Get the callbacks for this hook.
		$callbacks = $wp_filter['transition_post_status']->callbacks ?? [];
		
		// Find our callback at priority 10.
		$this->assertArrayHasKey( 10, $callbacks, 'Callback should be at priority 10' );
		
		// Find the Revalidate callback.
		$found = false;
		foreach ( $callbacks[10] as $callback ) {
			if ( isset( $callback['function'][0] ) && $callback['function'][0] instanceof Revalidate ) {
				$found = true;
				
				// Verify it accepts 3 arguments.
				$this->assertSame(
					3,
					$callback['accepted_args'],
					'transition_post_status callback should accept 3 arguments'
				);
				break;
			}
		}
		
		$this->assertTrue( $found, 'Revalidate callback should be registered on transition_post_status' );
	}
}
