#!/usr/bin/env bash

set -e

# Check if package name is provided
if [[ -z "$1" ]]; then
    echo "❌ Error: Package name not provided"
    echo "Usage: $0 <package-name>"
    exit 1
fi

# Configuration
PACKAGE_DIR="$(pwd)"
PACKAGE_NAME="$1"
GITHUB_REPO_URL="https://github.com/$PACKAGE_NAME"
PACKAGIST_URL="https://packagist.org/packages/submit"

# Check for composer.json
if [[ ! -f "$PACKAGE_DIR/composer.json" ]]; then
    echo "❌ composer.json not found in $PACKAGE_DIR"
    exit 1
fi

# Step 1: Run basic Composer validation
echo "🔍 Validating composer.json..."
composer validate --strict

# Step 2: Run tests if they exist
if [[ -f "$PACKAGE_DIR/phpunit.xml" || -d "$PACKAGE_DIR/tests" ]]; then
    echo "🧪 Running tests..."
    if command -v pest &> /dev/null; then
        pest
    else
        vendor/bin/phpunit
    fi
fi

# Step 3: Git check (requires commit and push before publishing)
echo "🔄 Checking Git status..."
git diff --quiet || { echo "❌ You have uncommitted changes. Commit them before publishing."; exit 1; }
git rev-parse --abbrev-ref HEAD | grep -q 'main\|master' || echo "⚠️ You're not on main/master branch."

# Step 4: Push to GitHub
echo "📤 Pushing to GitHub..."
git push origin

# Step 5: Open Packagist submit page
echo "🌐 Opening Packagist submission page..."
xdg-open "$PACKAGIST_URL" 2>/dev/null || open "$PACKAGIST_URL" || echo "Please visit: $PACKAGIST_URL"

# Step 6: Output help info
echo ""
echo "✅ All done. Now, paste your repo URL ($GITHUB_REPO_URL) into the Packagist submission form."
echo "Once it's published, run 'composer require your-vendor/your-package' to test it."
