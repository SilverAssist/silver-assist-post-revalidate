# Silver Assist Post Revalidate — Project Context

WordPress plugin that provides automatic cache revalidation for posts, categories, and tags when content changes, sending requests to a configured endpoint (typically Next.js).

| Field | Value |
|-------|-------|
| **Namespace** | `RevalidatePosts` |
| **Text Domain** | `silver-assist-revalidate-posts` |
| **Version** | 1.4.0 |
| **Default Branch** | `master` |

## Architecture

### File Structure

```
silver-assist-post-revalidate/
├── assets/
│   ├── css/admin-debug-logs.css    # Debug logs styling (CSS variables, responsive)
│   └── js/admin-debug-logs.js      # Accordion & AJAX functionality (IIFE, jQuery)
├── Includes/
│   ├── AdminSettings.php           # Settings page + debug logs UI + token security
│   ├── Plugin.php                  # Singleton initialization, autoloader, hooks
│   ├── Revalidate.php              # Core revalidation logic + request logging
│   └── Updater.php                 # GitHub auto-update integration
├── scripts/
│   ├── build-release.sh            # Production ZIP builder
│   ├── run-quality-checks.sh       # Unified quality checks
│   └── update-version.sh           # Version bumper (all files)
├── silver-assist-post-revalidate.php
├── composer.json / phpcs.xml / phpstan.neon
└── CHANGELOG.md
```

### Class Responsibilities

- **Plugin.php** — Singleton entry point; loads autoloader, initializes `Revalidate` and `AdminSettings`.
- **Revalidate.php** — Hooks into `save_post`, `created_category`, `edited_category`, `delete_category`, `transition_post_status`, `before_delete_post`. Converts URLs to relative paths, sends GET requests via `wp_remote_get()`, logs all requests/responses (max 100 entries, FIFO rotation). Logs stored in option `silver_assist_revalidate_logs`.
- **AdminSettings.php** — Settings Hub integration ("Silver Assist" menu). Manages `revalidate_endpoint` and `revalidate_token` options. Debug logs accordion UI with AJAX clear-logs handler. Token masking with show/hide toggle and XSS-safe sanitization (detects bullet-point masked values → preserves existing token).
- **Updater.php** — Integrates `silverassist/wp-github-updater` for automatic updates from GitHub releases.

### Dependencies (Composer)

- `silverassist/wp-github-updater` (^1.1) — Auto-updates from GitHub releases
- `silverassist/wp-settings-hub` (^1.0) — Centralized "Silver Assist" admin menu
- `composer/installers` (^2.0) — WordPress plugin directory handling

## Revalidation Flow

### Triggers

| Event | Hook(s) | Paths Revalidated |
|-------|---------|-------------------|
| Post save/update | `save_post` | Post permalink + category paths |
| Post publish/unpublish | `transition_post_status` | Post permalink |
| Post deletion | `before_delete_post` | Post permalink + category paths |
| Category CRUD | `created_category`, `edited_category`, `delete_category` | Category archive + posts in category |
| Tag CRUD | `created_post_tag`, `edited_post_tag`, `delete_post_tag` | Tag archive |

### Path Rules & Request Format

- Full URLs → relative paths (domain stripped), must start and end with `/`
- **Deduplication**: duplicate paths removed before sending
- Request: `GET {endpoint}?token={token}&path={relative_path}`

## Plugin-Specific Features

- **Tag lifecycle revalidation** (v1.2.0): create/edit/delete triggers
- **Status transitions** (v1.2.0): smart revalidation on publish/unpublish
- **Post deletion support** (v1.2.0): automatic revalidation when posts are deleted
- **Path deduplication** (v1.2.0): prevents duplicate revalidation requests
- **Debug logs viewer**: accordion UI showing request/response data with AJAX clear functionality
- **Token masking** (v1.2.3): password field with show/hide toggle, sanitization guards against XSS and masked-value overwrites
- **CSS design tokens**: centralized variables for colors, spacing, typography, borders
- **Settings Hub fallback**: standalone settings page when hub plugin is unavailable

## Quick Reference

| Action | Command |
|--------|---------|
| Quality checks (fast) | `bash scripts/run-quality-checks.sh --skip-wp-setup phpcs phpstan` |
| Quality checks (full) | `bash scripts/run-quality-checks.sh all` |
| Update version | `./scripts/update-version.sh <version>` |
| Build release ZIP | `bash scripts/build-release.sh` |
| Run tests | `bash scripts/run-quality-checks.sh phpunit` |
| Text domain | `silver-assist-revalidate-posts` |
