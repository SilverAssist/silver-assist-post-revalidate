<?php
/**
 * Silver Assist Post Revalidate Updater - GitHub Updates Integration
 *
 * Integrates the reusable silverassist/wp-github-updater package for automatic updates
 * from public GitHub releases. Provides seamless WordPress admin updates.
 *
 * @package RevalidatePosts
 * @since 1.0.0
 * @author Silver Assist
 * @version 1.3.0
 * @license Polyform Noncommercial 1.0.0
 */

namespace RevalidatePosts;

defined( 'ABSPATH' ) || exit;

use SilverAssist\WpGithubUpdater\Updater as GitHubUpdater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

/**
 * Class Updater
 *
 * Extends the reusable GitHub updater package with Silver Assist Post Revalidate specific configuration.
 * This approach reduces code duplication and centralizes update logic maintenance.
 *
 * @since 1.0.0
 */
class Updater extends GitHubUpdater
{
	/**
	 * Initialize the Silver Assist Post Revalidate updater with specific configuration
	 *
	 * @since 1.0.0
	 * @param string $plugin_file Path to main plugin file.
	 * @param string $github_repo GitHub repository (username/repository).
	 */
	public function __construct( string $plugin_file, string $github_repo )
	{
		$config = new UpdaterConfig(
			$plugin_file,
			$github_repo,
			[
				'plugin_name'        => 'Silver Assist Post Revalidate',
				'plugin_description' => 'WordPress plugin that automatically revalidates posts and categories when content changes, ' .
					'sending requests to a configured endpoint for cache invalidation (e.g., Next.js ISR).',
				'plugin_author'      => 'Silver Assist',
				'plugin_homepage'    => "https://github.com/{$github_repo}",
				'requires_wordpress' => '6.5',
				'requires_php'       => '8.3',
				'asset_pattern'      => 'silver-assist-post-revalidate-v{version}.zip',
				'cache_duration'     => 12 * 3600, // 12 hours.
				'ajax_action'        => 'silver_assist_revalidate_check_version',
				'ajax_nonce'         => 'silver_assist_revalidate_version_check',
			]
		);

		parent::__construct( $config );
	}
}
