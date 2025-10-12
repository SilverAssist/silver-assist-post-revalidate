<?php
/**
 * Configuration Unit Tests
 *
 * Tests for the Configuration class that manages enabled post types.
 *
 * @package RevalidatePosts
 * @since 1.4.0
 */

namespace RevalidatePosts\Tests\Unit;

use RevalidatePosts\Configuration;
use WP_UnitTestCase;

/**
 * Configuration test case
 *
 * @since 1.4.0
 */
class Configuration_Test extends WP_UnitTestCase {

	/**
	 * Test that Configuration is a singleton
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_configuration_is_singleton(): void {
		$instance1 = Configuration::instance();
		$instance2 = Configuration::instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Configuration::class, $instance1 );
	}

	/**
	 * Test get_enabled_post_types returns default post type
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_enabled_post_types_returns_default(): void {
		$config = Configuration::instance();
		$enabled = $config->get_enabled_post_types();

		$this->assertIsArray( $enabled );
		$this->assertContains( 'post', $enabled );
		$this->assertCount( 1, $enabled );
	}

	/**
	 * Test is_post_type_enabled returns true for post
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_is_post_type_enabled_returns_true_for_post(): void {
		$config = Configuration::instance();

		$this->assertTrue( $config->is_post_type_enabled( 'post' ) );
	}

	/**
	 * Test is_post_type_enabled returns false for disabled types
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_is_post_type_enabled_returns_false_for_disabled_types(): void {
		$config = Configuration::instance();

		$this->assertFalse( $config->is_post_type_enabled( 'page' ) );
		$this->assertFalse( $config->is_post_type_enabled( 'product' ) );
		$this->assertFalse( $config->is_post_type_enabled( 'custom_type' ) );
	}

	/**
	 * Test get_post_type_object returns object for enabled type
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_object_returns_object_for_enabled_type(): void {
		$config = Configuration::instance();
		$type_obj = $config->get_post_type_object( 'post' );

		$this->assertInstanceOf( \WP_Post_Type::class, $type_obj );
		$this->assertEquals( 'post', $type_obj->name );
	}

	/**
	 * Test get_post_type_object returns null for disabled type
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_object_returns_null_for_disabled_type(): void {
		$config = Configuration::instance();
		$type_obj = $config->get_post_type_object( 'page' );

		$this->assertNull( $type_obj );
	}

	/**
	 * Test get_post_type_label returns correct label for post
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_label_returns_correct_label(): void {
		$config = Configuration::instance();
		$label = $config->get_post_type_label( 'post' );

		$this->assertEquals( 'Post', $label );
	}

	/**
	 * Test get_post_type_label returns slug for disabled type
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_label_returns_slug_for_disabled_type(): void {
		$config = Configuration::instance();
		$label = $config->get_post_type_label( 'custom_type' );

		$this->assertEquals( 'custom_type', $label );
	}

	/**
	 * Test get_post_type_label_plural returns correct plural label
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_label_plural_returns_correct_label(): void {
		$config = Configuration::instance();
		$label = $config->get_post_type_label_plural( 'post' );

		$this->assertEquals( 'Posts', $label );
	}

	/**
	 * Test get_post_type_label_plural returns slug for disabled type
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function test_get_post_type_label_plural_returns_slug_for_disabled_type(): void {
		$config = Configuration::instance();
		$label = $config->get_post_type_label_plural( 'custom_type' );

		$this->assertEquals( 'custom_type', $label );
	}
}
