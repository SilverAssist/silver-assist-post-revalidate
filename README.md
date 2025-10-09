# Silver Assist Post Revalidate

A WordPress plugin that automatically revalidates posts and categories when content changes, sending requests to a configured endpoint for cache invalidation.

## Description

Silver Assist Post Revalidate is a lightweight WordPress plugin designed to keep your headless WordPress site's cache fresh. When content is created, updated, or deleted, the plugin automatically triggers revalidation requests to your front-end application (typically Next.js with ISR - Incremental Static Regeneration).

### Features

- ✅ **Automatic Revalidation**: Triggers on post save, update, and delete
- ✅ **Category Support**: Revalidates when categories are created, updated, or deleted
- ✅ **Path-Based**: Sends only relative paths (no domain) to your endpoint
- ✅ **Secure**: Token-based authentication for endpoint requests
- ✅ **Simple Configuration**: Easy-to-use admin settings page
- ✅ **Debug Logs**: Built-in debug log viewer with accordion UI (see requests/responses)
- ✅ **Centralized Settings**: Integrates with Silver Assist Settings Hub
- ✅ **Automatic Updates**: GitHub-based auto-updates for seamless upgrades
- ✅ **Modern PHP**: Built with PHP 8.3+ features and PSR-4 autoloading

## Requirements

- **WordPress**: 6.5 or higher
- **PHP**: 8.3 or higher
- **Composer**: For development only

## Installation

### Via WordPress Admin (Recommended)

1. Download the latest release from the [releases page](https://github.com/SilverAssist/silver-assist-post-revalidate/releases)
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the downloaded ZIP file
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin files
2. Upload the `silver-assist-post-revalidate` folder to `/wp-content/plugins/`
3. Activate the plugin through the "Plugins" menu in WordPress

### Via Composer (For Development)

```bash
composer require silver-assist/post-revalidate
```

## Configuration

### 1. Access Settings

Navigate to **Settings → Post Revalidate** in your WordPress admin panel.

### 2. Configure Endpoint

Enter your revalidation endpoint URL:
```
https://your-frontend-site.com/api/revalidate
```

### 3. Set Authentication Token

Enter the authentication token that your endpoint expects:
```
your-secret-token-here
```

### 4. Save Settings

Click "Save Settings" to store your configuration.

## Usage

Once configured, the plugin works automatically. No additional setup required!

### What Gets Revalidated?

#### When a Post is Saved/Updated
- The post's permalink
- All categories associated with the post

#### When a Category is Updated
- The category archive page
- All posts within that category

### Request Format

The plugin sends GET requests to your endpoint with the following query parameters:

```
GET https://your-endpoint.com/api/revalidate?token=YOUR_TOKEN&path=/blog/my-post/
```

**Parameters:**
- `token`: Authentication token (from settings)
- `path`: Relative path to revalidate (e.g., `/blog/my-post/`)

## Debug Logs

**New in v1.2.0**: Built-in debug log viewer for complete traceability!

### Overview

The Debug Logs section appears at the bottom of the settings page and displays all revalidation requests with full request/response details.

### Features

- 📊 **Accordion UI**: Click any log entry to expand and view details
- 🎨 **Color-Coded Status**: Green for success (200-299), red for errors
- 📝 **JSON Formatted**: Request and response data formatted for easy reading
- ⏱️ **Timestamps**: Track when each revalidation occurred
- 🗑️ **Clear Logs**: Button to remove all logs (with confirmation)
- 🔄 **Auto-Rotation**: Maximum 100 entries kept (FIFO)

### What's Logged

Each log entry contains:

**Request Details:**
```json
{
  "url": "https://example.com/api/revalidate?token=xxx&path=/blog/post/",
  "method": "GET",
  "headers": {
    "User-Agent": "Silver-Assist-Revalidate/1.2.0"
  },
  "timeout": 30
}
```

**Response Details:**
```json
{
  "code": 200,
  "message": "OK",
  "body": "{\"revalidated\":true,\"path\":\"/blog/post/\"}",
  "headers": {
    "content-type": "application/json",
    "cache-control": "no-cache"
  }
}
```

### Use Cases

1. **Debugging**: See exactly what was sent and received
2. **Duplicate Detection**: Identify if requests are being sent multiple times
3. **Server Issues**: Check response codes and error messages
4. **Performance Monitoring**: Track response times and success rates
5. **Path Verification**: Ensure correct paths are being revalidated

### Accessing Debug Logs

1. Go to **Settings → Post Revalidate** (or **Silver Assist → Post Revalidate**)
2. Scroll to the bottom of the page
3. See the "Revalidation Debug Logs" section
4. Click any log header to expand and view details

### Visual Example

```
┌─────────────────────────────────────────────────────────┐
│ Revalidation Debug Logs          [Clear All Logs]      │
├─────────────────────────────────────────────────────────┤
│ Showing 3 requests (most recent first). Max 100 kept.  │
│                                                         │
│ ✓ /blog/my-post/     SUCCESS (200)  2025-10-09 14:32  │ ← Click to expand
│ ✗ /category/tech/    ERROR (500)    2025-10-09 14:31  │
│ ✓ /blog/another/     SUCCESS (200)  2025-10-09 14:30  │
└─────────────────────────────────────────────────────────┘
```

When expanded, you'll see formatted JSON for both request and response data.

## Example Next.js Handler

Here's an example API route for Next.js to handle revalidation requests:

```javascript
// pages/api/revalidate.js
export default async function handler(req, res) {
  const { token, path } = req.query;

  // Validate token
  if (token !== process.env.REVALIDATE_TOKEN) {
    return res.status(401).json({ message: 'Invalid token' });
  }

  // Validate path
  if (!path) {
    return res.status(400).json({ message: 'Path is required' });
  }

  try {
    // Revalidate the path
    await res.revalidate(path);
    
    return res.json({ 
      revalidated: true, 
      path,
      timestamp: new Date().toISOString() 
    });
  } catch (err) {
    return res.status(500).json({ 
      message: 'Error revalidating', 
      error: err.message 
    });
  }
}
```

## Development

### Prerequisites

- PHP 8.3+
- Composer
- WordPress development environment

### Setup

1. Clone the repository:
```bash
git clone https://github.com/SilverAssist/silver-assist-post-revalidate.git
cd silver-assist-post-revalidate
```

2. Install dependencies:
```bash
composer install
```

### Quality Checks

Run these commands before committing:

```bash
# Auto-fix coding standards
composer run phpcbf

# Check coding standards
composer run phpcs

# Run static analysis
composer run phpstan

# Or run all checks
composer run test
```

### File Structure

```
silver-assist-post-revalidate/
├── .github/
│   ├── copilot-instructions.md  # Project documentation
│   └── workflows/
│       └── release.yml          # GitHub Actions CI/CD
├── assets/
│   ├── css/
│   │   └── admin-debug-logs.css # Debug logs styling with design tokens
│   └── js/
│       └── admin-debug-logs.js  # Accordion & AJAX functionality
├── Includes/
│   ├── AdminSettings.php        # Admin settings page & debug UI
│   ├── Plugin.php               # Main plugin initialization
│   ├── Revalidate.php           # Core revalidation logic & logging
│   └── Updater.php              # GitHub auto-update integration
├── scripts/
│   ├── build-release.sh         # Production build generator
│   └── update-version.sh        # Automated version management
├── vendor/                      # Composer dependencies (production)
│   ├── silverassist/
│   │   ├── wp-settings-hub/     # Centralized settings menu
│   │   └── wp-github-updater/   # Auto-update system
│   └── ...
├── silver-assist-post-revalidate.php  # Main plugin file
├── composer.json                # Dependencies & autoloading
├── phpcs.xml                    # Coding standards config
├── phpstan.neon                 # Static analysis config
├── phpunit.xml                  # Unit testing config
├── README.md                    # This file
├── CHANGELOG.md                 # Version history
├── LICENSE                      # Polyform Noncommercial License
└── .gitignore                   # Git ignore rules
```

## Testing

The plugin uses PHPUnit with the WordPress test suite for comprehensive integration testing.

### Prerequisites

- MySQL/MariaDB server
- SVN (Subversion) client
- WordPress test suite installed

### Installing WordPress Test Suite

1. Install the WordPress test suite using the provided script:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Parameters**:
- `wordpress_test` - Test database name (will be created)
- `root` - MySQL username
- `''` - MySQL password (empty in this example)
- `localhost` - MySQL host
- `latest` - WordPress version (or specific version like `6.4.2`)

The script will:
- Download WordPress core to `/tmp/wordpress/`
- Install WordPress test suite to `/tmp/wordpress-tests-lib/`
- Create test database configuration

2. Set environment variable (optional, if not using `/tmp/wordpress-tests-lib`):

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
```

Or update `phpunit.xml`:

```xml
<env name="WP_TESTS_DIR" value="/path/to/wordpress-tests-lib"/>
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/Plugin_Test.php

# Run with code coverage (requires Xdebug or PCOV)
vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure

```
tests/
├── bootstrap.php             # WordPress test suite bootstrap
└── Unit/
    ├── Plugin_Test.php       # Plugin class tests
    ├── AdminSettings_Test.php # Settings & options tests
    └── Revalidate_Test.php   # Core revalidation logic tests
```

### Writing Tests

All tests extend `WP_UnitTestCase` for real WordPress integration:

```php
<?php
namespace RevalidatePosts\Tests\Unit;

use WP_UnitTestCase;
use RevalidatePosts\Plugin;

class Plugin_Test extends WP_UnitTestCase {
    
    public function test_something(): void {
        // Create real WordPress posts, users, etc.
        $post_id = $this->factory->post->create();
        
        // Test with real WordPress functions
        $this->assertTrue( get_post( $post_id ) !== null );
    }
}
```

### Test Coverage

Current tests cover:

- ✅ **Plugin Class**: Singleton pattern, settings link, initialization
- ✅ **AdminSettings Class**: Options storage, sanitization, settings registration, AJAX handlers, script/style enqueuing
- ✅ **Revalidate Class**: Post/category hooks, logging system, FIFO rotation, HTTP requests

### Continuous Integration

Tests run automatically on GitHub Actions for pull requests and releases.

## Debugging

Enable WordPress debug mode to see detailed logs:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `wp-content/debug.log` for revalidation activity.

## Troubleshooting

### Revalidation Not Working?

1. **Check Settings**: Verify endpoint URL and token are correct
2. **Test Endpoint**: Use curl or Postman to test your endpoint independently
3. **Check Logs**: Enable WP_DEBUG and check debug.log
4. **Verify Post Type**: Plugin only handles "post" type by default
5. **Check Post Status**: Only "publish" status triggers revalidation

### Common Issues

**Issue**: "Endpoint or token not configured" error
- **Solution**: Configure settings under Settings → Post Revalidate

**Issue**: Revalidation requests timing out
- **Solution**: Check your endpoint's response time and increase timeout if needed

**Issue**: Not all paths are revalidated
- **Solution**: Verify paths are being generated correctly in debug logs

## Roadmap

Future enhancements planned:

- [ ] Custom post type support
- [ ] Manual revalidation button
- [ ] Bulk revalidation tool
- [ ] Revalidation queue system
- [ ] More granular path control
- [ ] Multiple endpoint support

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress coding standards
4. Run quality checks (`composer run test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## License

This plugin is licensed under the **Polyform Noncommercial License 1.0.0**.

### Key Points

- ✅ **Free for noncommercial use**: You can use, modify, and distribute this plugin for any noncommercial purpose
- ✅ **Open source**: Source code is publicly available
- ❌ **No commercial use**: Commercial use requires a separate commercial license
- ✅ **Modifications allowed**: You can create and distribute modified versions (noncommercial only)
- ✅ **Attribution**: Please maintain attribution to Silver Assist

### Full License Text

```
Polyform Noncommercial License 1.0.0
Copyright (C) 2025 Silver Assist

The licensor grants you a copyright license for the licensed material to do 
everything you might do with the licensed material that would otherwise infringe 
the licensor's copyright in it, for any noncommercial purpose, for the duration 
of the license, and in all territories.

Commercial purposes means use of the licensed material for a purpose intended 
for or directed toward commercial advantage or monetary compensation.
```

**Full license**: See [LICENSE](LICENSE) file or visit https://polyformproject.org/licenses/noncommercial/1.0.0

### Commercial License

If you need to use this plugin for commercial purposes, please contact Silver Assist for licensing options.

## Credits

Developed by [Silver Assist](http://silverassist.com/)

## Support

- **Issues**: [GitHub Issues](https://github.com/SilverAssist/silver-assist-post-revalidate/issues)
- **Documentation**: [GitHub Wiki](https://github.com/SilverAssist/silver-assist-post-revalidate/wiki)
- **Website**: http://silverassist.com/

---

Made with ❤️ by Silver Assist
