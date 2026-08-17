<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Hygiene;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Contracts\FixableInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Fixers\StrictTypesFixer;
use PhpParser\Node\Stmt\Declare_;

final class StrictTypesDeclarationCheck implements CheckInterface, FixableInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly StrictTypesFixer $fixer
    ) {}

    public function key(): string
    {
        return 'strict-types';
    }

    public function name(): string
    {
        return 'Strict Types Declaration';
    }

    public function description(): string
    {
        return 'Ensure declare(strict_types=1); is defined at the top of all PHP files.';
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
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $stmts = $this->parser->parse($content);
            if ($stmts === null) {
                continue;
            }

            $hasStrictTypes = false;
            $firstStmt = $stmts[0] ?? null;

            if ($firstStmt instanceof Declare_) {
                foreach ($firstStmt->declares as $declare) {
                    if ($declare->key->name === 'strict_types' &&
                        isset($declare->value->value) &&
                        $declare->value->value === 1
                    ) {
                        $hasStrictTypes = true;
                        break;
                    }
                }
            }

            if (!$hasStrictTypes) {
                $issues[] = new Issue(
                    severity: Severity::Medium,
                    message: "Missing 'declare(strict_types=1);' declaration statement.",
                    file: $file,
                    line: 1,
                    snippet: count($stmts) > 0 ? null : 'Empty file or PHP block only',
                    fixable: true,
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

    public function fix(): int
    {
        $result = $this->run();
        return $this->fixer->fix($result->issues);
    }
}
