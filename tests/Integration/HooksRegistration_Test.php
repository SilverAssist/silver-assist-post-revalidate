<?php
/**
 * WordPress Hooks Registration Integration Tests
 *
 * Tests that all WordPress hooks are properly registered with correct priorities
 * and argument counts. This ensures the plugin integrates correctly with WordPress
 * action and filter system.
 *
 * @package    RevalidatePosts
 * @subpackage Tests\Integration
 * @author     Silver Assist
 * @since      1.2.3
 * @version    1.2.3
 */

namespace RevalidatePosts\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use RevalidatePosts\Revalidate;
use WP_UnitTestCase;

/**
 * Test WordPress hooks registration
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class HooksRegistration_Test extends WP_UnitTestCase
{
	/**
	 * Test that save_post hook is registered correctly
	 *
	 * Verifies the save_post hook is registered with:
	 * - Priority: 10
	 * - Accepted args: 1
	 * - Callback: Revalidate instance method
	 *
	 * @return void
	 */
	public function test_save_post_hook_registered(): void {
		$instance = Revalidate::instance();
		
		// Check if hook is registered (has_action returns priority or false).
		$priority = \has_action( 'save_post', [ $instance, 'on_post_saved' ] );
		
		$this->assertNotFalse(
			$priority,
			'save_post hook should be registered'
		);
		
		// Verify priority is 10 (default).
		$this->assertSame(
			10,
			$priority,
			'save_post hook should have priority 10'
		);
		
		// Verify accepted args count.
		global $wp_filter;
		$callbacks = $wp_filter['save_post']->callbacks[10] ?? [];
		
		foreach ( $callbacks as $callback ) {
			if ( isset( $callback['function'] ) && 
			     is_array( $callback['function'] ) &&
			     $callback['function'][0] instanceof Revalidate &&
			     $callback['function'][1] === 'on_post_saved' ) {
				$this->assertSame(
					1,
					$callback['accepted_args'],
					'save_post hook should accept 1 argument'
				);
				return;
			}
		}
		
		$this->fail( 'save_post hook callback not found in wp_filter' );
	}

	/**
	 * Test that delete_post hook is registered correctly
	 *
	 * Verifies the delete_post hook is registered with:
	 * - Priority: 10
	 * - Accepted args: 1
	 * - Callback: Revalidate instance method
	 *
	 * @return void
	 */
	public function test_delete_post_hook_registered(): void {
		$instance = Revalidate::instance();
		
		// Check if hook is registered (has_action returns priority or false).
		$priority = \has_action( 'delete_post', [ $instance, 'on_post_deleted' ] );
		
		$this->assertNotFalse(
			$priority,
			'delete_post hook should be registered'
		);
		
		// Verify priority is 10 (default).
		$this->assertSame(
			10,
			$priority,
			'delete_post hook should have priority 10'
		);
		
		// Verify accepted args count.
		global $wp_filter;
		$callbacks = $wp_filter['delete_post']->callbacks[10] ?? [];
		
		foreach ( $callbacks as $callback ) {
			if ( isset( $callback['function'] ) && 
			     is_array( $callback['function'] ) &&
			     $callback['function'][0] instanceof Revalidate &&
			     $callback['function'][1] === 'on_post_deleted' ) {
				$this->assertSame(
					1,
					$callback['accepted_args'],
					'delete_post hook should accept 1 argument'
				);
				return;
			}
		}
		
		$this->fail( 'delete_post hook callback not found in wp_filter' );
	}

	/**
	 * Test that transition_post_status hook is registered correctly
	 *
	 * Verifies the transition_post_status hook is registered with:
	 * - Priority: 10
	 * - Accepted args: 3
	 * - Callback: Revalidate instance method
	 *
	 * @return void
	 */
	public function test_transition_post_status_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'transition_post_status', [ $instance, 'on_post_status_changed' ] );
		
		$this->assertNotFalse(
			$priority,
			'transition_post_status hook should be registered'
		);
		
		$this->assertSame(
			10,
			$priority,
			'transition_post_status hook should have priority 10'
		);
		
		// Verify accepted args count is 3 (new_status, old_status, post).
		global $wp_filter;
		$callbacks = $wp_filter['transition_post_status']->callbacks[10] ?? [];
		
		foreach ( $callbacks as $callback ) {
			if ( isset( $callback['function'] ) && 
			     is_array( $callback['function'] ) &&
			     $callback['function'][0] instanceof Revalidate &&
			     $callback['function'][1] === 'on_post_status_changed' ) {
				$this->assertSame(
					3,
					$callback['accepted_args'],
					'transition_post_status hook should accept 3 arguments'
				);
				return;
			}
		}
		
		$this->fail( 'transition_post_status hook callback not found in wp_filter' );
	}

	/**
	 * Test that created_category hook is registered correctly
	 *
	 * @return void
	 */
	public function test_created_category_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'created_category', [ $instance, 'on_category_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'created_category hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}

	/**
	 * Test that edited_category hook is registered correctly
	 *
	 * @return void
	 */
	public function test_edited_category_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'edited_category', [ $instance, 'on_category_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'edited_category hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}

	/**
	 * Test that delete_category hook is registered correctly
	 *
	 * @return void
	 */
	public function test_delete_category_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'delete_category', [ $instance, 'on_category_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'delete_category hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}

	/**
	 * Test that created_post_tag hook is registered correctly
	 *
	 * @return void
	 */
	public function test_created_post_tag_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'created_post_tag', [ $instance, 'on_tag_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'created_post_tag hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}

	/**
	 * Test that edited_post_tag hook is registered correctly
	 *
	 * @return void
	 */
	public function test_edited_post_tag_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'edited_post_tag', [ $instance, 'on_tag_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'edited_post_tag hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}

	/**
	 * Test that delete_post_tag hook is registered correctly
	 *
	 * @return void
	 */
	public function test_delete_post_tag_hook_registered(): void {
		$instance = Revalidate::instance();
		
		$priority = \has_action( 'delete_post_tag', [ $instance, 'on_tag_updated' ] );
		
		$this->assertNotFalse(
			$priority,
			'delete_post_tag hook should be registered'
		);
		
		$this->assertSame( 10, $priority );
	}
}
