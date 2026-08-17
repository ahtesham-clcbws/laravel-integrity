<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Blade;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\BladeTokenScanner;
use Illuminate\Support\Facades\View;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

final class MissingViewPathCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly BladeTokenScanner $bladeScanner
    ) {}

    public function key(): string
    {
        return 'missing-view';
    }

    public function name(): string
    {
        return 'Missing View Templates';
    }

    public function description(): string
    {
        return 'Scan files and templates for view references where the template file does not exist.';
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        // Scan PHP files
        $phpFiles = $this->scanner->scanPhpFiles();
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $stmts = $this->parser->parse($content);
            if ($stmts === null) {
                continue;
            }

            $issues = array_merge($issues, $this->scanStmts($stmts, $file));
        }

        // Scan Blade files
        $bladeFiles = $this->scanner->scanBladeFiles();
        foreach ($bladeFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $stmts = $this->bladeScanner->compileAndParse($content);
            if ($stmts === null) {
                continue;
            }

            $issues = array_merge($issues, $this->scanStmts($stmts, $file));
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }

    /**
     * Scan AST statements for view violations.
     *
     * @param array<Node\Stmt> $stmts
     * @return array<Issue>
     */
    private function scanStmts(array $stmts, string $file): array
    {
        $issues = [];

        $visitor = new class extends NodeVisitorAbstract {
            public array $views = [];

            public function enterNode(Node $node)
            {
                // 1. view('name') function call
                if ($node instanceof FuncCall && $node->name instanceof Name) {
                    if ($node->name->toString() === 'view') {
                        $args = $node->getArgs();
                        if (isset($args[0]) && $args[0]->value instanceof String_) {
                            $this->views[] = [
                                'name' => $args[0]->value->value,
                                'line' => $node->getStartLine(),
                            ];
                        }
                    }
                }

                // 2. $__env->make('name') method call (Blade @include/@extends compilation)
                if ($node instanceof MethodCall && 
                    $node->var instanceof Variable && 
                    $node->var->name === '__env' &&
                    $node->name instanceof Identifier &&
                    in_array($node->name->toString(), ['make', 'render', 'first'], true)
                ) {
                    $args = $node->getArgs();
                    if (isset($args[0]) && $args[0]->value instanceof String_) {
                        $this->views[] = [
                            'name' => $args[0]->value->value,
                            'line' => $node->getStartLine(),
                        ];
                    }
                }

                return null;
            }
        };

        $this->parser->traverse($stmts, [$visitor]);

        foreach ($visitor->views as $viewData) {
            $viewName = $viewData['name'];
            
            // Skip dynamic view calls or views containing variables/dots only
            if (empty($viewName) || str_contains($viewName, '$')) {
                continue;
            }

            if (!View::exists($viewName)) {
                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "View template '{$viewName}' does not exist on disk.",
                    file: $file,
                    line: $viewData['line'],
                    snippet: "view('{$viewName}')",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
                        'view_name' => $viewName,
                    ]
                );
            }
        }

        return $issues;
    }
}
