<?php
/**
 * Tests for Manual Revalidation functionality (Row Actions + Meta Box).
 *
 * @package RevalidatePosts
 * @since 1.4.0
 * @version 1.4.0
 */

namespace RevalidatePosts\Tests\Integration;

use RevalidatePosts\Revalidate;
use WP_Ajax_UnitTestCase;

/**
 * Test case for manual revalidation features using real WordPress environment.
 *
 * Tests both row actions (post list table) and meta box (post editor) functionality.
 *
 * @since 1.4.0
 */
class ManualRevalidation_Test extends WP_Ajax_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private static $admin_user_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private static $editor_user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $test_post_id;

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

		self::$editor_user_id = $factory->user->create(
			[
				'role' => 'editor',
			]
		);
	}

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Configure endpoint and token for tests.
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token-12345' );

		// Disable cooldown for testing.
		Revalidate::instance()->set_cooldown_disabled( true );

		// Mock HTTP requests FIRST - before any code that might make requests.
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// Remove automatic revalidation hooks temporarily to avoid interfering with tests.
		remove_action( 'save_post', [ Revalidate::instance(), 'on_post_saved' ], 10 );

		// Create a published post for testing (won't trigger revalidation now).
		$this->test_post_id = $this->factory->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post for Manual Revalidation',
			]
		);

		// Re-add the hook after post creation.
		add_action( 'save_post', [ Revalidate::instance(), 'on_post_saved' ], 10, 3 );

		// Initialize ManualRevalidation to register hooks.
		// Note: We need to get the instance and manually re-register hooks because
		// WordPress test suite may clear hooks between tests.
		$manual_revalidation = \RevalidatePosts\ManualRevalidation::instance();
		
		// Re-register the hooks to ensure they're active for this test.
		add_filter( 'post_row_actions', [ $manual_revalidation, 'add_revalidate_row_action' ], 10, 2 );
		add_action( 'add_meta_boxes', [ $manual_revalidation, 'add_revalidate_meta_box' ] );
		add_action( 'wp_ajax_silver_assist_manual_revalidate', [ $manual_revalidation, 'ajax_manual_revalidate' ] );
		add_action( 'admin_action_revalidate_post', [ $manual_revalidation, 'handle_revalidate_action' ] );
		add_action( 'admin_enqueue_scripts', [ $manual_revalidation, 'enqueue_admin_scripts' ] );
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

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Re-enable cooldown after testing.
		Revalidate::instance()->set_cooldown_disabled( false );
		Revalidate::instance()->reset_processed_posts();

		// Clear all transients for fresh state.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_revalidate_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_revalidate_%'" );

		// Clear meta boxes global.
		global $wp_meta_boxes;
		$wp_meta_boxes = [];

		parent::tearDown();
	}

	/**
	 * Mock HTTP requests for testing.
	 *
	 * @param false  $preempt Whether to preempt the request.
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 * @return array Mocked response.
	 */
	public function mock_http_request( $preempt, $args, $url ) {
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => '{"revalidated":true}',
		];
	}

	/**
	 * Test that "Revalidate" action appears in post row actions for admins.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_revalidate_action_appears_in_row_actions_for_admin(): void {
		wp_set_current_user( self::$admin_user_id );

		$post = get_post( $this->test_post_id );

		// Apply the filter that WordPress uses to get row actions.
		$actions = apply_filters( 'post_row_actions', [], $post );

		$this->assertArrayHasKey( 'revalidate', $actions, 'Revalidate action should appear in row actions for admins' );
		$this->assertStringContainsString( 'Revalidate', $actions['revalidate'], 'Action should contain "Revalidate" text' );
	}

	/**
	 * Test that "Revalidate" action appears in post row actions for editors.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_revalidate_action_appears_in_row_actions_for_editor(): void {
		wp_set_current_user( self::$editor_user_id );

		$post = get_post( $this->test_post_id );

		// Debug: verify user and post
		$this->assertTrue( current_user_can( 'edit_posts' ), 'Editor should have edit_posts capability' );
		$this->assertEquals( 'publish', $post->post_status, 'Post should be published' );
		$this->assertEquals( 'post', $post->post_type, 'Post type should be post' );

		// Apply the filter that WordPress uses to get row actions.
		$actions = apply_filters( 'post_row_actions', [], $post );

		// Debug: show what actions we got
		if ( empty( $actions ) ) {
			$this->fail( 'No actions returned from filter. Something is wrong with hook registration.' );
		}

		$this->assertArrayHasKey( 'revalidate', $actions, 'Revalidate action should appear in row actions for editors. Actions: ' . print_r( array_keys( $actions ), true ) );
	}

	/**
	 * Test that "Revalidate" action does NOT appear for draft posts.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_revalidate_action_not_shown_for_draft_posts(): void {
		wp_set_current_user( self::$admin_user_id );

		// Create a draft post.
		$draft_post_id = $this->factory->post->create(
			[
				'post_status' => 'draft',
			]
		);

		$post = get_post( $draft_post_id );

		// Apply the filter that WordPress uses to get row actions.
		$actions = apply_filters( 'post_row_actions', [], $post );

		$this->assertArrayNotHasKey( 'revalidate', $actions, 'Revalidate action should NOT appear for draft posts' );
	}

	/**
	 * Test that row action URL contains correct nonce and post ID.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_row_action_url_contains_nonce_and_post_id(): void {
		wp_set_current_user( self::$admin_user_id );

		$post = get_post( $this->test_post_id );

		// Apply the filter that WordPress uses to get row actions.
		$actions = apply_filters( 'post_row_actions', [], $post );

		$this->assertArrayHasKey( 'revalidate', $actions );

		// Extract URL from the HTML.
		preg_match( '/href=["\']([^"\']+)["\']/', $actions['revalidate'], $matches );
		$url = $matches[1] ?? '';

		$this->assertStringContainsString( 'action=revalidate_post', $url, 'URL should contain revalidate action' );
		$this->assertStringContainsString( 'post=' . $this->test_post_id, $url, 'URL should contain post ID' );
		$this->assertStringContainsString( '_wpnonce=', $url, 'URL should contain nonce' );
	}

	/**
	 * Test that meta box is registered for post edit screen.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_meta_box_is_registered_for_posts(): void {
		global $wp_meta_boxes;

		wp_set_current_user( self::$admin_user_id );

		// Set the global post for the meta box callback.
		$GLOBALS['post'] = get_post( $this->test_post_id );

		// Clear meta boxes to start fresh.
		$wp_meta_boxes = [];

		// Trigger meta box registration - use 'add_meta_boxes' hook without _post suffix.
		do_action( 'add_meta_boxes', 'post', $GLOBALS['post'] );

		// Debug: Check what we got.
		if ( empty( $wp_meta_boxes ) ) {
			$this->fail( 'No meta boxes registered at all. Hook may not be firing.' );
		}

		$this->assertArrayHasKey( 'post', $wp_meta_boxes, 'Meta boxes should be registered for post type. Keys: ' . print_r( array_keys( $wp_meta_boxes ), true ) );
		$this->assertArrayHasKey( 'side', $wp_meta_boxes['post'], 'Side meta boxes should exist' );

		// Check if our meta box is registered.
		$side_boxes = $wp_meta_boxes['post']['side'];
		$found      = false;

		foreach ( $side_boxes as $priority => $boxes ) {
			if ( isset( $boxes['silver_assist_revalidate_meta_box'] ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Revalidate meta box should be registered in sidebar' );

		// Clean up global.
		unset( $GLOBALS['post'] );
	}

	/**
	 * Test that meta box only shows for published posts.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_meta_box_only_shows_for_published_posts(): void {
		global $wp_meta_boxes;

		wp_set_current_user( self::$admin_user_id );

		// Create a draft post.
		$draft_post_id = $this->factory->post->create(
			[
				'post_status' => 'draft',
			]
		);

		// Clear meta boxes.
		$wp_meta_boxes = [];

		// Trigger meta box registration for draft post.
		do_action( 'add_meta_boxes_post', get_post( $draft_post_id ) );

		// Check if our meta box is registered.
		$found = false;
		if ( isset( $wp_meta_boxes['post']['side'] ) ) {
			foreach ( $wp_meta_boxes['post']['side'] as $priority => $boxes ) {
				if ( isset( $boxes['silver_assist_revalidate_meta_box'] ) ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertFalse( $found, 'Revalidate meta box should NOT appear for draft posts' );
	}

	/**
	 * Test AJAX handler for manual revalidation from row action.
	 *
	 * @return void
	 */
	public function test_ajax_manual_revalidation_success(): void {
		wp_set_current_user( self::$admin_user_id );

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = wp_create_nonce( 'revalidate_post_' . $this->test_post_id );

		// Use _handleAjax() helper from WordPress test case.
		try {
			$this->_handleAjax( 'silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected - thrown by wp_send_json_success().
		}

		// If no exception was thrown for wrong reason, test the response.
		$response = json_decode( $this->_last_response, true );
		
		$this->assertNotNull( $response, 'Response should be valid JSON' );
		$this->assertTrue( $response['success'] ?? false, 'AJAX response should be successful' );
	}

	/**
	 * Test AJAX handler requires valid nonce.
	 *
	 * @return void
	 */
	public function test_ajax_manual_revalidation_requires_nonce(): void {
		wp_set_current_user( self::$admin_user_id );

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = 'invalid-nonce';

		// Use _handleAjax() helper from WordPress test case.
		try {
			$this->_handleAjax( 'silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected - thrown by wp_send_json_error().
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertNotNull( $response, 'Response should be valid JSON' );
		$this->assertFalse( $response['success'] ?? true, 'AJAX response should fail with invalid nonce' );
	}

	/**
	 * Test AJAX handler requires edit_posts capability.
	 *
	 * @return void
	 */
	public function test_ajax_manual_revalidation_requires_capability(): void {
		// Create subscriber user (no edit_posts capability).
		$subscriber_id = $this->factory->user->create(
			[
				'role' => 'subscriber',
			]
		);
		wp_set_current_user( $subscriber_id );

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = wp_create_nonce( 'revalidate_post_' . $this->test_post_id );

		// Use _handleAjax() helper from WordPress test case.
		try {
			$this->_handleAjax( 'silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected - thrown by wp_send_json_error().
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertNotNull( $response, 'Response should be valid JSON' );
		$this->assertFalse( $response['success'] ?? true, 'AJAX response should fail without edit_posts capability' );
	}

	/**
	 * Test that manual revalidation creates log entry.
	 *
	 * @return void
	 */
	public function test_manual_revalidation_creates_log_entry(): void {
		wp_set_current_user( self::$admin_user_id );

		// Clear existing logs.
		Revalidate::clear_logs();

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = wp_create_nonce( 'revalidate_post_' . $this->test_post_id );

		// Use _handleAjax() helper from WordPress test case.
		try {
			$this->_handleAjax( 'silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected - thrown by wp_send_json_success().
		}

		// Check logs.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );

		$this->assertNotEmpty( $logs, 'Log entry should be created' );
		$this->assertStringContainsString( 'manual', strtolower( $logs[0]['trigger'] ?? '' ), 'Log should indicate manual trigger' );
	}
}
