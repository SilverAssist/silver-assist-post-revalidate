#!/bin/bash
###############################################################################
# Quality Checks Runner for Silver Assist Post Revalidate
#
# Centralized script for running quality checks consistently across CI/CD
# workflows (release.yml, dependency-updates.yml).
#
# @package  silver-assist-post-revalidate
# @author   Silver Assist
# @version  1.4.0
# @since    1.4.0
###############################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

###############################################################################
# Configuration
###############################################################################

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Default values
PHP_VERSION="${PHP_VERSION:-8.3}"
WORDPRESS_VERSION="${WORDPRESS_VERSION:-latest}"
DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_HOST="${DB_HOST:-localhost}"
SKIP_WP_SETUP="${SKIP_WP_SETUP:-false}"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

###############################################################################
# Helper Functions
###############################################################################

print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

###############################################################################
# Setup Functions
###############################################################################

setup_wordpress_tests() {
    print_header "🐘 Setting up WordPress Test Suite"
    
    if [ "$SKIP_WP_SETUP" = "true" ]; then
        print_warning "Skipping WordPress Test Suite setup (SKIP_WP_SETUP=true)"
        return 0
    fi
    
    # Install system dependencies
    print_info "Installing system dependencies..."
    if command -v apt-get &> /dev/null; then
        sudo apt-get update -qq
        sudo apt-get install -y -qq subversion mysql-client > /dev/null 2>&1
    fi
    print_success "System dependencies installed"
    
    # Setup MySQL
    print_info "Setting up MySQL database: $DB_NAME"
    if command -v systemctl &> /dev/null; then
        sudo systemctl start mysql.service || true
    fi
    
    mysql -e "DROP DATABASE IF EXISTS $DB_NAME;" -u"$DB_USER" -p"$DB_PASS" 2>/dev/null || true
    mysql -e "CREATE DATABASE $DB_NAME;" -u"$DB_USER" -p"$DB_PASS"
    print_success "Database $DB_NAME created"
    
    # Install WordPress Test Suite
    print_info "Installing WordPress Test Suite (version: $WORDPRESS_VERSION)..."
    bash "$SCRIPT_DIR/install-wp-tests.sh" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WORDPRESS_VERSION" true
    print_success "WordPress Test Suite installed"
}

###############################################################################
# Quality Check Functions
###############################################################################

run_composer_validate() {
    print_header "📦 Validating composer.json and composer.lock"
    
    cd "$PROJECT_ROOT"
    composer validate --strict
    
    print_success "Composer files validated"
}

run_phpcs() {
    print_header "🎨 Running PHPCS (Code Style Check)"
    
    cd "$PROJECT_ROOT"
    vendor/bin/phpcs --warning-severity=0
    
    print_success "PHPCS passed - No errors found"
}

run_phpstan() {
    print_header "🔍 Running PHPStan (Static Analysis)"
    
    cd "$PROJECT_ROOT"
    php -d memory_limit=1G vendor/bin/phpstan analyse Includes/ --no-progress
    
    print_success "PHPStan Level 8 passed - No errors found"
}

run_phpunit() {
    print_header "🧪 Running PHPUnit Tests"
    
    cd "$PROJECT_ROOT"
    
    # Check if WordPress Test Suite is needed
    if [ "$SKIP_WP_SETUP" != "true" ]; then
        # Set WordPress tests directory for PHPUnit
        export WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
        print_info "Using WordPress Test Suite: $WP_TESTS_DIR"
    fi
    
    vendor/bin/phpunit --testdox
    
    print_success "All tests passed"
}

run_syntax_check() {
    print_header "🔍 Running PHP Syntax Check"
    
    cd "$PROJECT_ROOT"
    
    # Check main plugin file
    print_info "Checking main plugin file..."
    php -l silver-assist-post-revalidate.php
    
    # Check all PHP files in Includes directory
    if [ -d "Includes" ]; then
        print_info "Checking source files in Includes/..."
        find Includes -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
    fi
    
    print_success "All PHP files have valid syntax"
}

###############################################################################
# Main Execution
###############################################################################

show_usage() {
    cat << EOF
Usage: $(basename "$0") [OPTIONS] [CHECKS...]

Centralized quality checks runner for CI/CD workflows.

OPTIONS:
    -h, --help              Show this help message
    -v, --verbose           Verbose output
    --skip-wp-setup         Skip WordPress Test Suite setup (for quick checks)
    --php-version VERSION   PHP version (default: $PHP_VERSION)
    --wp-version VERSION    WordPress version (default: $WORDPRESS_VERSION)
    --db-name NAME          Database name (default: $DB_NAME)
    --db-user USER          Database user (default: $DB_USER)
    --db-pass PASS          Database password (default: $DB_PASS)
    --db-host HOST          Database host (default: $DB_HOST)

CHECKS (run all if none specified):
    composer-validate       Validate composer.json and composer.lock
    phpcs                   Run PHP CodeSniffer
    phpstan                 Run PHPStan static analysis
    phpunit                 Run PHPUnit tests
    syntax                  Run PHP syntax check on all files
    setup-wp                Setup WordPress Test Suite only
    all                     Run all quality checks (default)

EXAMPLES:
    # Run all checks with WordPress setup
    $(basename "$0")
    
    # Run only PHPCS and PHPStan (no WordPress needed)
    $(basename "$0") --skip-wp-setup phpcs phpstan
    
    # Run full test suite with custom database
    $(basename "$0") --db-name custom_test --db-pass secret phpunit
    
    # Setup WordPress Test Suite only
    $(basename "$0") setup-wp

ENVIRONMENT VARIABLES:
    PHP_VERSION             PHP version to use
    WORDPRESS_VERSION       WordPress version to install
    DB_NAME                 Database name
    DB_USER                 Database user
    DB_PASS                 Database password
    DB_HOST                 Database host
    SKIP_WP_SETUP           Skip WordPress setup (true/false)
    WP_TESTS_DIR            WordPress tests directory path

EOF
}

main() {
    local checks=()
    local setup_wp_only=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_usage
                exit 0
                ;;
            -v|--verbose)
                set -x
                shift
                ;;
            --skip-wp-setup)
                SKIP_WP_SETUP="true"
                shift
                ;;
            --php-version)
                PHP_VERSION="$2"
                shift 2
                ;;
            --wp-version)
                WORDPRESS_VERSION="$2"
                shift 2
                ;;
            --db-name)
                DB_NAME="$2"
                shift 2
                ;;
            --db-user)
                DB_USER="$2"
                shift 2
                ;;
            --db-pass)
                DB_PASS="$2"
                shift 2
                ;;
            --db-host)
                DB_HOST="$2"
                shift 2
                ;;
            composer-validate|phpcs|phpstan|phpunit|syntax|setup-wp|all)
                checks+=("$1")
                shift
                ;;
            *)
                print_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Default to 'all' if no checks specified
    if [ ${#checks[@]} -eq 0 ]; then
        checks=("all")
    fi
    
    # Show configuration
    print_header "⚙️  Configuration"
    echo "PHP Version:       $PHP_VERSION"
    echo "WordPress Version: $WORDPRESS_VERSION"
    echo "Database Name:     $DB_NAME"
    echo "Database User:     $DB_USER"
    echo "Database Host:     $DB_HOST"
    echo "Skip WP Setup:     $SKIP_WP_SETUP"
    echo "Checks:            ${checks[*]}"
    
    # Setup WordPress Test Suite if needed
    local needs_wp_setup=false
    for check in "${checks[@]}"; do
        if [ "$check" = "phpunit" ] || [ "$check" = "all" ] || [ "$check" = "setup-wp" ]; then
            needs_wp_setup=true
            break
        fi
    done
    
    if [ "$needs_wp_setup" = "true" ] && [ "$SKIP_WP_SETUP" != "true" ]; then
        setup_wordpress_tests
    fi
    
    # Handle setup-wp-only mode
    if [ "${checks[0]}" = "setup-wp" ]; then
        print_success "WordPress Test Suite setup completed"
        exit 0
    fi
    
    # Run quality checks
    local failed_checks=()
    
    for check in "${checks[@]}"; do
        case $check in
            all)
                run_composer_validate || failed_checks+=("composer-validate")
                run_phpcs || failed_checks+=("phpcs")
                run_phpstan || failed_checks+=("phpstan")
                run_syntax_check || failed_checks+=("syntax")
                run_phpunit || failed_checks+=("phpunit")
                ;;
            composer-validate)
                run_composer_validate || failed_checks+=("composer-validate")
                ;;
            phpcs)
                run_phpcs || failed_checks+=("phpcs")
                ;;
            phpstan)
                run_phpstan || failed_checks+=("phpstan")
                ;;
            syntax)
                run_syntax_check || failed_checks+=("syntax")
                ;;
            phpunit)
                run_phpunit || failed_checks+=("phpunit")
                ;;
        esac
    done
    
    # Summary
    print_header "📊 Quality Checks Summary"
    
    if [ ${#failed_checks[@]} -eq 0 ]; then
        print_success "All quality checks passed! 🎉"
        exit 0
    else
        print_error "Failed checks: ${failed_checks[*]}"
        exit 1
    fi
}

# Run main function
main "$@"
