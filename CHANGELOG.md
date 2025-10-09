# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Custom post type support
- Manual revalidation button in admin
- Bulk revalidation tool
- Revalidation queue system

## [1.2.1] - 2025-10-09

### Added
- **Check Updates Button**: Manual update checking with one-click convenience
  - Added "Check Updates" button on Settings Hub dashboard card
  - Integration with `silverassist/wp-settings-hub` v1.1.0 custom actions feature
  - AJAX-powered update check with immediate visual feedback
  - Automatic redirect to updates page when new version available
  - New method: `AdminSettings::render_check_updates_script()` for AJAX handling

### Changed
- **Updated Dependencies**:
  - Upgraded `silverassist/wp-settings-hub` from v1.0.0 to v1.1.0
  - Added support for custom dashboard action buttons

### Technical Details
- **AdminSettings.php** (lines 118-127): Added `actions` parameter in `register_plugin()` with "Check Updates" button configuration
- **AdminSettings.php** (lines 211-272): New `render_check_updates_script()` method with complete AJAX handling
  - Uses wp-github-updater's `manualVersionCheck()` endpoint
  - Security: nonce verification with `silver_assist_revalidate_version_check`
  - User feedback: Button state changes (Checking... → Update Available! / Up to Date / Error)
  - Error handling: Network errors and API failures with 3-second auto-reset
  - Redirect: Automatic navigation to `plugins.php?plugin_status=upgrade` on update availability

## [1.2.0] - 2025-10-09

### Added
- **Tag Support**: Complete tag revalidation functionality
  - Automatic revalidation when tags are created, edited, or deleted
  - Tag paths included when posts are saved or deleted
  - All posts with a tag are revalidated when tag is modified
  - New hooks: `created_post_tag`, `edited_post_tag`, `delete_post_tag`
  - New method: `on_tag_updated()` for tag lifecycle events

- **Post Status Transition Support**: Smart revalidation on status changes
  - Revalidation triggered when posts are published or unpublished
  - Handles draft → publish transitions
  - Handles publish → draft transitions (unpublishing)
  - Handles publish → private transitions
  - New hook: `transition_post_status`
  - New method: `on_post_status_changed()` for status transitions

- **Post Deletion Support**: Revalidation on post deletion
  - Automatic revalidation when posts are permanently deleted
  - Revalidates post permalink, categories, and tags
  - New hook: `delete_post`
  - New method: `on_post_deleted()` for deletion events

- **Path Deduplication**: Prevents duplicate revalidation requests
  - Uses `array_unique()` to remove duplicate paths
  - Ensures each path is only revalidated once per operation
  - Improves performance and reduces API calls

- **Revalidation Debug Logs**: Complete traceability system for revalidation requests
  - Accordion-style debug section in admin settings page
  - Displays last 100 revalidation requests (FIFO rotation)
  - Shows request details (URL, method, headers, timeout) in formatted JSON
  - Shows response details (status code, message, body, headers) in formatted JSON
  - Color-coded status indicators (green for success, red for error)
  - Timestamp for each request
  - "Clear All Logs" button with AJAX confirmation
  - Helps identify duplicate requests and track server responses
  - Stores logs in WordPress options (`silver_assist_revalidate_logs`)

- **Comprehensive Test Suite**: 36 tests covering all scenarios (100% passing)
  - Unified test file with organized sections
  - Post lifecycle tests (create, edit, delete, draft filtering)
  - Post status transition tests (3 scenarios)
  - Taxonomy invalidation tests (categories + tags)
  - Deduplication tests
  - Category lifecycle tests (create, edit, delete)
  - Tag lifecycle tests (create, edit, delete)
  - Log management tests (entries, fields, FIFO, clear, empty endpoint)
  - Uses WordPress Test Suite (PHPUnit 9.6.29)
  - HTTP mocking for fast, reliable tests (0.3s execution time)
  
### Changed
- **Enhanced `Revalidate.php`**: Extended core revalidation class
  - Added 6 new WordPress action hooks (3 for posts, 3 for tags)
  - `on_post_saved()` now includes tag path revalidation
  - `on_post_status_changed()` bypasses status check for unpublish scenarios
  - `revalidate_paths()` now deduplicates paths before processing
  - Import added: `use WP_Post;` for type safety

- Enhanced `revalidate_paths()` method to capture and log all request/response data
- Admin settings page now includes debug section below configuration
- **Refactored assets to external files**: Moved inline CSS and JavaScript to separate files
  - CSS variables (design tokens) for consistent styling
  - Better maintainability and separation of concerns
  - Uses `wp_enqueue_style()` and `wp_enqueue_script()` properly
  - Uses `wp_localize_script()` for internationalization

### Fixed
- Status transition revalidation now works correctly for unpublishing posts
  - Previously failed because `on_post_saved()` checked for publish status
  - Now `on_post_status_changed()` handles revalidation directly
  - Properly revalidates post permalink, categories, and tags even after status change

### Technical
- New `on_post_deleted()` method for post deletion handling (lines 197-249)
- New `on_post_status_changed()` method for status transitions (lines 254-323)
- New `on_tag_updated()` method for tag lifecycle events (lines 325-374)
- New `save_log_entry()` private method in Revalidate class
- New `clear_logs()` static method in Revalidate class
- New `render_debug_logs_section()` method in AdminSettings class
- New `enqueue_admin_scripts()` method for proper asset loading
- New `ajax_clear_logs()` AJAX handler in AdminSettings class
- New `assets/css/admin-debug-logs.css` with CSS variables for design tokens
- New `assets/js/admin-debug-logs.js` with accordion and AJAX functionality
- Accordion functionality with jQuery for expanding/collapsing log details
- Responsive CSS styling with mobile breakpoints
- Build script updated to include `assets/` directory and validation
- Test files unified: `Revalidate_Test.php` (710 lines, 36 tests)
- WordPress Test Suite integration with PHP 8.4.1 and WordPress 6.8.3

## [1.1.0] - 2025-10-08

### Added
- **Settings Hub Integration**: Centralized "Silver Assist" settings menu
  - Plugin now appears under unified "Silver Assist" menu in WordPress admin
  - Dashboard with plugin cards showing version and description
  - Auto-registration system with `register_with_settings_hub()` method
  - Fallback to standalone settings page if Settings Hub is not available
  - Dependencies installed from Packagist (`silverassist/wp-settings-hub ^1.0`)
- **GitHub Updater Integration**: Automatic plugin updates from GitHub releases
  - Update notifications in WordPress admin
  - Seamless update experience
  - Dependencies installed from Packagist (`silverassist/wp-github-updater ^1.1`)
- **Version Update Script**: Automated version management (`scripts/update-version.sh`)
  - Updates version numbers across all plugin files
  - Interactive and CI/CD modes
  - Validates semantic versioning
  - Handles @version tags automatically
- **Build Script Enhancements**: Improved production build process
  - Automatic cleaning of Settings Hub package (removes dev files)
  - Validation checks for Settings Hub inclusion
  - Optimized vendor directory for smaller package size

### Changed
- AdminSettings now integrates with Settings Hub for centralized menu
- Menu location moved from "Settings > Post Revalidate" to "Silver Assist > Post Revalidate"
- Composer dependencies now installed from Packagist
- Build script includes Settings Hub package cleanup and validation

### Technical
- New `Updater.php` class for GitHub updates integration
- New `register_with_settings_hub()` method in AdminSettings class
- Updated `composer.json` with Packagist dependencies
- Enhanced `scripts/build-release.sh` with hub package cleaning
- Added comprehensive documentation to `.github/copilot-instructions.md`

### Dependencies
- Added: `silverassist/wp-settings-hub: ^1.0` (from Packagist)
- Added: `silverassist/wp-github-updater: ^1.1` (from Packagist)
- Existing: `composer/installers: ^2.0`

## [1.0.1] - 2025-10-06

### Added
- Settings link in plugins list page for quick access to configuration
  - Direct link from Plugins page to Settings > Post Revalidate
  - Appears as first action link before Deactivate/Delete
  - Improves user experience with one-click access to settings

### Changed
- Enhanced plugin action links with quick settings access

### Technical
- New method `add_settings_link()` in Plugin class
- Added `plugin_action_links` filter integration
- New unit test for settings link functionality
- Test coverage: 14 tests, 20 assertions (100% passing)

## [1.0.0] - 2025-10-06

### Added
- Initial release of Silver Assist Post Revalidate plugin
- Automatic revalidation on post save/update/delete
- Category revalidation on create/update/delete
- Admin settings page for endpoint and token configuration
- Version number display in admin settings page header
- User-Agent header in HTTP requests with plugin version
- Path-based revalidation (relative paths only, no domain)
- Support for standard WordPress "post" post type
- Debug logging when WP_DEBUG is enabled
- PSR-4 autoloading with composer
- WordPress Coding Standards compliance (PHPCS)
- PHPStan level 8 static analysis compliance
- PHPUnit test suite with 13 comprehensive tests (100% passing)
- Comprehensive PHPDoc documentation
- **Polyform Noncommercial License 1.0.0** - Free for noncommercial use
- GitHub automatic updates integration via silverassist/wp-github-updater package
- Updater class for seamless WordPress admin updates from GitHub releases
- Automated release build system (scripts/build-release.sh)
  - Production-ready package creation
  - Composer production dependencies only
  - Development file cleanup
  - Vendor directory optimization
  - WordPress.org readme.txt generation
  - ZIP archive creation with validation
  - CI/CD and local environment support
- GitHub Actions workflow for automated releases (.github/workflows/release.yml)
  - Automatic release creation on version tags (v*)
  - Manual release trigger via workflow_dispatch
  - Version validation and consistency checks
  - CHANGELOG.md entry verification
  - Automated build process with PHP 8.3
  - Checksum generation (MD5 and SHA256)
  - Build info documentation
  - Release notes extraction from CHANGELOG
  - Multi-PHP version testing (8.3, 8.4)
  - Quality checks: PHPCS, PHPStan Level 8, PHPUnit
  - Package structure validation
  - Artifact retention (90 days)
  - Success/failure notifications

### License
- Changed from GPL v2 to **Polyform Noncommercial 1.0.0**
- Allows free use, modification, and distribution for noncommercial purposes
- Commercial use requires separate commercial license
- Full license details in LICENSE file

### Testing
- 13 unit tests with 100% pass rate
- Brain Monkey + Mockery for WordPress function mocking
- Custom WP_Post stub for testing without WordPress dependency
- Tests cover: singleton pattern, post triggers, option saving, sanitization, validation

### Technical
- Plugin constants: `SILVER_ASSIST_REVALIDATE_VERSION` and `SILVER_ASSIST_REVALIDATE_PLUGIN_DIR`
- WordPress stubs for testing (php-stubs/wordpress-stubs v6.6+)
- Yoast PHPUnit Polyfills for compatibility
- Removed unused `SILVER_ASSIST_REVALIDATE_PLUGIN_URL` constant
- Singleton pattern implementation for all classes
- Modern PHP 8.3+ features

### Features
- **Plugin.php**: Main plugin initialization class
- **Revalidate.php**: Core revalidation functionality
- **AdminSettings.php**: Admin settings page
- Token-based authentication for endpoint requests
- WordPress Settings API integration
- Proper input sanitization and output escaping
- User capability checks for admin pages

### Author
- Developer: Silver Assist
- Website: http://silverassist.com/

### Developer Features
- Composer package configuration
- PHPCS configuration for WordPress standards
- PHPStan configuration for static analysis
- Git ignore file
- Comprehensive README documentation
- Project documentation in `.github/copilot-instructions.md`

### Security
- Input sanitization with `sanitize_url()` and `sanitize_text_field()`
- Output escaping with `esc_html()`, `esc_attr()`, `esc_url()`
- User capability checks with `current_user_can()`
- Nonce verification for settings forms (via WordPress Settings API)
- Direct file access prevention with `ABSPATH` checks

### Documentation
- Complete README with installation and usage instructions
- Code documentation with PHPDoc
- WordPress i18n ready with text domain
- Example Next.js API handler
- Troubleshooting guide

## Version History

- **1.0.1** - Settings link enhancement (October 6, 2025)
- **1.0.0** - Initial release (October 6, 2025)

---

[Unreleased]: https://github.com/SilverAssist/silver-assist-post-revalidate/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/SilverAssist/silver-assist-post-revalidate/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/SilverAssist/silver-assist-post-revalidate/releases/tag/v1.0.0
