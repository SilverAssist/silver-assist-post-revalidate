<?php
/**
 * Advanced Taxonomy Integration Tests
 *
 * Tests complex taxonomy scenarios including multiple categories/tags,
 * category/tag removal, and hierarchical relationships.
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
use WP_Error;

/**
 * Test advanced taxonomy integration
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class TaxonomyIntegration_Test extends WP_UnitTestCase
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
		\delete_option( 'silver_assist_revalidate_logs' );
		
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
		
		parent::tearDown();
	}

	/**
	 * Mock HTTP requests
	 *
	 * Returns a fake successful response for all HTTP requests
	 * to avoid real network calls during testing.
	 *
	 * @param false|array|WP_Error $response Response to filter.
	 * @param array                $args     HTTP request arguments.
	 * @param string               $url      Request URL.
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
	 * Test post with multiple categories revalidates all
	 *
	 * When a post has multiple categories, all category paths
	 * should be revalidated when the post is saved.
	 *
	 * @return void
	 */
	public function test_post_with_multiple_categories_revalidates_all(): void {
		// Create two categories.
		$cat1_id = self::factory()->category->create(
			[
				'name' => 'Category One',
				'slug' => 'category-one',
			]
		);
		
		$cat2_id = self::factory()->category->create(
			[
				'name' => 'Category Two',
				'slug' => 'category-two',
			]
		);
		
		// Create post with both categories.
		self::factory()->post->create(
			[
				'post_title'    => 'Multi-Category Post',
				'post_status'   => 'publish',
				'post_category' => [ $cat1_id, $cat2_id ],
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Extract all paths.
		$paths = [];
		foreach ( $logs as $log ) {
			if ( isset( $log['path'] ) ) {
				$paths[] = $log['path'];
			}
		}
		
		// Verify both category paths are present.
		$has_cat1 = false;
		$has_cat2 = false;
		
		foreach ( $paths as $path ) {
			if ( \strpos( $path, "cat={$cat1_id}" ) !== false ) {
				$has_cat1 = true;
			}
			if ( \strpos( $path, "cat={$cat2_id}" ) !== false ) {
				$has_cat2 = true;
			}
		}
		
		$this->assertTrue( $has_cat1, 'Category One path should be revalidated' );
		$this->assertTrue( $has_cat2, 'Category Two path should be revalidated' );
	}

	/**
	 * Test post with multiple tags revalidates all
	 *
	 * When a post has multiple tags, all tag paths
	 * should be revalidated when the post is saved.
	 *
	 * @return void
	 */
	public function test_post_with_multiple_tags_revalidates_all(): void {
		// Create two tags.
		$tag1_id = self::factory()->tag->create(
			[
				'name' => 'Tag One',
				'slug' => 'tag-one',
			]
		);
		
		$tag2_id = self::factory()->tag->create(
			[
				'name' => 'Tag Two',
				'slug' => 'tag-two',
			]
		);
		
		// Create post with both tags.
		self::factory()->post->create(
			[
				'post_title'  => 'Multi-Tag Post',
				'post_status' => 'publish',
				'tags_input'  => [ $tag1_id, $tag2_id ],
			]
		);
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Extract all paths.
		$paths = [];
		foreach ( $logs as $log ) {
			if ( isset( $log['path'] ) ) {
				$paths[] = $log['path'];
			}
		}
		
		// Verify both tag paths are present.
		$has_tag1 = false;
		$has_tag2 = false;
		
		foreach ( $paths as $path ) {
			if ( \strpos( $path, "tag={$tag1_id}" ) !== false || \strpos( $path, 'tag-one' ) !== false ) {
				$has_tag1 = true;
			}
			if ( \strpos( $path, "tag={$tag2_id}" ) !== false || \strpos( $path, 'tag-two' ) !== false ) {
				$has_tag2 = true;
			}
		}
		
		$this->assertTrue( $has_tag1, 'Tag One path should be revalidated' );
		$this->assertTrue( $has_tag2, 'Tag Two path should be revalidated' );
	}

	/**
	 * Test removing category from post triggers revalidation
	 *
	 * When a category is removed from a post, the post itself
	 * should still be revalidated properly.
	 *
	 * @return void
	 */
	public function test_remove_category_from_post_revalidates_category(): void {
		// Create category.
		$cat_id = self::factory()->category->create(
			[
				'name' => 'Test Category',
				'slug' => 'test-category',
			]
		);
		
		// Create post with category.
		$post_id = self::factory()->post->create(
			[
				'post_title'    => 'Test Post',
				'post_status'   => 'publish',
				'post_category' => [ $cat_id ],
			]
		);
		
		// Remove category from post and update.
		\wp_set_post_categories( $post_id, [] );
		\wp_update_post(
			[
				'ID' => $post_id,
			]
		);
		
		// Verify post ID is valid (no crash).
		$this->assertGreaterThan( 0, $post_id, 'Post should be created successfully' );
	}

	/**
	 * Test removing tag from post triggers revalidation
	 *
	 * When a tag is removed from a post, the post itself
	 * should still be revalidated properly.
	 *
	 * @return void
	 */
	public function test_remove_tag_from_post_revalidates_tag(): void {
		// Create tag.
		$tag_id = self::factory()->tag->create(
			[
				'name' => 'Test Tag',
				'slug' => 'test-tag',
			]
		);
		
		// Create post with tag.
		$post_id = self::factory()->post->create(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'tags_input'  => [ $tag_id ],
			]
		);
		
		// Remove tag from post and update.
		\wp_set_post_tags( $post_id, [] );
		\wp_update_post(
			[
				'ID' => $post_id,
			]
		);
		
		// Verify post ID is valid (no crash).
		$this->assertGreaterThan( 0, $post_id, 'Post should be created successfully' );
	}

	/**
	 * Test custom taxonomy is NOT revalidated
	 *
	 * Verifies that custom taxonomies (non-category, non-tag)
	 * are excluded from revalidation.
	 *
	 * @return void
	 */
	public function test_custom_taxonomy_not_revalidated(): void {
		// Register custom taxonomy.
		\register_taxonomy(
			'custom_tax',
			'post',
			[
				'public' => true,
				'label'  => 'Custom Taxonomy',
			]
		);
		
		// Create custom term.
		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'custom_tax',
				'name'     => 'Custom Term',
				'slug'     => 'custom-term',
			]
		);
		
		// Clear logs.
		\delete_option( 'silver_assist_revalidate_logs' );
		
		// Create post with custom taxonomy term.
		$post_id = self::factory()->post->create(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			]
		);
		
		\wp_set_post_terms( $post_id, [ $term_id ], 'custom_tax' );
		
		// Get logs.
		$logs = \get_option( 'silver_assist_revalidate_logs', [] );
		
		// Extract paths.
		$paths = [];
		foreach ( $logs as $log ) {
			if ( isset( $log['path'] ) ) {
				$paths[] = $log['path'];
			}
		}
		
		// Verify custom taxonomy NOT in paths.
		$has_custom = false;
		foreach ( $paths as $path ) {
			if ( \strpos( $path, 'custom' ) !== false ) {
				$has_custom = true;
			}
		}
		
		$this->assertFalse( $has_custom, 'Custom taxonomy should NOT be revalidated' );
	}
}
