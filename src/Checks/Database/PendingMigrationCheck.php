<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;

final class PendingMigrationCheck implements CheckInterface
{
    public function key(): string
    {
        return 'pending-migration';
    }

    public function name(): string
    {
        return 'Pending Database Migrations';
    }

    public function description(): string
    {
        return 'Flag outstanding database migrations that have not been applied to the database.';
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        try {
            $migrator = app('migrator');
            
            // Collect migration paths from configured app path
            $paths = $migrator->paths();
            if (empty($paths)) {
                $paths = [database_path('migrations')];
            }

            // Get all migration files on disk
            $files = $migrator->getMigrationFiles($paths);

            if ($migrator->repositoryExists()) {
                $ran = $migrator->getRepository()->getRan();
                $pending = array_diff(array_keys($files), $ran);
            } else {
                $pending = array_keys($files);
            }

            foreach ($pending as $migrationName) {
                $filePath = $files[$migrationName] ?? 'database/migrations';
                
                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "Database migration '{$migrationName}' is pending execution.",
                    file: (string) $filePath,
                    line: null,
                    snippet: null,
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'migration' => $migrationName,
                    ]
                );
            }
        } catch (\Throwable $e) {
            $issues[] = new Issue(
                severity: Severity::Critical,
                message: "Database connection failed during pending migration check: " . $e->getMessage(),
                file: 'database/migrations',
                line: null,
                snippet: null,
                fixable: false,
                context: [
                    'check_name' => $this->name(),
                    'error' => $e->getMessage(),
                ]
            );
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }
}
