<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;

final class DeadProviderCheck implements CheckInterface
{
    public function key(): string
    {
        return 'dead-provider';
    }

    public function name(): string
    {
        return 'Dead Service Providers';
    }

    public function description(): string
    {
        return 'Assert that all registered service providers exist and are loadable by the container.';
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        $providers = [];

        // 1. Load from bootstrap/providers.php (Laravel 11+)
        $bootstrapFile = base_path('bootstrap/providers.php');
        if (file_exists($bootstrapFile)) {
            try {
                $loaded = require $bootstrapFile;
                if (is_array($loaded)) {
                    $providers = array_merge($providers, $loaded);
                }
            } catch (\Throwable $e) {
                $issues[] = new Issue(
                    severity: Severity::Critical,
                    message: "Failed to load providers config from [bootstrap/providers.php]: " . $e->getMessage(),
                    file: $bootstrapFile,
                    line: null,
                    snippet: null,
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                    ]
                );
            }
        }

        // 2. Load from config/app.php (Legacy/Alternate)
        $configProviders = config('app.providers', []);
        if (is_array($configProviders)) {
            $providers = array_merge($providers, $configProviders);
        }

        // Unique lists
        $providers = array_unique($providers);

        foreach ($providers as $provider) {
            if (empty($provider) || !is_string($provider)) {
                continue;
            }

            if (!class_exists($provider)) {
                $issues[] = new Issue(
                    severity: Severity::Critical,
                    message: "Registered service provider class '{$provider}' does not exist on disk.",
                    file: file_exists($bootstrapFile) ? $bootstrapFile : config_path('app.php'),
                    line: null,
                    snippet: "'{$provider}'",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'provider_class' => $provider,
                    ]
                );
            }
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }
}
