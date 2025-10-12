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
use WP_UnitTestCase;

/**
 * Test case for manual revalidation features using real WordPress environment.
 *
 * Tests both row actions (post list table) and meta box (post editor) functionality.
 *
 * @since 1.4.0
 */
class ManualRevalidation_Test extends WP_UnitTestCase {

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

		// Create a published post for testing.
		$this->test_post_id = $this->factory->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post for Manual Revalidation',
			]
		);

		// Mock HTTP requests.
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// Disable cooldown for testing.
		Revalidate::instance()->set_cooldown_disabled( true );
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

		// Trigger manual revalidation initialization to ensure hooks are registered.
		\RevalidatePosts\ManualRevalidation::instance();

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

		// Apply the filter that WordPress uses to get row actions.
		$actions = apply_filters( 'post_row_actions', [], $post );

		$this->assertArrayHasKey( 'revalidate', $actions, 'Revalidate action should appear in row actions for editors' );
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

		// Trigger meta box registration.
		do_action( 'add_meta_boxes_post', get_post( $this->test_post_id ) );

		$this->assertArrayHasKey( 'post', $wp_meta_boxes, 'Meta boxes should be registered for post type' );
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
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_ajax_manual_revalidation_success(): void {
		wp_set_current_user( self::$admin_user_id );

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = wp_create_nonce( 'revalidate_post_' . $this->test_post_id );

		// Capture the AJAX response.
		try {
			do_action( 'wp_ajax_silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'revalidated', strtolower( $response['data']['message'] ?? '' ), 'Response should contain success message' );
	}

	/**
	 * Test AJAX handler requires valid nonce.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
	 *
	 * @return void
	 */
	public function test_ajax_manual_revalidation_requires_nonce(): void {
		wp_set_current_user( self::$admin_user_id );

		$_POST['action']   = 'silver_assist_manual_revalidate';
		$_POST['post_id']  = $this->test_post_id;
		$_POST['_wpnonce'] = 'invalid-nonce';

		// Capture the AJAX response.
		try {
			do_action( 'wp_ajax_silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should fail with invalid nonce' );
	}

	/**
	 * Test AJAX handler requires edit_posts capability.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
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

		// Capture the AJAX response.
		try {
			do_action( 'wp_ajax_silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should fail without edit_posts capability' );
	}

	/**
	 * Test that manual revalidation creates log entry.
	 *
	 * RED PHASE: This test will fail because the functionality doesn't exist yet.
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

		// Trigger AJAX action.
		try {
			do_action( 'wp_ajax_silver_assist_manual_revalidate' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}

		// Check logs.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );

		$this->assertNotEmpty( $logs, 'Log entry should be created' );
		$this->assertStringContainsString( 'manual', strtolower( $logs[0]['trigger'] ?? '' ), 'Log should indicate manual trigger' );
	}
}
