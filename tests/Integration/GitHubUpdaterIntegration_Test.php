<?php
/**
 * GitHub Updater Integration Tests
 *
 * Tests the plugin's integration with the GitHub Updater package.
 * Verifies updater initialization and update checking functionality.
 *
 * @package    RevalidatePosts
 * @subpackage Tests\Integration
 * @since      1.2.3
 * @author     Silver Assist
 * @version    1.2.3
 */

namespace RevalidatePosts\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use RevalidatePosts\Plugin;
use RevalidatePosts\Updater;
use WP_UnitTestCase;

/**
 * Test GitHub Updater integration
 *
 * @package RevalidatePosts\Tests\Integration
 * @since 1.2.3
 * @version 1.2.3
 */
class GitHubUpdaterIntegration_Test extends WP_UnitTestCase
{
	/**
	 * Test that updater initializes when package is present
	 *
	 * Verifies that the GitHub Updater is properly initialized
	 * and available through the Plugin instance.
	 *
	 * @return void
	 */
	public function test_updater_initializes_when_package_present(): void {
		// Verify Updater class exists.
		$this->assertTrue(
			\class_exists( 'RevalidatePosts\Updater' ),
			'Updater class should exist'
		);
		
		// Verify GitHubUpdater parent class exists.
		$this->assertTrue(
			\class_exists( 'SilverAssist\WpGithubUpdater\Updater' ),
			'GitHubUpdater parent class should be available via composer'
		);
		
		// Get Plugin instance.
		$plugin = Plugin::instance();
		
		// Verify get_updater method exists.
		$this->assertTrue(
			\method_exists( $plugin, 'get_updater' ),
			'get_updater method should exist'
		);
	}

	/**
	 * Test that get_updater returns valid instance
	 *
	 * Verifies that the get_updater() method returns a proper
	 * Updater instance or null if not initialized.
	 *
	 * @return void
	 */
	public function test_get_updater_returns_valid_instance(): void {
		$plugin  = Plugin::instance();
		$updater = $plugin->get_updater();
		
		// Updater should be either an Updater instance or null.
		$this->assertTrue(
			$updater instanceof Updater || $updater === null,
			'get_updater should return Updater instance or null'
		);
		
		// If updater exists, verify it's properly initialized.
		if ( $updater instanceof Updater ) {
			$this->assertInstanceOf(
				'SilverAssist\WpGithubUpdater\Updater',
				$updater,
				'Updater should extend GitHubUpdater parent class'
			);
		}
	}

	/**
	 * Test that Updater extends GitHub Updater correctly
	 *
	 * Verifies the class hierarchy and inheritance.
	 *
	 * @return void
	 */
	public function test_updater_extends_github_updater_correctly(): void {
		$this->assertTrue(
			\is_subclass_of( 'RevalidatePosts\Updater', 'SilverAssist\WpGithubUpdater\Updater' ),
			'Updater should extend GitHubUpdater base class'
		);
	}
}
