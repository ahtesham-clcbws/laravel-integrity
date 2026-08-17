# Baseline Management

When introducing Laravel Integrity to an existing, large codebase, you might be overwhelmed by hundreds of hygiene issues or orphaned views.

Instead of fixing everything at once, you can generate a *baseline*. This will snapshot all existing issues and ignore them in future checks, allowing you to enforce strict rules only for *new* code.

## Generating a Baseline

To generate a baseline, run:

```bash
php artisan integrity:baseline
```

This will scan your entire project, find all issues, and save them to a `nintegrity-baseline.json` file in your project root.

## Committing The Baseline

You should commit the `.integrity-baseline.json` file to your git repository. This ensures that CI/CD pipelines and other developers on your team share the same baseline.

## Updating The Baseline

As you gradually fix legacy issues, the baseline will automatically shrink if you rerun the baseline command.

It is a good practice to occasionally rerun `artisan integrity:baseline` to keep the file up to date as technical debt is resolved.