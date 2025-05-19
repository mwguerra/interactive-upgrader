# Interactive Upgrader for Laravel

A CLI tool that handles both Composer and npm dependencies for Laravel projects.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mwguerra/interactive-upgrader.svg?style=flat-square)](https://packagist.org/packages/mwguerra/interactive-upgrader)
[![Total Downloads](https://img.shields.io/packagist/dt/mwguerra/interactive-upgrader.svg?style=flat-square)](https://packagist.org/packages/mwguerra/interactive-upgrader)

## Installation

You can install the package via composer:

```bash
composer require mwguerra/interactive-upgrader
```

## Usage

Once installed, you can run the command:

```bash
php artisan upgrade:interactive
```

This will:
1. Check for outdated Composer and npm packages
2. Display them in a table with available upgrade options
3. Allow you to interactively select which packages to upgrade
4. Handle the upgrade process, including dependency analysis

### Options

```
--latest           Only consider "latest" (major) upgrades
--dev              Only consider "dev" (unstable) upgrades
--ignore=          Skip specific packages (comma-separated type:package pairs)
--ignore-major     Don't show any major bumps in the table
--ignore-dev       Don't show the "Dev" column or dev‐upgrades; drop packages with no actionable updates
--show             Only show the table without asking for any input and exit
```

### Examples

```bash
# See everything updatable
php artisan upgrade:interactive

# Only show major jumps
php artisan upgrade:interactive --latest

# Skip Predis and Tailwind from the table
php artisan upgrade:interactive --ignore=composer:predis/predis,npm:tailwindcss

# Hide all major bumps (even if --latest is given)
php artisan upgrade:interactive --ignore-major

# Don't show any dev‐builds, and drop packages already fully up‐to-date
php artisan upgrade:interactive --ignore-dev

# Only show the table without asking for any input
php artisan upgrade:interactive --show
```

## Features

- Handles both Composer and npm dependencies in a single command
- Interactive table-driven interface
- Analyzes dependencies to suggest related packages that might need to be updated
- Creates backups before making changes
- Supports various upgrade strategies (minor, major, dev)
- Color-coded version display (red for major bumps, green for minor)
- Warns about major version upgrades and allows falling back to safer options
- Provides option to only display outdated packages without making changes

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
