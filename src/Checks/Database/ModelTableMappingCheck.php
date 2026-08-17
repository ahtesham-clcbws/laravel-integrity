<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\ContainerReflectionEngine;
use Illuminate\Support\Facades\Schema;

final class ModelTableMappingCheck implements CheckInterface
{
    public function __construct(private readonly ContainerReflectionEngine $reflection) {}

    public function key(): string
    {
        return 'model-table-mapping';
    }

    public function name(): string
    {
        return 'Model Table Mapping';
    }

    public function description(): string
    {
        return 'Assert that all Eloquent models map to a valid backing database table.';
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
            $models = $this->reflection->getModels();

            foreach ($models as $modelClass) {
                // Instantiate the model dynamically
                /** @var \Illuminate\Database\Eloquent\Model $modelInstance */
                $modelInstance = new $modelClass();
                $tableName = $modelInstance->getTable();

                if (!Schema::hasTable($tableName)) {
                    $ref = new \ReflectionClass($modelClass);

                    $issues[] = new Issue(
                        severity: Severity::High,
                        message: "Model '{$modelClass}' maps to table '{$tableName}' which does not exist in the database.",
                        file: (string) $ref->getFileName(),
                        line: null,
                        snippet: "protected \$table = '{$tableName}'",
                        fixable: false,
                        context: [
                            'check_name' => $this->name(),
                            'model' => $modelClass,
                            'table' => $tableName,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            $issues[] = new Issue(
                severity: Severity::Critical,
                message: "Database connection failed during model table mapping check: " . $e->getMessage(),
                file: 'database/schema',
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
