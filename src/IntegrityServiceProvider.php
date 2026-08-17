<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity;

use Illuminate\Support\ServiceProvider;
use Clcbws\LaravelIntegrity\Commands\IntegrityAuditCommand;
use Clcbws\LaravelIntegrity\Commands\IntegrityMcpCommand;
use Clcbws\LaravelIntegrity\Commands\IntegrityPrReviewCommand;
use Clcbws\LaravelIntegrity\Commands\IntegrityFixCommand;
use Clcbws\LaravelIntegrity\Commands\IntegrityBaselineCommand;
use Clcbws\LaravelIntegrity\Support\FileScanner;

final class IntegrityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/integrity.php',
            'integrity'
        );

        $this->app->singleton(FileScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/integrity.php' => config_path('integrity.php'),
            ], 'integrity-config');

            $this->commands([
                IntegrityAuditCommand::class,
                IntegrityMcpCommand::class,
                IntegrityPrReviewCommand::class,
                IntegrityFixCommand::class,
                IntegrityBaselineCommand::class,
            ]);
        }
    }
}
