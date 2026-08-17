<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Hygiene;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

final class DirectEnvCallCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'direct-env';
    }

    public function name(): string
    {
        return 'Direct Env Calls';
    }

    public function description(): string
    {
        return 'Flag usage of env() calls outside the config/ folder.';
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
        $configPath = config('integrity.paths.config', base_path('config'));

        foreach ($files as $file) {
            // Skip files in the config folder
            if (str_starts_with($file, $configPath)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $stmts = $this->parser->parse($content);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $violations = [];

                public function enterNode(Node $node)
                {
                    if ($node instanceof FuncCall && $node->name instanceof Name) {
                        if ($node->name->toString() === 'env') {
                            $this->violations[] = [
                                'line' => $node->getStartLine(),
                            ];
                        }
                    }
                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitor]);

            foreach ($visitor->violations as $violation) {
                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "Direct call to env() outside config file is prohibited. Use config() instead.",
                    file: $file,
                    line: $violation['line'],
                    snippet: 'env(...)',
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
