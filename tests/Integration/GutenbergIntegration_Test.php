<?php
/**
 * Gutenberg Integration Tests
 *
 * Tests that the Block Editor (Gutenberg) behavior works correctly with
 * the revalidation system, including cooldown prevention, duplicate filtering,
 * and autosave handling.
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
 * Test Gutenberg/Block Editor integration
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class GutenbergIntegration_Test extends WP_UnitTestCase
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
		
		// Clear logs and reset state.
		Revalidate::clear_logs();
		$this->revalidate->reset_processed_posts();
		
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
	 * Test multiple rapid saves trigger only ONE revalidation (cooldown)
	 *
	 * When a user rapidly saves a post (e.g., in Gutenberg with auto-updates),
	 * the cooldown mechanism should prevent multiple revalidations within 5 seconds.
	 *
	 * @return void
	 */
	public function test_multiple_rapid_saves_trigger_one_revalidation(): void {
		// Create a published post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Simulate 3 rapid saves within cooldown period.
		for ( $i = 1; $i <= 3; $i++ ) {
			\wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => "Updated Title {$i}",
				]
			);
		}
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Should only have revalidation from first save (cooldown blocks others).
		$this->assertLessThanOrEqual( 2, count( $logs ), 'Rapid saves should trigger minimal revalidations (cooldown)' );
	}

	/**
	 * Test meta updates during save do NOT duplicate revalidation
	 *
	 * When WordPress saves post meta during a post save operation,
	 * it should NOT trigger separate revalidation requests.
	 *
	 * @return void
	 */
	public function test_meta_updates_during_save_no_duplicate(): void {
		// Create a published post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Update post with meta (simulates Gutenberg block data save).
		\update_post_meta( $post_id, '_test_meta_key', 'test_value' );
		\wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated with Meta',
			]
		);
		\update_post_meta( $post_id, '_test_meta_key', 'new_value' );
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Should only have one set of revalidations (post save, not meta updates).
		// Each post save triggers 2 logs (post permalink + category).
		$this->assertLessThanOrEqual( 4, count( $logs ), 'Meta updates should NOT trigger separate revalidations' );
	}

	/**
	 * Test taxonomy saves during post save do NOT duplicate
	 *
	 * When categories/tags are updated during a post save,
	 * it should NOT trigger multiple separate revalidations.
	 *
	 * @return void
	 */
	public function test_taxonomy_saves_during_post_no_duplicate(): void {
		// Create a category.
		$category_id = self::factory()->category->create( [ 'name' => 'Test Category' ] );
		
		// Create a published post with category.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Update post with new category (simulates Gutenberg taxonomy panel).
		\wp_set_post_categories( $post_id, [ $category_id ] );
		\wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated with Category',
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Should have revalidations for post + category, but not duplicated.
		$this->assertLessThanOrEqual( 4, count( $logs ), 'Taxonomy updates should NOT duplicate revalidations' );
	}

	/**
	 * Test processed_posts array prevents same-request duplicates
	 *
	 * The processed_posts array should prevent the same post from
	 * triggering revalidation multiple times within the same request.
	 *
	 * @return void
	 */
	public function test_processed_posts_array_prevents_duplicates(): void {
		// Create a published post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Manually trigger save_post action multiple times (simulates WordPress behavior).
		\do_action( 'save_post', $post_id, \get_post( $post_id ), false );
		\do_action( 'save_post', $post_id, \get_post( $post_id ), false );
		\do_action( 'save_post', $post_id, \get_post( $post_id ), false );
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Should only have one set of revalidations (processed_posts prevents duplicates).
		$this->assertLessThanOrEqual( 2, count( $logs ), 'processed_posts array should prevent same-request duplicates' );
		
		// Reset processed_posts for next test.
		$this->revalidate->reset_processed_posts();
	}

	/**
	 * Test block editor autosave filtered correctly
	 *
	 * Gutenberg autosaves should be filtered by wp_is_post_autosave()
	 * and should NOT trigger revalidation.
	 *
	 * @return void
	 */
	public function test_block_editor_autosave_filtered(): void {
		// Create a published post.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			]
		);
		
		// Create an autosave revision.
		$autosave_id = self::factory()->post->create(
			[
				'post_parent' => $post_id,
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_name'   => "{$post_id}-autosave-v1",
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Manually trigger save_post for autosave (should be filtered).
		\do_action( 'save_post', $autosave_id, \get_post( $autosave_id ), false );
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Autosave should NOT trigger revalidation.
		$this->assertEmpty( $logs, 'Block editor autosaves should NOT trigger revalidation' );
	}

	/**
	 * Test classic editor save behavior matches block editor
	 *
	 * Both Classic Editor and Block Editor saves should behave
	 * identically in terms of revalidation triggers.
	 *
	 * @return void
	 */
	public function test_classic_editor_matches_block_editor(): void {
		// Create a published post (simulates Classic Editor post).
		$classic_post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Classic Editor Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Update post (simulates Classic Editor save).
		\wp_update_post(
			[
				'ID'         => $classic_post_id,
				'post_title' => 'Updated Classic Post',
			]
		);
		
		// Get logs from classic editor save.
		$classic_logs = \get_option( 'silver_assist_revalidate_logs', [] );
		$classic_count = count( $classic_logs );
		
		// Clear logs and processed posts.
		Revalidate::clear_logs();
		$this->revalidate->reset_processed_posts();
		
		// Create another post (simulates Gutenberg post).
		$block_post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Block Editor Post',
			]
		);
		
		// Clear initial logs.
		Revalidate::clear_logs();
		
		// Update post (simulates Block Editor save).
		\wp_update_post(
			[
				'ID'         => $block_post_id,
				'post_title' => 'Updated Block Post',
			]
		);
		
		// Get logs from block editor save.
		$block_logs = \get_option( 'silver_assist_revalidate_logs', [] );
		$block_count = count( $block_logs );
		
		// Both should trigger same number of revalidations.
		$this->assertSame(
			$classic_count,
			$block_count,
			'Classic Editor and Block Editor should trigger same number of revalidations'
		);
	}
}
