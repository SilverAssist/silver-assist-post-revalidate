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

- **1.0.0** - Initial release (October 6, 2025)

---

[Unreleased]: https://github.com/SilverAssist/silver-assist-post-revalidate/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/SilverAssist/silver-assist-post-revalidate/releases/tag/v1.0.0
