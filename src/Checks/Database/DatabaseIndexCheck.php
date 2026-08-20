<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

final class DatabaseIndexCheck implements CheckInterface
{
    public function key(): string
    {
        return 'database-index';
    }

    public function name(): string
    {
        return "Database Index Hygiene";
    }

    public function description(): string
    {
        return "Detects missing indexes on foreign key columns (e.g., _id) in the database schema.";
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
            $connection = DB::connection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $tables = $schemaManager->listTableNames();
            
            foreach ($tables as $tableName) {
                $columns = $schemaManager->listTableColumns($tableName);
                $indexes = $schemaManager->listTableIndexes($tableName);
                
                $indexedColumns = [];
                foreach ($indexes as $index) {
                    foreach ($index->getColumns() as $col) {
                        $indexedColumns[] = strtolower($col);
                    }
                }
                
                foreach ($columns as $column) {
                    $colName = $column->getName();
                    if (str_ends_with($colName, "_id") && !in_array(strtolower($colName), $indexedColumns)) {
                        $issues[] = new Issue(
                            severity: Severity::Medium,
                            message: "Missing database index on foreign key column: `{$colName}` in table `{$tableName}`.",
                            file: "Database Table: {$tableName}",
                            line: 0,
                            snippet: $colName,
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'table_name' => $tableName,
                                'column_name' => $colName,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fail if database is not connected during static analysis
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }
}
