# Composer Lifecycle Hooks Integration

To automate integrity checks and formatting fixers during package lifecycle events, you can integrate the suite directly into your application's `composer.json` file.

---

## 1. Pre-Commit Verification (recommended)

To run lightweight checks as a pre-commit verification before staging commits:

```json
{
    "scripts": {
        "check-integrity": "@php artisan integrity:check --dirty --strict"
    }
}
```

Then run `composer check-integrity` to scan only modified/staged files.

---

## 2. Post-Autoload-Dump Pre-Flight Checks

To automatically verify routing, Blade compilation, database models, and service provider dependencies after autoload dumps:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi",
            "@php artisan integrity:check --full"
        ]
    }
}
```

> **Warning**: Ensure your database connection is active when executing with `--full` during composer runs, otherwise database reflection mapping checks will generate fault logs.
