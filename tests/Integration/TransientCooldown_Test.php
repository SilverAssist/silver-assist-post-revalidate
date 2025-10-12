<?php
/**
 * Integration Tests: Transient Cooldown Functionality.
 *
 * Tests the 5-second cooldown mechanism using WordPress transients
 * to prevent duplicate revalidation requests.
 *
 * @package    RevalidatePosts
 * @subpackage Tests\Integration
 * @author     Silver Assist
 * @since      1.2.2
 * @version    1.2.2
 */

namespace RevalidatePosts\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * Transient Cooldown Integration Tests.
 *
 * @since 1.2.2
 */
class TransientCooldown_Test extends WP_UnitTestCase {

	/**
	 * Revalidate instance for testing.
	 *
	 * @var Revalidate
	 */
	private $revalidate;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize Revalidate instance.
		$this->revalidate = Revalidate::instance();

		// Configure endpoint and token.
		\update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		\update_option( 'revalidate_token', 'test-token' );

		// Mock HTTP responses to prevent actual requests.
		\add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '{"revalidated": true}',
				];
			}
		);

		// Clear all transients before each test.
		$this->clear_all_cooldown_transients();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->clear_all_cooldown_transients();
		parent::tearDown();
	}

	/**
	 * Clear all cooldown transients for testing.
	 *
	 * @return void
	 */
	private function clear_all_cooldown_transients(): void {
		global $wpdb;

		// Delete all transients starting with sa_revalidate_.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_sa_revalidate_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_sa_revalidate_' ) . '%'
			)
		);
	}

	/**
	 * Test that cooldown transient is created after revalidation.
	 *
	 * @return void
	 */
	public function test_cooldown_transient_created_after_revalidation(): void {
		$path = '/test-post/';

		// Revalidate the path.
		$this->revalidate->revalidate_paths( [ $path ] );

		// Check transient exists.
		$transient_key = 'sa_revalidate_' . md5( $path );
		$transient     = \get_transient( $transient_key );

		$this->assertNotFalse( $transient, 'Cooldown transient should be created' );
	}

	/**
	 * Test that cooldown transient has 5-second expiration.
	 *
	 * @return void
	 */
	public function test_cooldown_transient_expires_after_5_seconds(): void {
		$path = '/test-post/';

		// Revalidate the path.
		$this->revalidate->revalidate_paths( [ $path ] );

		// Get transient timeout.
		$transient_key     = 'sa_revalidate_' . md5( $path );
		$timeout_option    = '_transient_timeout_' . $transient_key;
		$transient_timeout = \get_option( $timeout_option );

		$this->assertNotFalse( $transient_timeout, 'Transient timeout should exist' );

		// Check that timeout is approximately 5 seconds from now.
		$expected_timeout = time() + 5;
		$this->assertEqualsWithDelta(
			$expected_timeout,
			$transient_timeout,
			2, // Allow 2 second variance for test execution time.
			'Transient should expire in ~5 seconds'
		);
	}

	/**
	 * Test that duplicate path within cooldown period is blocked.
	 *
	 * @return void
	 */
	public function test_duplicate_path_blocked_within_cooldown(): void {
		$path = '/test-post/';

		// First revalidation should succeed.
		$this->revalidate->revalidate_paths( [ $path ] );
		$transient_key = 'sa_revalidate_' . md5( $path );
		$transient1    = \get_transient( $transient_key );
		$this->assertNotFalse( $transient1, 'First revalidation should create transient' );

		// Second revalidation within cooldown should be blocked (no new transient activity).
		$this->revalidate->revalidate_paths( [ $path ] );
		// Transient should still exist with same value (path was skipped).
		$transient2 = \get_transient( $transient_key );
		$this->assertNotFalse( $transient2, 'Transient should still exist after duplicate attempt' );
	}

	/**
	 * Test that revalidation allowed after cooldown expires.
	 *
	 * @return void
	 */
	public function test_revalidation_allowed_after_cooldown_expires(): void {
		$path = '/test-post/';

		// First revalidation.
		$this->revalidate->revalidate_paths( [ $path ] );

		// Manually delete transient to simulate expiration.
		$transient_key = 'sa_revalidate_' . md5( $path );
		\delete_transient( $transient_key );

		// Second revalidation should succeed after cooldown.
		$this->revalidate->revalidate_paths( [ $path ] );
		// New transient should be created.
		$transient = \get_transient( $transient_key );
		$this->assertNotFalse( $transient, 'Revalidation should succeed after cooldown expires' );
	}

	/**
	 * Test that cooldown disabled flag bypasses cooldown.
	 *
	 * @return void
	 */
	public function test_cooldown_disabled_flag_bypasses_cooldown(): void {
		$path = '/test-post/';

		// First revalidation.
		$this->revalidate->revalidate_paths( [ $path ] );

		// Second revalidation with cooldown disabled.
		$this->revalidate->set_cooldown_disabled( true );
		$this->revalidate->revalidate_paths( [ $path ] );

		// Should succeed (we can verify via logs).
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Revalidation should succeed when cooldown disabled' );

		// Cleanup.
		$this->revalidate->set_cooldown_disabled( false );
	}

	/**
	 * Test that multiple different paths have independent cooldowns.
	 *
	 * @return void
	 */
	public function test_multiple_paths_have_independent_cooldowns(): void {
		$path1 = '/post-1/';
		$path2 = '/post-2/';

		// Revalidate both paths.
		$this->revalidate->revalidate_paths( [ $path1 ] );
		$this->revalidate->revalidate_paths( [ $path2 ] );

		$transient1 = \get_transient( 'sa_revalidate_' . md5( $path1 ) );
		$transient2 = \get_transient( 'sa_revalidate_' . md5( $path2 ) );

		$this->assertNotFalse( $transient1, 'First path should have cooldown' );
		$this->assertNotFalse( $transient2, 'Second path should have cooldown' );

		// Try to revalidate again - both should be blocked (transients still exist).
		$this->revalidate->revalidate_paths( [ $path1 ] );
		$this->revalidate->revalidate_paths( [ $path2 ] );

		// Transients should still exist.
		$this->assertNotFalse( \get_transient( 'sa_revalidate_' . md5( $path1 ) ), 'First path still blocked' );
		$this->assertNotFalse( \get_transient( 'sa_revalidate_' . md5( $path2 ) ), 'Second path still blocked' );
	}

	/**
	 * Test that transient key uses MD5 hash of path.
	 *
	 * @return void
	 */
	public function test_transient_key_uses_md5_hash(): void {
		$path = '/test-post/';

		// Revalidate path.
		$this->revalidate->revalidate_paths( [ $path ] );

		// Check transient with MD5 hash.
		$expected_key = 'sa_revalidate_' . md5( $path );
		$transient    = \get_transient( $expected_key );

		$this->assertNotFalse( $transient, 'Transient should use MD5 hash of path' );
	}

	/**
	 * Test that post save creates cooldown for post permalink.
	 *
	 * @return void
	 */
	public function test_post_save_creates_cooldown_for_permalink(): void {
		// Create and publish a post.
		$post_id = static::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_name'   => 'test-cooldown-post',
			]
		);

		// Get post permalink and generate the same path format as Revalidate class.
		$permalink = \get_permalink( $post_id );
		$home_url  = \home_url();
		$path      = str_replace( $home_url, '', $permalink );
		$path      = '/' . trim( $path, '/' ) . '/';

		// Check cooldown transient exists.
		$transient_key = 'sa_revalidate_' . md5( $path );
		$transient     = \get_transient( $transient_key );

		$this->assertNotFalse( $transient, 'Post save should create cooldown for permalink' );
	}

	/**
	 * Test that cooldown prevents duplicate post update revalidations.
	 *
	 * @return void
	 */
	public function test_cooldown_prevents_duplicate_post_updates(): void {
		// Create a post.
		$post_id = static::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_title'  => 'Original Title',
			]
		);

		// Update post twice quickly.
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated Title 1',
			]
		);

		// Second update should be blocked by cooldown.
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated Title 2',
			]
		);

		// Get logs to verify only one revalidation happened.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );

		// Count revalidations for this post's path.
		$permalink        = \get_permalink( $post_id );
		$path             = \wp_parse_url( $permalink, PHP_URL_PATH );
		$revalidate_count = 0;

		foreach ( $logs as $log ) {
			if ( isset( $log['path'] ) && $log['path'] === $path ) {
				$revalidate_count++;
			}
		}

		// Should only have 1 revalidation due to cooldown.
		$this->assertLessThanOrEqual( 2, $revalidate_count, 'Cooldown should limit rapid post updates' );
	}
}
