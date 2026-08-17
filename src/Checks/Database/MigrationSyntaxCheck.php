<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;

final class MigrationSyntaxCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'migration-syntax';
    }

    public function name(): string
    {
        return 'Migration Syntax Check';
    }

    public function description(): string
    {
        return 'Assert that database migration files contain no syntax errors and parse cleanly.';
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        $files = $this->scanner->scanPhpFiles();

        foreach ($files as $file) {
            // Only verify files under database/migrations
            if (!str_contains($file, 'migrations')) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // 1. Check parser validation
            $stmts = $this->parser->parse($content);
            $hasParseError = ($stmts === null);

            // 2. Lint verification check
            exec(sprintf('php -l %s 2>&1', escapeshellarg($file)), $output, $returnCode);

            if ($hasParseError || $returnCode !== 0) {
                $errorMsg = !empty($output) ? implode(' ', $output) : 'Unable to parse PHP file syntax.';
                $errorMsg = preg_replace('/in .* on line \d+/', '', $errorMsg);

                $issues[] = new Issue(
                    severity: Severity::Critical,
                    message: "Database migration file contains syntax errors: " . trim($errorMsg),
                    file: $file,
                    line: null,
                    snippet: null,
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
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
