<?php

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseIndexCheck implements Check
{
    public function name(): string
    {
        return "Database Index Hygiene";
    }

    public function description(): string
    {
        return "Detects missing indexes on foreign key columns (e.g., _id) in the database schema.";
    }

    public function run(array $files, bool $full = false): array
    {
        if (!$full) {
            return [];
        }

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
                            $tableName,
                            0,
                            "Missing database index on foreign key column: `{$colName}` in table `{$tableName}`."
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database is not connected during static analysis
        }

        return $issues;
    }
}
