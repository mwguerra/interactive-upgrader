#!/usr/bin/env bash

set -e

# Check if version number is provided
if [[ -z "$1" ]]; then
    echo "❌ Error: Version number not provided"
    echo "Usage: $0 <version-number>"
    exit 1
fi

VERSION="$1"

# Validate version format (should be in semantic versioning format: X.Y.Z)
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "❌ Error: Invalid version format. Version should be in format X.Y.Z (e.g., 1.0.0)"
    exit 1
fi

# Check if tag already exists
if git tag | grep -q "^v$VERSION$"; then
    echo "❌ Error: Tag v$VERSION already exists"
    exit 1
fi

# Get the latest tag
LATEST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
LATEST_VERSION=${LATEST_TAG#v}

# Compare versions
IFS='.' read -r LATEST_MAJOR LATEST_MINOR LATEST_PATCH <<< "$LATEST_VERSION"
IFS='.' read -r NEW_MAJOR NEW_MINOR NEW_PATCH <<< "$VERSION"

if [[ $NEW_MAJOR -lt $LATEST_MAJOR ]] || 
   [[ $NEW_MAJOR -eq $LATEST_MAJOR && $NEW_MINOR -lt $LATEST_MINOR ]] || 
   [[ $NEW_MAJOR -eq $LATEST_MAJOR && $NEW_MINOR -eq $LATEST_MINOR && $NEW_PATCH -le $LATEST_PATCH ]]; then
    echo "❌ Error: Version $VERSION is not higher than the latest version $LATEST_VERSION"
    exit 1
fi

# Get current version from composer.json
COMPOSER_VERSION=$(grep -o '"version": *"[^"]*"' composer.json 2>/dev/null | cut -d'"' -f4 || echo "")

# If version is not in composer.json, add it
if [[ -z "$COMPOSER_VERSION" ]]; then
    echo "ℹ️ No version found in composer.json. Adding version $VERSION..."
    COMPOSER_VERSION="$VERSION"
else
    # Ask if the current version is correct
    echo "ℹ️ Current version in composer.json: $COMPOSER_VERSION"
    echo "ℹ️ Version to tag: $VERSION"

    read -p "Is this correct? (y/n): " CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
        read -p "Enter new version number: " NEW_VERSION

        # Validate new version format
        if ! [[ $NEW_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "❌ Error: Invalid version format. Version should be in format X.Y.Z (e.g., 1.0.0)"
            exit 1
        fi

        # Check if tag already exists for new version
        if git tag | grep -q "^v$VERSION$"; then
            echo "❌ Error: Tag v$VERSION already exists"
            exit 1
        fi

        # Compare with latest version again
        IFS='.' read -r NEW_MAJOR NEW_MINOR NEW_PATCH <<< "$VERSION"
        if [[ $NEW_MAJOR -lt $LATEST_MAJOR ]] || 
           [[ $NEW_MAJOR -eq $LATEST_MAJOR && $NEW_MINOR -lt $LATEST_MINOR ]] || 
           [[ $NEW_MAJOR -eq $LATEST_MAJOR && $NEW_MINOR -eq $LATEST_MINOR && $NEW_PATCH -le $LATEST_PATCH ]]; then
            echo "❌ Error: Version $VERSION is not higher than the latest version $LATEST_VERSION"
            exit 1
        fi
    fi
fi

# Create and push tag
echo "🏷️ Creating tag v$VERSION..."
git tag "v$VERSION"

echo "📤 Pushing tag to origin..."
git push origin "v$VERSION"

echo "✅ Successfully created and pushed tag v$VERSION"
