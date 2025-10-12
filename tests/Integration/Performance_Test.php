<?php
/**
 * Performance Tests
 *
 * Tests for plugin performance optimization including bulk revalidation,
 * memory usage, database queries, and HTTP request efficiency.
 *
 * @package RevalidatePosts
 * @subpackage Tests\Integration
 * @since 1.5.0
 * @author Silver Assist Team
 * @version 1.5.0
 */

declare(strict_types=1);

namespace RevalidatePosts\Tests\Integration;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * Performance test suite
 *
 * @group integration
 * @group performance
 * @since 1.5.0
 */
class Performance_Test extends WP_UnitTestCase {

	/**
	 * Revalidate instance
	 *
	 * @var Revalidate
	 */
	private $revalidate;

	/**
	 * Set up test environment
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->revalidate = Revalidate::instance();
		
		// Configure test endpoint and token
		\update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		\update_option( 'revalidate_token', 'test-token-12345' );
		
		// Clear any existing logs
		\delete_option( 'silver_assist_revalidate_logs' );
		
		// Clear any existing transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_revalidate_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_revalidate_%'" );
	}

	/**
	 * Clean up after tests
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function tearDown(): void {
		\delete_option( 'revalidate_endpoint' );
		\delete_option( 'revalidate_token' );
		\delete_option( 'silver_assist_revalidate_logs' );
		
		// Clear transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_revalidate_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_revalidate_%'" );
		
		parent::tearDown();
	}

	/**
	 * Test bulk revalidation completes within time limit
	 *
	 * Measures time to revalidate 100 paths. Should complete in reasonable time.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_bulk_revalidation_performance() {
		// Mock HTTP requests to avoid network delay
		\add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return [
				'response' => [ 
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'success' => true ] ),
			];
		}, 10, 3 );

		$start_time = microtime( true );

		// Create 100 posts to trigger 100 revalidations
		for ( $i = 0; $i < 100; $i++ ) {
			static::factory()->post->create( [
				'post_title'  => "Performance Test Post {$i}",
				'post_status' => 'publish',
			] );
		}

		$end_time      = microtime( true );
		$execution_time = $end_time - $start_time;

		// Should complete in less than 10 seconds (generous limit)
		$this->assertLessThan( 10.0, $execution_time, 'Bulk revalidation should complete in under 10 seconds' );
	}

	/**
	 * Test memory usage stays within acceptable limits
	 *
	 * Verifies that memory usage for 100 log entries stays under 10MB.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_memory_usage_within_limits() {
		// Mock HTTP requests
		\add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return [
				'response' => [ 
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'success' => true ] ),
			];
		}, 10, 3 );

		$memory_before = memory_get_usage( true );

		// Create 100 posts to generate 100+ log entries
		for ( $i = 0; $i < 100; $i++ ) {
			static::factory()->post->create( [
				'post_title'  => "Memory Test Post {$i}",
				'post_status' => 'publish',
			] );
		}

		$memory_after = memory_get_usage( true );
		$memory_used  = $memory_after - $memory_before;

		// Memory usage should be less than 10MB
		$mb_used = $memory_used / 1024 / 1024;
		$this->assertLessThan( 10, $mb_used, "Memory usage should be under 10MB, used: {$mb_used}MB" );
	}

	/**
	 * Test database queries are optimized (no N+1 queries)
	 *
	 * Verifies that number of queries doesn't grow linearly with posts.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_no_n_plus_1_queries() {
		// Mock HTTP requests
		\add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return [
				'response' => [ 
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'success' => true ] ),
			];
		}, 10, 3 );

		global $wpdb;

		// Measure queries for 1 post
		$queries_before = $wpdb->num_queries;
		static::factory()->post->create( [
			'post_title'  => 'Query Test Post 1',
			'post_status' => 'publish',
		] );
		$queries_for_one = $wpdb->num_queries - $queries_before;

		// Measure queries for 10 posts
		$queries_before = $wpdb->num_queries;
		for ( $i = 2; $i <= 11; $i++ ) {
			static::factory()->post->create( [
				'post_title'  => "Query Test Post {$i}",
				'post_status' => 'publish',
			] );
		}
		$queries_for_ten = $wpdb->num_queries - $queries_before;

		// Queries should NOT scale linearly (N+1 problem)
		// If no N+1, queries for 10 posts should be less than 10x queries for 1 post
		$this->assertLessThan( 
			$queries_for_one * 10, 
			$queries_for_ten,
			'Queries should not scale linearly (N+1 problem detected)'
		);
	}

	/**
	 * Test transient storage read/write performance
	 *
	 * Measures performance of cooldown transient operations.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_transient_storage_performance() {
		$start_time = microtime( true );

		// Write 100 transients
		for ( $i = 0; $i < 100; $i++ ) {
			\set_transient( "revalidate_cooldown_test_{$i}", true, 5 );
		}

		$write_time = microtime( true ) - $start_time;

		$start_time = microtime( true );

		// Read 100 transients
		for ( $i = 0; $i < 100; $i++ ) {
			\get_transient( "revalidate_cooldown_test_{$i}" );
		}

		$read_time = microtime( true ) - $start_time;

		// Clean up
		for ( $i = 0; $i < 100; $i++ ) {
			\delete_transient( "revalidate_cooldown_test_{$i}" );
		}

		// Both operations should complete in under 1 second
		$this->assertLessThan( 1.0, $write_time, "Transient writes should complete in under 1 second, took: {$write_time}s" );
		$this->assertLessThan( 1.0, $read_time, "Transient reads should complete in under 1 second, took: {$read_time}s" );
	}

	/**
	 * Test revalidation completes quickly for single post
	 *
	 * Benchmark: single revalidation should complete in under 100ms.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_single_revalidation_benchmark() {
		// Mock HTTP requests
		\add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return [
				'response' => [ 
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'success' => true ] ),
			];
		}, 10, 3 );

		$start_time = microtime( true );

		static::factory()->post->create( [
			'post_title'  => 'Benchmark Test Post',
			'post_status' => 'publish',
		] );

		$end_time      = microtime( true );
		$execution_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds

		// Should complete in under 100ms
		$this->assertLessThan( 100, $execution_time, "Single revalidation should complete in under 100ms, took: {$execution_time}ms" );
	}

	/**
	 * Test log rotation performance
	 *
	 * Verifies that FIFO rotation is efficient when exceeding 100 entries.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function test_log_rotation_performance() {
		// Mock HTTP requests
		\add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return [
				'response' => [ 
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'success' => true ] ),
			];
		}, 10, 3 );

		$start_time = microtime( true );

		// Create 150 posts to trigger log rotation
		for ( $i = 0; $i < 150; $i++ ) {
			static::factory()->post->create( [
				'post_title'  => "Log Rotation Test {$i}",
				'post_status' => 'publish',
			] );
		}

		$end_time      = microtime( true );
		$execution_time = $end_time - $start_time;

		// Get final logs
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );

		// Should have exactly 100 entries (FIFO rotation working)
		$this->assertCount( 100, $logs, 'FIFO rotation should maintain exactly 100 entries' );

		// Rotation should not significantly impact performance
		// 150 posts should complete in reasonable time even with rotation
		$this->assertLessThan( 15.0, $execution_time, 'Log rotation should not significantly impact performance' );
	}
}
