<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Routing;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\BladeTokenScanner;
use Illuminate\Support\Facades\Route;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class MissingNamedRouteCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly BladeTokenScanner $bladeScanner
    ) {}

    public function key(): string
    {
        return 'missing-named-route';
    }

    public function name(): string
    {
        return 'Missing Named Routes';
    }

    public function description(): string
    {
        return 'Scan files and templates for route() calls referencing non-existent routes.';
    }

    public function isExpensive(): bool
    {
        // Requires compiling views and container routing lookup
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
     * Scan AST statements for route() violations.
     *
     * @param array<Node\Stmt> $stmts
     * @return array<Issue>
     */
    private function scanStmts(array $stmts, string $file): array
    {
        $issues = [];

        $visitor = new class extends NodeVisitorAbstract {
            public array $routes = [];

            public function enterNode(Node $node)
            {
                if ($node instanceof FuncCall && $node->name instanceof Name) {
                    if ($node->name->toString() === 'route') {
                        $args = $node->getArgs();
                        if (isset($args[0]) && $args[0]->value instanceof String_) {
                            $this->routes[] = [
                                'name' => $args[0]->value->value,
                                'line' => $node->getStartLine(),
                            ];
                        }
                    }
                }
                return null;
            }
        };

        $this->parser->traverse($stmts, [$visitor]);

        foreach ($visitor->routes as $routeData) {
            $routeName = $routeData['name'];
            if (!Route::has($routeName)) {
                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "Named route '{$routeName}' is not defined in route configurations.",
                    file: $file,
                    line: $routeData['line'],
                    snippet: "route('{$routeName}')",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
                        'route_name' => $routeName,
                    ]
                );
            }
        }

        return $issues;
    }
}
