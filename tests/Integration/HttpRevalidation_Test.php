<?php
/**
 * HTTP Revalidation Integration Tests
 *
 * Tests HTTP request behavior, headers, timeouts, error handling,
 * and endpoint communication for revalidation requests.
 *
 * @package RevalidatePosts
 * @subpackage Tests\Integration
 * @since 1.2.2
 * @version 1.2.2
 */

namespace RevalidatePosts\Tests\Integration;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * HTTP revalidation test case for API communication.
 *
 * @since 1.2.2
 */
class HttpRevalidation_Test extends WP_UnitTestCase {

	/**
	 * Store original HTTP request filter
	 *
	 * @var callable|null
	 */
	private $original_http_filter = null;

	/**
	 * Set up before each test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Disable cooldown for testing.
		Revalidate::instance()->set_cooldown_disabled( true );

		// Clear any existing logs.
		delete_option( 'silver_assist_revalidate_logs' );
	}

	/**
	 * Clean up after each test
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Re-enable cooldown.
		Revalidate::instance()->set_cooldown_disabled( false );

		// Reset processed posts.
		Revalidate::instance()->reset_processed_posts();

		// Clean up options.
		delete_option( 'revalidate_endpoint' );
		delete_option( 'revalidate_token' );
		delete_option( 'silver_assist_revalidate_logs' );

		// Remove HTTP filter.
		if ( null !== $this->original_http_filter ) {
			remove_filter( 'pre_http_request', $this->original_http_filter );
			$this->original_http_filter = null;
		}

		parent::tearDown();
	}

	/**
	 * Mock HTTP response helper
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body Response body.
	 * @param array  $headers Response headers.
	 * @return array Mock response.
	 */
	private function mock_http_response( int $status_code = 200, string $body = '{"revalidated":true}', array $headers = [] ): array {
		return [
			'response' => [
				'code'    => $status_code,
				'message' => $this->get_status_message( $status_code ),
			],
			'body'     => $body,
			'headers'  => array_merge(
				[
					'content-type' => 'application/json',
				],
				$headers
			),
		];
	}

	/**
	 * Get HTTP status message
	 *
	 * @param int $code Status code.
	 * @return string Status message.
	 */
	private function get_status_message( int $code ): string {
		$messages = [
			200 => 'OK',
			404 => 'Not Found',
			500 => 'Internal Server Error',
			503 => 'Service Unavailable',
		];

		return $messages[ $code ] ?? 'Unknown';
	}

	// ============================================
	// END-TO-END REVALIDATION TESTS
	// ============================================

	/**
	 * Test end-to-end revalidation with mocked endpoint.
	 *
	 * @return void
	 */
	public function test_end_to_end_revalidation_with_mocked_endpoint(): void {
		$captured_request = null;

		// Mock HTTP response and capture request.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_request ) {
				$captured_request = [
					'url'  => $url,
					'args' => $args,
				];
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		// Configure endpoint and token.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token-12345' );

		// Trigger revalidation.
		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test-post/' ] );

		// Verify request was made.
		$this->assertNotNull( $captured_request, 'HTTP request should have been made' );
		$this->assertStringContainsString( 'example.com', $captured_request['url'] );
		$this->assertStringContainsString( 'token=test-token-12345', $captured_request['url'] );
		// WordPress add_query_arg may or may not URL encode the path, both are valid.
		$this->assertTrue(
			strpos( $captured_request['url'], 'path=%2Ftest-post%2F' ) !== false ||
			strpos( $captured_request['url'], 'path=/test-post/' ) !== false,
			'URL should contain path parameter'
		);
	}

	// ============================================
	// HTTP HEADERS TESTS
	// ============================================

	/**
	 * Test that User-Agent header is sent correctly.
	 *
	 * @return void
	 */
	public function test_user_agent_header_sent_correctly(): void {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_args ) {
				$captured_args = $args;
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		$this->assertNotNull( $captured_args );
		$this->assertArrayHasKey( 'user-agent', $captured_args );
		$this->assertStringContainsString( 'Silver-Assist-Revalidate/', $captured_args['user-agent'] );
		$this->assertStringContainsString( SILVER_ASSIST_REVALIDATE_VERSION, $captured_args['user-agent'] );
	}

	// ============================================
	// TIMEOUT CONFIGURATION TESTS
	// ============================================

	/**
	 * Test that 30-second timeout is configured.
	 *
	 * @return void
	 */
	public function test_30_second_timeout_configured(): void {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_args ) {
				$captured_args = $args;
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		$this->assertNotNull( $captured_args );
		$this->assertArrayHasKey( 'timeout', $captured_args );
		$this->assertSame( 30, $captured_args['timeout'] );
	}

	// ============================================
	// SSL VERIFICATION TESTS
	// ============================================

	/**
	 * Test that SSL verification is enabled.
	 *
	 * @return void
	 */
	public function test_ssl_verification_enabled(): void {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_args ) {
				$captured_args = $args;
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		$this->assertNotNull( $captured_args );
		$this->assertArrayHasKey( 'sslverify', $captured_args );
		$this->assertTrue( $captured_args['sslverify'], 'SSL verification should be enabled' );
	}

	// ============================================
	// ERROR HANDLING TESTS
	// ============================================

	/**
	 * Test handling network timeout gracefully.
	 *
	 * @return void
	 */
	public function test_handle_network_timeout_gracefully(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return new \WP_Error( 'http_request_failed', 'Operation timed out after 30000 milliseconds' );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		// Verify error was logged.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Error should be logged' );
		$this->assertSame( 'error', $logs[0]['status'] );
		$this->assertArrayHasKey( 'response', $logs[0] );
		$this->assertTrue( $logs[0]['response']['error'] );
	}

	/**
	 * Test handling 404 response.
	 *
	 * @return void
	 */
	public function test_handle_404_response(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return $this->mock_http_response( 404, '{"error":"Not Found"}' );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		// Verify error was logged.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs );
		$this->assertSame( 'error', $logs[0]['status'] );
		$this->assertSame( 404, $logs[0]['status_code'] );
	}

	/**
	 * Test handling 500 response.
	 *
	 * @return void
	 */
	public function test_handle_500_response(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return $this->mock_http_response( 500, '{"error":"Internal Server Error"}' );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		// Verify error was logged.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs );
		$this->assertSame( 'error', $logs[0]['status'] );
		$this->assertSame( 500, $logs[0]['status_code'] );
	}

	/**
	 * Test handling 200 success response.
	 *
	 * @return void
	 */
	public function test_handle_200_success_response(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return $this->mock_http_response( 200, '{"revalidated":true}' );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test/' ] );

		// Verify success was logged.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs );
		$this->assertSame( 'success', $logs[0]['status'] );
		$this->assertSame( 200, $logs[0]['status_code'] );
	}

	// ============================================
	// QUERY PARAMETERS TESTS
	// ============================================

	/**
	 * Test that token and path query parameters are sent correctly.
	 *
	 * @return void
	 */
	public function test_query_parameters_token_and_path_sent_correctly(): void {
		$captured_url = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'secure-token-xyz' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/my-post/' ] );

		$this->assertNotNull( $captured_url );
		
		// Parse URL and verify query parameters.
		$parsed = wp_parse_url( $captured_url );
		$this->assertArrayHasKey( 'query', $parsed );
		
		parse_str( $parsed['query'], $params );
		$this->assertArrayHasKey( 'token', $params );
		$this->assertArrayHasKey( 'path', $params );
		$this->assertSame( 'secure-token-xyz', $params['token'] );
		$this->assertSame( '/my-post/', $params['path'] );
	}

	/**
	 * Test that relative path format is correct (starts and ends with /).
	 *
	 * @return void
	 */
	public function test_relative_path_format_correct(): void {
		$captured_url = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;
				return $this->mock_http_response( 200 );
			},
			10,
			3
		);

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$revalidate = Revalidate::instance();
		$revalidate->revalidate_paths( [ '/test-post/' ] );

		$this->assertNotNull( $captured_url );
		
		// Extract path parameter.
		$parsed = wp_parse_url( $captured_url );
		parse_str( $parsed['query'], $params );
		
		$path = $params['path'];
		$this->assertStringStartsWith( '/', $path, 'Path should start with /' );
		$this->assertStringEndsWith( '/', $path, 'Path should end with /' );
	}
}
