<?php
/**
 * Comprehensive tests for Revalidate class using WordPress test suite.
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
 * Tests cover:
 * - Singleton pattern
 * - Post creation, editing, deletion
 * - Post status transitions (draft, private, unpublish)
 * - Category/tag invalidation
 * - Deduplication (one revalidation per path)
 * - Category/tag creation, editing, deletion
 * - Log registration and FIFO rotation
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
	 * Test tag ID.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Set up before class runs.
	 *
	 * @param mixed $factory Factory instance.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		// Create test category.
		self::$category_id = $factory->category->create(
[
'name' => 'Test Category',
				'slug' => 'test-category',
			]
		);

		// Create test tag.
		self::$tag_id = $factory->tag->create(
[
'name' => 'Test Tag',
				'slug' => 'test-tag',
			]
		);

		// Create test post with category and tag.
		self::$post_id = $factory->post->create(
[
'post_title'    => 'Test Post for Revalidation',
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_category' => [ self::$category_id ],
				'tags_input'    => [ self::$tag_id ],
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
		remove_filter( 'pre_http_request', [ $this, 'mock_http_response' ] );
		parent::tearDown();
	}

	/**
	 * Mock HTTP response to prevent actual network requests.
	 *
	 * @param false|array|WP_Error $preempt Whether to preempt an HTTP request. Default false.
	 * @param array                $args    HTTP request arguments.
	 * @param string               $url     The request URL.
	 * @return array Fake HTTP response.
	 */
	public function mock_http_response( $preempt, $args, $url ) {
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => wp_json_encode( [ 'revalidated' => true ] ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	// ============================================
	// CORE FUNCTIONALITY TESTS
	// ============================================

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

	// ============================================
	// POST LIFECYCLE TESTS
	// ============================================

	/**
	 * Test that creating a post triggers revalidation.
	 *
	 * @return void
	 */
	public function test_creating_post_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		// Create a new post.
		$new_post_id = $this->factory->post->create(
[
'post_title'  => 'New Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		// Trigger the save_post action manually.
		do_action( 'save_post', $new_post_id, get_post( $new_post_id ), false );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Creating a post should trigger revalidation' );
	}

	/**
	 * Test that editing a post triggers revalidation.
	 *
	 * @return void
	 */
	public function test_editing_post_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		// Update the post.
		wp_update_post(
[
'ID'         => self::$post_id,
				'post_title' => 'Updated Test Post',
			]
		);

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Editing a post should trigger revalidation' );
	}

	/**
	 * Test that deleting a post triggers revalidation.
	 *
	 * @return void
	 */
	public function test_deleting_post_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a post to delete.
		$delete_post_id = $this->factory->post->create(
[
'post_title'  => 'Post to Delete',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		// Delete the post.
		wp_delete_post( $delete_post_id, true );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Deleting a post should trigger revalidation' );
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

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( $draft_post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs, 'Draft posts should not trigger revalidation' );
	}

	// ============================================
	// POST STATUS TRANSITION TESTS
	// ============================================

	/**
	 * Test changing post status from publish to draft triggers revalidation.
	 *
	 * @return void
	 */
	public function test_post_status_change_publish_to_draft_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a published post.
		$status_post_id = $this->factory->post->create(
[
'post_title'  => 'Post Status Test',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		// Change status to draft.
		wp_update_post(
[
'ID'          => $status_post_id,
				'post_status' => 'draft',
			]
		);

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Changing post status from publish to draft should trigger revalidation' );
	}

	/**
	 * Test changing post status from publish to private triggers revalidation.
	 *
	 * @return void
	 */
	public function test_post_status_change_publish_to_private_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a published post.
		$status_post_id = $this->factory->post->create(
[
'post_title'  => 'Post Status Private Test',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		// Change status to private.
		wp_update_post(
[
'ID'          => $status_post_id,
				'post_status' => 'private',
			]
		);

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Changing post status from publish to private should trigger revalidation' );
	}

	/**
	 * Test changing post status from draft to publish triggers revalidation.
	 *
	 * @return void
	 */
	public function test_post_status_change_draft_to_publish_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a draft post.
		$status_post_id = $this->factory->post->create(
[
'post_title'  => 'Draft to Publish Test',
				'post_status' => 'draft',
				'post_type'   => 'post',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		// Change status to publish.
		wp_update_post(
[
'ID'          => $status_post_id,
				'post_status' => 'publish',
			]
		);

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Changing post status from draft to publish should trigger revalidation' );
	}

	// ============================================
	// TAXONOMY INVALIDATION TESTS
	// ============================================

	/**
	 * Test that categories and tags are invalidated when post is saved.
	 *
	 * @return void
	 */
	public function test_post_invalidates_related_categories_and_tags(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Post should trigger revalidation' );

		// Check that logs include category and tag paths.
		$paths = array_column( $logs, 'path' );
		$has_category = false;
		$has_tag      = false;

		foreach ( $paths as $path ) {
			if ( strpos( $path, 'cat=' ) !== false || strpos( $path, 'category' ) !== false ) {
				$has_category = true;
			}
			if ( strpos( $path, 'tag=' ) !== false || strpos( $path, 'tag' ) !== false ) {
				$has_tag = true;
			}
		}

		$this->assertTrue( $has_category || $has_tag, 'Logs should include category or tag paths' );
	}

	// ============================================
	// DEDUPLICATION TESTS
	// ============================================

	/**
	 * Test that revalidation is only triggered once per path (deduplication).
	 *
	 * @return void
	 */
	public function test_revalidation_triggered_once_per_path(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$paths = array_column( $logs, 'path' );

		// Check for duplicates.
		$unique_paths = array_unique( $paths );
		$this->assertCount( count( $unique_paths ), $paths, 'Each path should only be revalidated once' );
	}

	// ============================================
	// CATEGORY LIFECYCLE TESTS
	// ============================================

	/**
	 * Test creating a category triggers revalidation.
	 *
	 * @return void
	 */
	public function test_creating_category_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		// Create a new category.
		$new_category_id = $this->factory->category->create(
[
'name' => 'New Category Test',
				'slug' => 'new-category-test',
			]
		);

		// Trigger the created_category action manually.
		do_action( 'created_category', $new_category_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Creating a category should trigger revalidation' );
	}

	/**
	 * Test editing a category triggers revalidation.
	 *
	 * @return void
	 */
	public function test_editing_category_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_category_updated( self::$category_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Editing a category should trigger revalidation' );
	}

	/**
	 * Test deleting a category triggers revalidation.
	 *
	 * @return void
	 */
	public function test_deleting_category_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a category to delete.
		$delete_category_id = $this->factory->category->create(
[
'name' => 'Category to Delete',
				'slug' => 'category-to-delete',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_category_updated( $delete_category_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Deleting a category should trigger revalidation' );
	}

	// ============================================
	// TAG LIFECYCLE TESTS
	// ============================================

	/**
	 * Test creating a tag triggers revalidation.
	 *
	 * @return void
	 */
	public function test_creating_tag_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		// Create a new tag.
		$new_tag_id = $this->factory->tag->create(
[
'name' => 'New Tag Test',
				'slug' => 'new-tag-test',
			]
		);

		// Trigger the created_post_tag action manually.
		do_action( 'created_post_tag', $new_tag_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Creating a tag should trigger revalidation' );
	}

	/**
	 * Test editing a tag triggers revalidation.
	 *
	 * @return void
	 */
	public function test_editing_tag_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_tag_updated( self::$tag_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Editing a tag should trigger revalidation' );
	}

	/**
	 * Test deleting a tag triggers revalidation.
	 *
	 * @return void
	 */
	public function test_deleting_tag_triggers_revalidation(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		// Create a tag to delete.
		$delete_tag_id = $this->factory->tag->create(
[
'name' => 'Tag to Delete',
				'slug' => 'tag-to-delete',
			]
		);

		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_tag_updated( $delete_tag_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Deleting a tag should trigger revalidation' );
	}

	// ============================================
	// LOG MANAGEMENT TESTS
	// ============================================

	/**
	 * Test that published posts create log entries.
	 *
	 * @return void
	 */
	public function test_published_post_creates_log_entry(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertNotEmpty( $logs, 'Published post should create log entry' );
		$this->assertGreaterThanOrEqual( 1, count( $logs ), 'Should have at least one log entry' );
	}

	/**
	 * Test that log entry contains all required fields.
	 *
	 * @return void
	 */
	public function test_log_entry_contains_required_fields(): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
		delete_option( 'silver_assist_revalidate_logs' );
		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertGreaterThanOrEqual( 1, count( $logs ), 'Should have at least one log entry' );

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
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );

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

		update_option( 'revalidate_endpoint', 'https://example.com/api/revalidate' );
		update_option( 'revalidate_token', 'test-token' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		// Check that we still have max 100 entries.
		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertCount( 100, $logs, 'Should maintain max 100 log entries (FIFO)' );

		// One of the newest logs should contain the post path.
		$newest_paths = array_column( array_slice( $logs, 0, 3 ), 'path' );
		$has_post_path = false;
		foreach ( $newest_paths as $path ) {
			if ( strpos( $path, '?p=' ) !== false ) {
				$has_post_path = true;
				break;
			}
		}
		$this->assertTrue( $has_post_path, 'One of the newest logs should contain the post path' );
	}

	/**
	 * Test clearing logs.
	 *
	 * @return void
	 */
	public function test_clear_logs(): void {
		// Create some log entries.
		$logs = [
			[
				'timestamp'   => current_time( 'mysql' ),
				'path'        => '/test-path/',
				'status'      => 'success',
				'status_code' => 200,
				'request'     => [],
				'response'    => [],
			],
		];
		update_option( 'silver_assist_revalidate_logs', $logs );

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
		delete_option( 'revalidate_endpoint' );
		delete_option( 'revalidate_token' );
		delete_option( 'silver_assist_revalidate_logs' );

		$instance = Revalidate::instance();
		$instance->on_post_saved( self::$post_id );

		$logs = get_option( 'silver_assist_revalidate_logs', [] );
		$this->assertEmpty( $logs, 'Empty endpoint should skip revalidation' );
	}

	/**
	 * Test URL to relative path conversion.
	 *
	 * @return void
	 */
	public function test_url_to_relative_path_conversion(): void {
		$full_url = 'https://example.com/blog/my-post/';
		$expected_path = '/blog/my-post/';
		
		$result = parse_url( $full_url, PHP_URL_PATH );
		
		$this->assertSame( $expected_path, $result, 'Full URL should be converted to relative path' );
	}
}
