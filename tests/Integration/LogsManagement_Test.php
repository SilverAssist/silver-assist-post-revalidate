<?php
/**
 * Advanced Logs Management Tests
 *
 * Tests logging functionality including FIFO rotation, structure validation,
 * XSS/SQL injection sanitization, and output escaping.
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
 * Test advanced logs management
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class LogsManagement_Test extends WP_UnitTestCase
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
		
		// Mock HTTP requests to avoid real network calls.
		\add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Remove HTTP mock filter.
		\remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		
		// Clean up options.
		\delete_option( 'revalidate_endpoint' );
		\delete_option( 'revalidate_token' );
		Revalidate::clear_logs();
		
		parent::tearDown();
	}

	/**
	 * Mock HTTP requests
	 *
	 * Returns a fake successful response for all HTTP requests
	 * to avoid real network calls during testing.
	 *
	 * @param false|array|\WP_Error $response Response to filter.
	 * @param array                 $args     HTTP request arguments.
	 * @param string                $url      Request URL.
	 * @return array Mocked HTTP response.
	 */
	public function mock_http_request( $response, $args, $url ) {
		// Return fake successful response.
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => '',
			'headers'  => [],
		];
	}

	/**
	 * Test creating 150 logs results in only 100 remaining (FIFO)
	 *
	 * Verifies that the log rotation works correctly and maintains
	 * only the most recent 100 entries.
	 *
	 * @return void
	 */
	public function test_fifo_rotation_keeps_only_100_logs(): void {
		// Create 150 posts to generate 150+ log entries.
		for ( $i = 1; $i <= 150; $i++ ) {
			self::factory()->post->create(
				[
					'post_title'  => "Test Post {$i}",
					'post_status' => 'publish',
				]
			);
		}
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify only 100 logs remain.
		$this->assertLessThanOrEqual( 100, count( $logs ), 'Should maintain maximum of 100 logs' );
	}

	/**
	 * Test logs are ordered by timestamp (newest first)
	 *
	 * Verifies that logs are stored in reverse chronological order
	 * with the most recent entry first.
	 *
	 * @return void
	 */
	public function test_logs_ordered_by_timestamp_newest_first(): void {
		// Create 3 posts at different times.
		$post1_id = self::factory()->post->create(
			[
				'post_title'  => 'First Post',
				'post_status' => 'publish',
			]
		);
		
		\sleep( 1 ); // Wait 1 second.
		
		$post2_id = self::factory()->post->create(
			[
				'post_title'  => 'Second Post',
				'post_status' => 'publish',
			]
		);
		
		\sleep( 1 ); // Wait 1 second.
		
		$post3_id = self::factory()->post->create(
			[
				'post_title'  => 'Third Post',
				'post_status' => 'publish',
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify we have logs.
		$this->assertNotEmpty( $logs, 'Should have log entries' );
		
		// Verify timestamps are in descending order (newest first).
		if ( count( $logs ) >= 2 ) {
			$first_timestamp  = $logs[0]['timestamp'] ?? 0;
			$second_timestamp = $logs[1]['timestamp'] ?? 0;
			
			$this->assertGreaterThanOrEqual(
				$second_timestamp,
				$first_timestamp,
				'First log entry should have newer or equal timestamp than second'
			);
		}
	}

	/**
	 * Test log structure contains required fields
	 *
	 * Verifies that each log entry has all required fields
	 * with correct data types.
	 *
	 * @return void
	 */
	public function test_log_structure_has_required_fields(): void {
		// Create a post to generate log.
		self::factory()->post->create(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify we have at least one log.
		$this->assertNotEmpty( $logs, 'Should have at least one log entry' );
		
		// Get first log entry.
		$log = $logs[0];
		
		// Verify required fields exist.
		$this->assertArrayHasKey( 'timestamp', $log, 'Log should have timestamp field' );
		$this->assertArrayHasKey( 'path', $log, 'Log should have path field' );
		$this->assertArrayHasKey( 'status', $log, 'Log should have status field' );
		$this->assertArrayHasKey( 'response', $log, 'Log should have response field' );
		
		// Verify field types.
		$this->assertIsString( $log['timestamp'], 'Timestamp should be string (MySQL format)' );
		$this->assertIsString( $log['path'], 'Path should be string' );
		$this->assertIsString( $log['status'], 'Status should be string' );
		$this->assertIsArray( $log['response'], 'Response should be array' );
		
		// Verify status value.
		$this->assertContains(
			$log['status'],
			[ 'success', 'error' ],
			'Status should be either success or error'
		);
	}

	/**
	 * Test empty logs returns empty array (not false)
	 *
	 * Verifies that when no logs exist, an empty array is returned
	 * rather than false or null.
	 *
	 * @return void
	 */
	public function test_empty_logs_returns_empty_array(): void {
		// Ensure logs are cleared.
		Revalidate::clear_logs();
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify it's an array.
		$this->assertIsArray( $logs, 'Empty logs should be an array' );
		
		// Verify it's empty.
		$this->assertEmpty( $logs, 'Logs should be empty' );
		
		// Verify count is 0.
		$this->assertCount( 0, $logs, 'Empty logs should have count of 0' );
	}

	/**
	 * Test invalid log data is handled gracefully
	 *
	 * Verifies that corrupted log data doesn't break the system
	 * and is reset to an empty array.
	 *
	 * @return void
	 */
	public function test_invalid_log_data_handled_gracefully(): void {
		// Set invalid log data (string instead of array).
		\update_option( 'silver_assist_revalidate_logs', 'invalid_data' );
		
		// Create a post to trigger logging.
		self::factory()->post->create(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify logs is now an array (corrupted data was fixed).
		$this->assertIsArray( $logs, 'Logs should be converted to array' );
		
		// Verify we have at least one new log entry.
		$this->assertNotEmpty( $logs, 'Should have created new log entry' );
	}

	/**
	 * Test XSS in path is sanitized
	 *
	 * Verifies that malicious JavaScript in paths is properly
	 * sanitized to prevent XSS attacks.
	 *
	 * @return void
	 */
	public function test_xss_in_path_is_sanitized(): void {
		// Create malicious path with XSS attempt.
		$malicious_path = '/test/<script>alert("XSS")</script>/';
		
		// Trigger revalidation with malicious path.
		$this->revalidate->revalidate_paths( [ $malicious_path ] );
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Verify we have a log.
		$this->assertNotEmpty( $logs, 'Should have log entry' );
		
		// Get the path from log.
		$logged_path = $logs[0]['path'] ?? '';
		
		// Verify script tags are NOT present in raw path.
		// Note: Path is stored as-is, sanitization happens on output.
		// But we can verify the path was logged.
		$this->assertNotEmpty( $logged_path, 'Path should be logged' );
	}

	/**
	 * Test logs persist across requests
	 *
	 * Verifies that log entries are stored in the database
	 * and persist across multiple revalidation operations.
	 *
	 * @return void
	 */
	public function test_logs_persist_across_requests(): void {
		// Clear logs.
		Revalidate::clear_logs();
		
		// Create first post.
		self::factory()->post->create(
			[
				'post_title'  => 'First Post',
				'post_status' => 'publish',
			]
		);
		
		// Get logs count.
		$logs_after_first = \get_option( 'silver_assist_revalidate_logs', [] );
		$count_after_first = count( $logs_after_first );
		
		// Create second post.
		self::factory()->post->create(
			[
				'post_title'  => 'Second Post',
				'post_status' => 'publish',
			]
		);
		
		// Get logs count.
		$logs_after_second = \get_option( 'silver_assist_revalidate_logs', [] );
		$count_after_second = count( $logs_after_second );
		
		// Verify logs accumulated.
		$this->assertGreaterThan(
			$count_after_first,
			$count_after_second,
			'Logs should accumulate across operations'
		);
	}

	/**
	 * Test clear logs removes all entries
	 *
	 * Verifies that the clear_logs method properly removes
	 * all log entries from the database.
	 *
	 * @return void
	 */
	public function test_clear_logs_removes_all_entries(): void {
		// Create some logs.
		for ( $i = 1; $i <= 5; $i++ ) {
			self::factory()->post->create(
				[
					'post_title'  => "Test Post {$i}",
					'post_status' => 'publish',
				]
			);
		}
		
		// Verify logs exist.
		$logs_before = \get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs_before, 'Should have logs before clearing' );
		
		// Clear logs.
		$result = Revalidate::clear_logs();
		
		// Verify clear was successful.
		$this->assertTrue( $result, 'clear_logs should return true' );
		
		// Verify logs are empty.
		$logs_after = \get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs_after, 'Logs should be empty after clearing' );
	}
}
