# Configuration

Once you have published the configuration file via `php artisan vendor:publish --tag=integrity-config`, a new file will be created at `config/integrity.php`.

This file allows you to customize the behavior of the entire Laravel Integrity suite.

## The Configuration Array

The configuration file returns a nested array defining paths, ignored files, and active checks.


```php
return [
    /*
    |-------------------------------------------------------------------------
    | Target Paths
    |------------------------------------------------------------------------
    |
    | Define the directories that the scanner should analyze. The engine will
    | recursively scan all PHP and Blade files within these directories.
    |
    */
    'paths' => [
        app_path(),
        resource_path('views'),
        base_path('routes'),
    ],

    /*
    |-------------------------------------------------------------------------
    | Ignored Files and Patterns
    |-------------------------------------------------------------------------
    |
    | Any paths or regex patterns defined here will be skipped by the scanner.
    | This is useful for ignoring third-party code, legacy directories, or
    | specific files that cause false positives.
    |
    */
    'ignore' => [
        '*/vendor/*',
        '9/node_modules/*',
        '*/storage/*',
        app_path('Legacy/*'), // Ignore a specific legacy folder
    ],

    /*
    |------------------------------------------------------------------------
    | Active Checks
    |-------------------------------------------------------------------------
    |
    | This array registers all the check classes that will be executed.
    | If you want to disable a specific check, simply comment it out or
    | remove it from this array.
    |
    | You can also register your own custom checks here.
    |
    */
    'checks' => [
        \Clcbws\LaravelIntegrity\Checks\Blade\DanglingViewCheck::class,
        \Clcbws\LaravelIntegrity\Checks\Blade\MissingRouteCheck::class,
        \Clcbws\LaravelIntegrity\Checks\Hygiene\UnusedImportCheck::class,
        // ...
    ],
];
```

## Disabling Specific Checks

If a particular check doesn't fit your project's workflow, you can easily disable it by commenting out or removing its class name from the `checks` array.

For example, if you intentionally use closure routes and want to disable the `ClosureRouteCheck`:

```php
    'checks' => [
        // \Clcbws\LaravelIntegrity\Checks\Routing\ClosureRouteCheck::class, // Disabled
        \Clcbws\LaravelIntegrity\Checks\Routing\MissingControllerMethodCheck::class,
    ],
```

## Adding Custom Checks

If you build your own custom checks for your specific application domain, you can register them directly in this `checks` array. The engine will automatically pick them up during execution.

See the [Extending Guide](/extending) for more information on writing custom checks.