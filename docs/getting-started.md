# Getting Started

Laravel Integrity is a high-fidelity static AST & dynamic Container Reflection suite designed to keep your codebase pristine. It prevents silent failures (like orphaned views or missing routes) from making it into production.

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 11.x, 12.x, or 13.x

## Installation

Install the package as a development dependency via Composer:

```bash
composer require clcbws/laravel-integrity --dev
```

Since the tool is strictly for analysis, you should generally install it only as a `dev` dependency.

## Configuration

Next, you should publish the default configuration file. This allows you to toggle specific checks and ignore certain files or directories:

```bash
php artisan vendor:publish --tag=integrity-config
```

This will create a `config/integrity.php` file in your application. See the [Configuration Guide](/configuration) for a detailed breakdown of the available options.

## Basic Usage

The primary entry point to run the suite is the `integrity:check` Artisan command.

### Standard Audit
Run all lightweight, static checks across the directories configured in `config/integrity.php`:

```bash
php artisan integrity:check
```

### Full Check Pipeline
Include intensive checks that require booting database connections (like migration checks) or full Blade file compilations:

```bash
php artisan integrity:check --full
```

### CI / CD Pipelines (Strict Mode)
To ensure the command fails with a non-zero exit code if any issues are identified, use the strict flag:

```bash
php artisan integrity:check --strict
```

### Pre-commit Hooks (Dirty Mode)
If you only want to scan files that have been modified or staged in Git, use the dirty flag. This is significantly faster and perfect for pre-commit hooks:

```bash
php artisan integrity:check --dirty
```

### Auto-Remediation
Some hygiene issues, such as missing strict types, inline root facades (e.g. `\DB::`), and unused imports can be automatically fixed by the tool:

```bash
php artisan integrity:fix
```

## Next Steps

- Review the [Full Checks Reference](/checks-reference) to understand everything the suite can catch.
- Learn about the [Architecture](/architecture) to understand how AST parsing and Container reflection are combined.
