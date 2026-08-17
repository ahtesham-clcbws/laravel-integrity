<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Target Paths for Scanning
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'app' => app_path(),
        'views' => resource_path('views'),
        'routes' => base_path('routes'),
        'database' => database_path(),
        'config' => config_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths / Files
    |--------------------------------------------------------------------------
    */
    'ignore' => [
        'routes' => [
            'horizon.*',
            'telescope.*',
            'debugbar.*',
            'sanctum.*',
        ],
        'views' => [
            'vendor/*',
        ],
        'paths' => [
            'storage/*',
            'bootstrap/cache/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Check Pipeline
    |--------------------------------------------------------------------------
    */
    'checks' => [
        // Hygiene
        Clcbws\LaravelIntegrity\Checks\Hygiene\StrictTypesDeclarationCheck::class,
        Clcbws\LaravelIntegrity\Checks\Hygiene\RootNamespaceFacadeCheck::class,
        Clcbws\LaravelIntegrity\Checks\Hygiene\DirectEnvCallCheck::class,
        Clcbws\LaravelIntegrity\Checks\Hygiene\UnusedImportCheck::class,
        Clcbws\LaravelIntegrity\Checks\Hygiene\MissingEnvVariableCheck::class,

        // Routing & Blade
        Clcbws\LaravelIntegrity\Checks\Routing\RouteSerializationCheck::class,
        Clcbws\LaravelIntegrity\Checks\Routing\MissingNamedRouteCheck::class,
        Clcbws\LaravelIntegrity\Checks\Routing\DanglingControllerActionCheck::class,
        Clcbws\LaravelIntegrity\Checks\Blade\BladeCompileCheck::class,
        Clcbws\LaravelIntegrity\Checks\Blade\MissingViewPathCheck::class,
        Clcbws\LaravelIntegrity\Checks\Blade\OrphanedComponentCheck::class,

        // Livewire
        Clcbws\LaravelIntegrity\Checks\Livewire\ComponentManifestCheck::class,
        Clcbws\LaravelIntegrity\Checks\Livewire\WireActionMethodCheck::class,
        Clcbws\LaravelIntegrity\Checks\Livewire\PropertyVisibilityCheck::class,

        // Database & Architecture
        Clcbws\LaravelIntegrity\Checks\Database\SeederClassExistsCheck::class,
        Clcbws\LaravelIntegrity\Checks\Database\PendingMigrationCheck::class,
        Clcbws\LaravelIntegrity\Checks\Database\MigrationSyntaxCheck::class,
        Clcbws\LaravelIntegrity\Checks\Database\ModelTableMappingCheck::class,
        Clcbws\LaravelIntegrity\Checks\Database\DatabaseIndexCheck::class,
        Clcbws\LaravelIntegrity\Checks\Architecture\DeadProviderCheck::class,
        Clcbws\LaravelIntegrity\Checks\Architecture\PolicyMethodMappingCheck::class,
        Clcbws\LaravelIntegrity\Checks\Architecture\EventListenerMappingCheck::class,
        Clcbws\LaravelIntegrity\Checks\Architecture\UnreferencedPrivateMethodCheck::class,
        // Security
        Clcbws\LaravelIntegrity\Checks\Security\ModelMassAssignmentCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Reports
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => 'single',
        'file' => storage_path('logs/integrity.log'),
        'format' => 'text', // 'text' | 'json'
    ],

    /*
    |--------------------------------------------------------------------------
    | Baseline Configuration
    |--------------------------------------------------------------------------
    */
    'baseline_file' => base_path('.integrity-baseline.json'),
];
