# Silver Assist Post Revalidate - WordPress Plugin

## Project Overview

**Plugin Name**: Silver Assist Post Revalidate  
**Namespace**: `RevalidatePosts`  
**Text Domain**: `silver-assist-revalidate-posts`  
**Minimum WordPress**: 6.5  
**Minimum PHP**: 8.2  
**License**: GPL v2 or later

## Purpose

This WordPress plugin provides automatic cache revalidation for posts, categories, and tags. When content is created, updated, deleted, or status changes, the plugin automatically triggers revalidation requests to a configured endpoint (typically a Next.js application) to ensure fresh content delivery.

### Key Features

- Automatic revalidation on post save/update/delete/status change
- Category-based revalidation when categories are modified
- **Tag support**: Full tag lifecycle revalidation (create/edit/delete) (NEW in v1.2.0)
- **Status transition support**: Smart revalidation on publish/unpublish (NEW in v1.2.0)
- **Post deletion support**: Automatic revalidation when posts are deleted (NEW in v1.2.0)
- **Path deduplication**: Prevents duplicate revalidation requests (NEW in v1.2.0)
- **Debug logs viewer**: Built-in UI to track all revalidation requests/responses
- Admin settings page for endpoint and token configuration
- **Settings Hub integration**: Centralized "Silver Assist" menu
- **GitHub auto-updates**: Automatic plugin updates from releases
- Path-based revalidation (excludes domain, sends only relative paths)
- Support for standard WordPress post types (extensible for custom post types in future versions)
- **Comprehensive test suite**: 70 tests (47 Unit + 23 Integration), 100% passing
- **Token masking security**: Password field with show/hide toggle (v1.2.3)
  - Sanitization tested: XSS protection, masked value detection, new token acceptance
  - UI components: Visual masking (last 4 chars), toggle button, jQuery functionality
  - Security layers: Browser-native masking, visual masking, optional reveal, sanitization

## Architecture

### File Structure

```
silver-assist-post-revalidate/
├── .github/
│   ├── copilot-instructions.md
│   └── workflows/
│       ├── release.yml              # Release workflow (uses run-quality-checks.sh)
│       └── dependency-updates.yml   # Dependency workflow (uses run-quality-checks.sh)
├── assets/
│   ├── css/
│   │   └── admin-debug-logs.css  # Debug logs styling with CSS variables
│   └── js/
│       └── admin-debug-logs.js   # Accordion & AJAX functionality
├── Includes/
│   ├── AdminSettings.php       # Admin settings page + debug logs UI
│   ├── Plugin.php              # Main plugin initialization class
│   ├── Revalidate.php          # Core revalidation functionality + logging
│   └── Updater.php             # GitHub auto-update integration
├── scripts/
│   ├── build-release.sh        # Production build generator
│   ├── run-quality-checks.sh   # Centralized quality checks runner (NEW v1.4.0)
│   └── update-version.sh       # Automated version management
├── silver-assist-post-revalidate.php  # Main plugin file
├── composer.json               # Dependencies and PSR-4 autoloading
├── phpcs.xml                   # WordPress Coding Standards configuration
├── phpstan.neon                # Static analysis configuration
├── README.md                   # Plugin documentation
├── CHANGELOG.md                # Version history
└── .gitignore                  # Git ignore rules
```

### Class Structure

#### 1. **Plugin.php** (Main Initialization)
- Singleton pattern for plugin initialization
- Loads composer autoloader
- Initializes Revalidate and AdminSettings classes
- Handles plugin activation/deactivation hooks

#### 2. **Revalidate.php** (Core Functionality)
- Listens to WordPress hooks: `save_post`, `created_category`, `edited_category`, `delete_category`
- Extracts permalinks and category paths
- Converts full URLs to relative paths
- Sends revalidation requests to configured endpoint
- **Logs all requests/responses**: Captures full request/response data for debugging
- **Auto-rotation**: Maintains maximum 100 log entries (FIFO)
- Stores logs in WordPress option: `silver_assist_revalidate_logs`

#### 3. **AdminSettings.php** (Admin Interface)
- Integrates with Settings Hub for centralized menu
- Creates settings page under "Silver Assist" menu
- Manages two options: `revalidate_endpoint` and `revalidate_token`
- Uses WordPress Settings API for secure option handling
- Provides user-friendly interface for configuration
- Falls back to standalone settings page if hub not available
- **Debug Logs UI**: Accordion-style viewer for revalidation logs
- **Asset Management**: Enqueues CSS/JS files properly with versioning
- **AJAX Handler**: Clear logs functionality with nonce verification
- **Token Security** (v1.2.3):
  - `sanitize_token()`: Detects masked values (bullet points), preserves existing token
  - `render_token_field()`: Password input with visual masking (shows last 4 chars)
  - Toggle button: Show/hide token with jQuery, dashicons icons
  - Inline JavaScript: Type switching (password ↔ text), value switching (masked ↔ original)

#### 4. **Updater.php** (GitHub Updates)
- Integrates GitHub Updater package for automatic updates
- Checks for new releases from GitHub repository
- Provides automatic update notifications in WordPress admin
- Handles plugin metadata for update system

#### 5. **Assets (CSS & JavaScript)**

##### **assets/css/admin-debug-logs.css**
- **CSS Variables (Design Tokens)**: Centralized styling system
  - Colors: `--sa-color-success`, `--sa-color-error`, `--sa-color-border`
  - Spacing: `--sa-spacing-xs` through `--sa-spacing-2xl`
  - Typography: `--sa-font-size-xs` through `--sa-font-size-md`
  - Borders: `--sa-radius-sm`, `--sa-radius-md`
- **Responsive Design**: Mobile breakpoints at 782px
- **Status Colors**: Green for success (200-299), red for errors
- **Modern CSS**: Flexbox layout, transitions, user-select

##### **assets/js/admin-debug-logs.js**
- **IIFE Pattern**: Self-contained, no global pollution
- **jQuery Integration**: WordPress-style jQuery usage
- **Accordion Functionality**: Toggle log details on click
- **AJAX Clear Logs**: Confirmation dialog + server request
- **i18n Ready**: Uses `wp_localize_script()` for translations
- **Error Handling**: Proper success/error callbacks

### Dependencies

The plugin uses Composer for dependency management:

1. **silverassist/wp-github-updater** (^1.1)
   - Automatic plugin updates from GitHub releases
   - Update notifications in WordPress admin
   - Seamless update experience

2. **silverassist/wp-settings-hub** (^1.0)
   - Centralized "Silver Assist" settings menu
   - Unified dashboard for all Silver Assist plugins
   - Auto-registration system
   - Plugin cards with version display
   - Optional navigation tabs between plugins

3. **composer/installers** (^2.0)
   - WordPress plugin installer
   - Handles proper WordPress directory structure

**Installation**: Dependencies are installed from Packagist using `composer install`

## Code Standards & Quality

### ⚠️ MANDATORY: Test-Driven Development (TDD)

**CRITICAL RULE**: ALL new functionality MUST follow strict TDD methodology.

#### TDD Process (Red-Green-Refactor)
1. **RED**: Write failing test FIRST
   - Test must fail for the right reason
   - Verify test failure before any implementation
   - Use `--stop-on-failure` to catch failures immediately

2. **GREEN**: Write minimum code to pass
   - Implement ONLY enough code to make test pass
   - Do NOT add extra features
   - Run test to verify it passes

3. **REFACTOR**: Improve code quality
   - Clean up implementation
   - Remove duplication
   - Improve naming and structure
   - Tests must still pass after refactor

#### TDD Rules
- **NEVER write implementation code before tests**
- **NEVER write tests after implementation** (that's validation, not TDD)
- **One test at a time**: Write test → Make it pass → Next test
- **Test behavior, not implementation**: Focus on what, not how
- **Run tests frequently**: After every small change

#### TDD Violations
- ❌ Writing implementation first, then adding tests
- ❌ Writing all tests at once, then implementing
- ❌ Skipping test phase for "simple" features
- ❌ Modifying tests to match implementation (should be opposite)

#### When to Use TDD
- ✅ ALL new features (no exceptions)
- ✅ ALL bug fixes (write failing test reproducing bug first)
- ✅ ALL refactoring (tests verify behavior unchanged)
- ✅ ALL security features (token masking, sanitization, etc.)

#### Test Coverage Standards
- **Unit Tests**: Test individual methods in isolation
- **Integration Tests**: Test component interactions
- **Security Tests**: Test sanitization, XSS protection, capability checks
- **Target**: 100% coverage of critical paths

### PHP Coding Standards

#### String Quotation Rules
- **Follow WordPress standards**: Use single quotes for simple strings, double quotes for interpolation
- **i18n functions**: Use single quotes: `__( 'Text', 'silver-assist-revalidate-posts' )`
- **String interpolation**: Use double quotes when embedding variables
- **sprintf() with positional placeholders**: Use `%1$s`, `%2$d` format for translator-friendly strings

#### Modern PHP 8.2+ Conventions
- **Short array syntax**: `[]` not `array()`
- **Namespaces**: Use `RevalidatePosts\` prefix
- **Singleton pattern**: `Class_Name::instance()` method
- **WordPress hooks**: `\add_action("init", [$this, "method"])` with array callbacks
- **String interpolation**: Use `"prefix_{$variable}"` instead of concatenation
- **Match over switch**: Use `match` expressions for cleaner code
- **Typed properties**: Use proper type declarations for all class properties

#### Global Function Prefixes
- **WordPress functions**: MUST use `\` prefix in namespaced context (e.g., `\add_action()`, `\get_option()`, `\wp_remote_get()`)
- **PHP native functions**: Do NOT use `\` prefix (e.g., `array_key_exists()`, `trim()`, `sprintf()`)

#### Import Organization (PSR-4)
- **MANDATORY**: All `use` statements at top of file after namespace
- **Alphabetical ordering**: ALWAYS sort imports alphabetically
- **No in-method imports**: NEVER use fully qualified names in methods
- **Same namespace rule**: NEVER import classes from same namespace

### Documentation Requirements

#### PHPDoc Standards
- Complete PHPDoc for ALL classes, methods, and properties
- English only for all documentation
- Required tags: `@package`, `@since`, `@author`, `@version`
- Method documentation must include `@param` and `@return` tags
- Use `@throws` for exceptions

#### WordPress i18n Standards
- **Text domain**: `'silver-assist-revalidate-posts'` for ALL i18n functions
- **ALL user-facing strings**: Must use WordPress i18n functions
- **Functions**: `__( 'text', 'silver-assist-revalidate-posts' )`, `esc_html_e( 'text', 'silver-assist-revalidate-posts' )`
- **sprintf() comments**: ALWAYS add translator comments for placeholders
  ```php
  /* translators: %d: number of paths revalidated */
  $message = sprintf( __( 'Revalidated %d paths', 'silver-assist-revalidate-posts' ), $count );
  ```

### File Creation Rules
- PHP classes: `PascalCase.php` following PSR-4 structure
- Start files with `defined( 'ABSPATH' ) || exit;`
- One class per file
- File name MUST match class name exactly

### Security Best Practices
- Sanitize all input: `\sanitize_text_field()`, `\sanitize_url()`
- Escape all output: `\esc_html()`, `\esc_attr()`, `\esc_url()`
- Verify nonces for form submissions
- Check user capabilities: `\current_user_can("manage_options")`
- Validate option values before saving

## Quality Assurance

### ⚠️ CRITICAL: Use Centralized Quality Checks Script

**ALWAYS use `scripts/run-quality-checks.sh` for running quality checks.** This script is the single source of truth for all quality standards and ensures consistency across local development, CI/CD workflows, and manual testing.

#### Quick Start

```bash
# Run ALL quality checks (recommended before commit)
bash scripts/run-quality-checks.sh all

# Run only fast checks (no WordPress Test Suite setup)
bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan

# Run specific checks
bash scripts/run-quality-checks.sh phpcs phpstan syntax
bash scripts/run-quality-checks.sh phpunit

# Get help
bash scripts/run-quality-checks.sh --help
```

### Centralized Quality Checks Script

Location: `scripts/run-quality-checks.sh`

**Purpose**: Single source of truth for all quality checks used in CI/CD workflows and local development.

#### Available Checks

1. **composer-validate** - Validates composer.json and composer.lock
2. **phpcs** - WordPress Coding Standards (--warning-severity=0)
3. **phpstan** - Static Analysis Level 8
4. **phpunit** - PHPUnit with WordPress Test Suite
5. **syntax** - PHP syntax check (php -l) on all files
6. **all** - Runs all checks in order (default)

#### Configuration Options

```bash
--help                  Show help message
--verbose               Verbose output (set -x)
--skip-wp-setup         Skip WordPress Test Suite setup (for fast checks)
--php-version VERSION   PHP version (default: 8.3)
--wp-version VERSION    WordPress version (default: latest)
--db-name NAME          Database name (default: wordpress_test)
--db-user USER          Database user (default: root)
--db-pass PASS          Database password (default: root)
--db-host HOST          Database host (default: localhost)
```

#### Usage Examples

```bash
# Development workflow (fast checks before commit)
bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan

# Full pre-commit check with all tests
bash scripts/run-quality-checks.sh all

# CI/CD mode (used by release.yml and dependency-updates.yml)
bash scripts/run-quality-checks.sh all

# Debug mode with verbose output
bash scripts/run-quality-checks.sh --verbose phpunit

# Custom database configuration
bash scripts/run-quality-checks.sh \
  --db-name my_test_db \
  --db-pass secret \
  phpunit

# Setup WordPress Test Suite only
bash scripts/run-quality-checks.sh setup-wp
```

#### What It Does

1. **Setup Phase** (if not skipped):
   - Installs system dependencies (subversion, mysql-client)
   - Starts MySQL service
   - Creates test database
   - Installs WordPress Test Suite via `install-wp-tests.sh`

2. **Quality Checks Phase**:
   - Runs selected checks in order
   - Each check has colored output with section headers
   - Exits on first error (`set -e`)
   - Shows summary at the end

3. **Output Format**:
   - 🎨 Colored output (green=success, red=error, yellow=warning, blue=info)
   - Clear section headers with decorative borders
   - Summary of all checks at the end
   - Exit code 0 on success, 1 on failure

#### Integration with Workflows

**release.yml**:
```yaml
- name: 🧪 Run Quality Checks
  run: bash scripts/run-quality-checks.sh all
```

**dependency-updates.yml**:
```yaml
- name: 🧪 Run Quality Checks
  run: bash scripts/run-quality-checks.sh --skip-wp-setup all
```

### Manual Quality Checks (DEPRECATED - Use Script Instead)

**⚠️ WARNING**: Running individual commands is deprecated. Use `run-quality-checks.sh` instead.

For reference only (do NOT use these directly):

```bash
# DEPRECATED: Use run-quality-checks.sh phpcs instead
vendor/bin/phpcbf
vendor/bin/phpcs --warning-severity=0

# DEPRECATED: Use run-quality-checks.sh phpstan instead  
php -d memory_limit=1G vendor/bin/phpstan analyse Includes/ --no-progress

# DEPRECATED: Use run-quality-checks.sh syntax instead
find Includes/ -name "*.php" -exec php -l {} \;

# DEPRECATED: Use run-quality-checks.sh phpunit instead
vendor/bin/phpunit
```

### Required Quality Checks Before Commit

**MANDATORY**: Run this command before every commit:

```bash
bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan
```

**RECOMMENDED**: Run full suite including tests:

```bash
bash scripts/run-quality-checks.sh all
```

### PHPStan Configuration
- **Level**: 8 (maximum strictness)
- **Memory**: 1GB minimum
- **Configuration**: `phpstan.neon`
- **Type Safety**: Handle functions returning `string|false` properly
- **Execution**: Use `bash scripts/run-quality-checks.sh phpstan` instead of direct command

### Development Workflow

**CRITICAL**: Always use the centralized quality checks script for validation.

1. **Write code** following WordPress and PHP standards
2. **Auto-fix formatting**: `bash scripts/run-quality-checks.sh --skip-wp-setup phpcs`
   - PHPCBF runs automatically within the script
3. **Run fast checks**: `bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan`
   - Validates code style and static analysis without WordPress setup
4. **Run full test suite**: `bash scripts/run-quality-checks.sh all`
   - Includes PHPUnit tests with WordPress Test Suite
5. **Test functionality** in WordPress environment
6. **Verify admin settings** work correctly
7. **Test revalidation** with actual endpoint

### Pre-Commit Checklist

✅ **Mandatory** (fast, ~10 seconds):
```bash
bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan
```

✅ **Recommended** (full suite, ~2 minutes):
```bash
bash scripts/run-quality-checks.sh all
```

✅ **Verify**:
- No PHPCS errors
- No PHPStan errors
- All PHPUnit tests passing
- No PHP syntax errors

### GitHub Actions and CI/CD Workflow Management

**IMPORTANT**: When working with GitHub Actions workflows (`.github/workflows/` files):

- **Always use GitHub Desktop for commits that touch workflow files** due to OAuth permission limitations
- The git CLI will reject pushes to workflow files with error: "refusing to allow an OAuth App to create or update workflow without `workflow` scope"
- **Workflow for Actions changes**:
  1. Make changes to `.github/workflows/` files
  2. Commit other files via git CLI if needed
  3. Use GitHub Desktop to commit and push workflow file changes
  4. Monitor CI execution using `gh` CLI tools

#### GitHub CLI (gh) for CI/CD Monitoring

**Prerequisites**: Ensure `gh` CLI is installed locally for GitHub Actions monitoring

**Common Commands**:
- `gh run list --limit 3` - View last 3 workflow runs (most commonly used)
- `gh run list --limit 5` - View recent workflow runs
- `gh run view <run-id>` - View specific run details
- `gh run watch <run-id>` - Monitor run in real-time
- `gh run list --limit 1 --json databaseId,status,conclusion --jq '.[0]'` - Quick status of latest run
- `gh run view --log --job=<job-id>` - View detailed job logs

**Pager Management**:
- Use `PAGER=cat` prefix to avoid interactive pager for long outputs
- Example: `PAGER=cat gh run view --log --job=12345`
- Example: `PAGER=cat gh run list --limit 3` - Avoid pager for run lists
- Long CI logs may require pager navigation or output redirection
- For quick monitoring, `gh run list --limit 3` is the most efficient command

**Debugging Workflow**:
1. Make changes and push via GitHub Desktop (for workflow files)
2. Monitor with `gh run list --limit 3` to get latest run ID
3. Watch progress with `gh run watch <run-id>`
4. Debug issues with `PAGER=cat gh run view --log --job=<job-id>`
5. For quick status checks, use `gh run list --limit 3` repeatedly

**Example Monitoring Session**:
```bash
# Quick check of recent runs
gh run list --limit 3

# Watch the latest run in real-time
gh run watch 17814735403

# Get detailed logs without pager
PAGER=cat gh run view --log --job=50645780001

# Check latest run status (JSON output)
PAGER=cat gh run list --limit 1 --json databaseId,status,conclusion --jq '.[0]'
```

## Plugin Functionality

### Revalidation Triggers

#### Post Events
- **Hook**: `save_post`
- **Conditions**:
  - Not an autosave or revision
  - Post status is "publish"
  - Post type is "post" (extensible to custom post types in future)
- **Paths revalidated**:
  - Post permalink (relative path only)
  - Category paths associated with the post

#### Category Events
- **Hooks**: `created_category`, `edited_category`, `delete_category`
- **Paths revalidated**:
  - Category archive page
  - All posts in that category

### Path Generation Rules
1. Convert full URLs to relative paths
2. Remove domain from URLs
3. Ensure paths start with `/` and end with `/`
4. Include category slugs in paths

### Revalidation Process
1. Retrieve endpoint and token from WordPress options
2. Build URL with query parameters: `?token={token}&path={path}`
3. Send GET request using `\wp_remote_get()`
4. Log success or error messages
5. Handle multiple paths in single operation

## Development Guidelines

### Adding New Features
1. Follow PSR-4 structure for new classes
2. Use proper namespacing: `RevalidatePosts\ClassName`
3. Add PHPDoc documentation
4. Use WordPress coding standards
5. Run quality checks before committing
6. Update CHANGELOG.md with changes

### Extending Post Types
Future versions may support custom post types. To add:
1. Modify `Revalidate.php` `on_post_saved()` method
2. Add post type to allowed list
3. Add admin setting for post type selection
4. Test thoroughly with custom post types

### Version Management
- Use semantic versioning (semver.org)
- Update version in main plugin file header
- Update version in `composer.json`
- Document changes in `CHANGELOG.md`
- Tag releases in Git

## Installation & Usage

### Installation
1. Upload plugin folder to `/wp-content/plugins/`
2. Activate plugin through WordPress admin
3. Configure settings under Settings > Post Revalidate

### Configuration
1. Navigate to Settings > Post Revalidate
2. Enter **Revalidate Endpoint** (e.g., `https://example.com/api/revalidate`)
3. Enter **Revalidate Token** (authentication token for endpoint)
4. Save settings

### Testing
1. Create or update a post
2. Check if revalidation request is sent
3. Verify endpoint receives correct paths
4. Check WordPress debug log for any errors

## Maintenance

### Regular Maintenance Tasks
- Update dependencies: `composer update`
- Check for WordPress compatibility with new versions
- Review and update deprecated WordPress functions
- Monitor plugin performance
- Review security best practices

### Troubleshooting
- Enable WordPress debug mode: `define("WP_DEBUG", true);`
- Check error logs in `wp-content/debug.log`
- Verify endpoint and token configuration
- Test endpoint independently with curl/Postman
- Check PHP error logs for syntax issues

## Automation Scripts

### Version Update Script

The project includes an automated version updater script: `scripts/update-version.sh`

**Purpose**: Automatically updates version numbers across all plugin files in one command.

**Usage**:
```bash
./scripts/update-version.sh <new-version> [--no-confirm]

# Examples
./scripts/update-version.sh 1.2.0              # Interactive mode (asks for confirmation)
./scripts/update-version.sh 1.2.0 --no-confirm # CI/CD mode (no prompts)
./scripts/update-version.sh --help             # Show help
```

**Files Updated**:
1. **Main plugin file** (`silver-assist-post-revalidate.php`):
   - Plugin header: `Version: X.X.X`
   - Constant: `SILVER_ASSIST_REVALIDATE_VERSION`
   - PHPDoc `@version` tag

2. **PHP files** (`Includes/*.php`):
   - All `@version` tags in PHPDoc blocks

3. **Script files** (`scripts/*.sh`):
   - All `@version` tags in script headers

4. **Documentation** (`README.md`):
   - Version references (if present)

**Important Notes**:
- Uses `perl` for reliable cross-platform compatibility (handles macOS sed quirks)
- Only updates `@version` tags, NOT `@since` tags
- `@since` tags should be set manually when files are first created
- Creates `.bak` backup files during processing
- Supports deferred modifications (for self-modification)
- Validates semantic versioning format (e.g., `1.2.0`)

**Version Update Workflow**:
```bash
# 1. Update version numbers
./scripts/update-version.sh 1.2.0

# 2. Review changes
git diff

# 3. Update CHANGELOG.md manually (REQUIRED)
# Add new version section with changes

# 4. Run quality checks
vendor/bin/phpcs
vendor/bin/phpstan analyse Includes/ --no-progress
vendor/bin/phpunit

# 5. Generate production build
bash scripts/build-release.sh

# 6. Commit and tag
git add .
git commit -m "chore: Update version to 1.2.0"
git tag -a v1.2.0 -m "Release v1.2.0"

# 7. Push to GitHub
git push origin master --tags
```

**Never Forget**:
- Always update `CHANGELOG.md` manually before committing
- Test the plugin after version update
- Run all validation tools
- Create meaningful release notes for GitHub

### Build Release Script

Location: `scripts/build-release.sh`

**Purpose**: Creates production-ready ZIP package for WordPress distribution.

**Features**:
- Installs production dependencies only (`composer install --no-dev`)
- Copies all necessary plugin files
- Cleans up development files (tests, docs, etc.)
- Optimizes vendor packages (Settings Hub, GitHub Updater)
- Validates package completeness
- Generates `readme.txt` for WordPress.org
- Creates ZIP archive in `build/` directory

**Validation Checks**:
- Main plugin file exists
- Version matches expected version
- Composer autoloader included
- Required packages present (GitHub Updater, Settings Hub)
- Source code directory (Includes/) included

**Important**: Script includes Settings Hub integration from Packagist.

## Support & Contribution

### Reporting Issues
- Provide WordPress version
- Provide PHP version
- Describe expected vs actual behavior
- Include relevant error messages
- Share debug log excerpts if possible

### Contributing
1. Follow established code standards
2. Write clear commit messages
3. Test thoroughly before submitting
4. Update documentation as needed
5. Run all quality checks

## Testing Guidelines

### Test-Driven Development (TDD)

**CRITICAL RULE**: ALL new functionality MUST follow strict TDD methodology (RED → GREEN → REFACTOR).

### WordPress Testing Standards

#### Test Structure
- **Base Class**: All tests MUST extend `WP_UnitTestCase` for WordPress integration
- **Test Location**: 
  - Unit tests: `tests/Unit/`
  - Integration tests: `tests/Integration/`
- **Naming**: Test files must match class names with `_Test.php` suffix

#### Test Organization
```php
/**
 * @group security       // For security-related tests
 * @group integration    // For integration tests
 * @group http          // For HTTP/API tests
 */
class MyFeature_Test extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Setup test fixtures
    }
    
    public function tearDown(): void {
        // Always clean up: delete options, transients, posts
        parent::tearDown();
    }
}
```

#### WordPress Test Utilities

**Use WordPress Factories**:
```php
// Create test posts
$post_id = static::factory()->post->create([
    'post_title' => 'Test Post',
    'post_status' => 'publish',
]);

// Create test users
$user_id = static::factory()->user->create(['role' => 'administrator']);

// Create test terms
$term_id = static::factory()->term->create(['taxonomy' => 'category']);
```

**Mock HTTP Requests**:
```php
// Use pre_http_request filter for fast tests
add_filter('pre_http_request', function($preempt, $args, $url) {
    return [
        'response' => ['code' => 200],
        'body' => json_encode(['success' => true]),
    ];
}, 10, 3);
```

**Test WordPress Hooks**:
```php
// Verify hook is registered
$this->assertTrue(has_action('save_post'));
$this->assertEquals(10, has_action('save_post', [$instance, 'method']));
```

#### Test Requirements

1. **Always Clean Up**: Delete created data in `tearDown()`
   ```php
   delete_option('my_option');
   delete_transient('my_transient');
   wp_delete_post($post_id, true);
   ```

2. **Document Version-Specific Behavior**:
   ```php
   // WordPress 6.0+ changed the autosave behavior
   if (version_compare(get_bloginfo('version'), '6.0', '>=')) {
       // New behavior
   }
   ```

3. **Use Assertions Appropriately**:
   - `assertInstanceOf()` for object types
   - `assertSame()` for strict equality
   - `assertTrue()/assertFalse()` for booleans
   - `assertCount()` for array sizes

4. **Test Edge Cases**:
   - Empty values
   - Null values
   - Invalid input
   - Permission checks
   - Security (XSS, SQL injection attempts)

#### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/Configuration_Test.php

# Run with testdox (readable output)
vendor/bin/phpunit --testdox

# Run specific group
vendor/bin/phpunit --group security

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

#### Test Coverage Goals

- **Unit Tests**: 100% coverage of public methods
- **Integration Tests**: All WordPress hooks and filters
- **Security Tests**: All capability checks and nonce validation
- **Current Status**: See `tests/TESTING_TODO.md` (local file only)

### AJAX Testing

For AJAX endpoints, extend `WP_Ajax_UnitTestCase`:
```php
class MyAjax_Test extends WP_Ajax_UnitTestCase {
    public function test_ajax_endpoint() {
        $_POST['action'] = 'my_action';
        $_POST['_wpnonce'] = wp_create_nonce('my_nonce');
        
        try {
            $this->_handleAjax('my_action');
        } catch (WPAjaxDieContinueException $e) {
            // Expected for successful AJAX
        }
        
        $response = json_decode($this->_last_response);
        $this->assertTrue($response->success);
    }
}
```

## License

GPL v2 or later

---

**Last Updated**: October 12, 2025  
**Version**: 1.4.0
```
