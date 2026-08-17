<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Engine\ContainerReflectionEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\BladeTokenScanner;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;

final class PolicyMethodMappingCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly BladeTokenScanner $bladeScanner,
        private readonly ContainerReflectionEngine $reflection
    ) {}

    public function key(): string
    {
        return 'policy-mapping';
    }

    public function name(): string
    {
        return 'Policy Method Mapping';
    }

    public function description(): string
    {
        return 'Verify that authorization abilities referenced in templates and classes map to existing policy methods.';
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        // Retrieve registered policies
        $policies = $this->reflection->getPolicies();
        $policyClasses = array_values($policies);

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

            $issues = array_merge($issues, $this->scanStmts($stmts, $file, $policyClasses));
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

            $issues = array_merge($issues, $this->scanStmts($stmts, $file, $policyClasses));
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }

    /**
     * Scan AST for authorization calls.
     *
     * @param array<Node\Stmt> $stmts
     * @param array<int, string> $policyClasses
     * @return array<Issue>
     */
    private function scanStmts(array $stmts, string $file, array $policyClasses): array
    {
        $issues = [];

        $visitor = new class extends NodeVisitorAbstract {
            public array $abilities = [];

            public function enterNode(Node $node)
            {
                // 1. $this->authorize('ability')
                if ($node instanceof MethodCall && 
                    $node->name instanceof Identifier && 
                    $node->name->toString() === 'authorize'
                ) {
                    $args = $node->getArgs();
                    if (isset($args[0]) && $args[0]->value instanceof String_) {
                        $this->abilities[] = [
                            'name' => $args[0]->value->value,
                            'line' => $node->getStartLine(),
                        ];
                    }
                }

                // 2. Gate::check('ability'), Gate::allows('ability')
                if ($node instanceof StaticCall && 
                    $node->class instanceof Name &&
                    $node->class->toString() === 'Illuminate\Support\Facades\Gate' &&
                    $node->name instanceof Identifier &&
                    in_array($node->name->toString(), ['check', 'allows', 'denies', 'authorize', 'any'], true)
                ) {
                    $args = $node->getArgs();
                    if (isset($args[0]) && $args[0]->value instanceof String_) {
                        $this->abilities[] = [
                            'name' => $args[0]->value->value,
                            'line' => $node->getStartLine(),
                        ];
                    }
                }

                return null;
            }
        };

        // Run name resolver first to resolve static calls on FQN Gate
        $this->parser->traverse($stmts, [new NameResolver(), $visitor]);

        foreach ($visitor->abilities as $abilityData) {
            $ability = $abilityData['name'];

            // Skip standard built-in abilities
            if (empty($ability) || in_array($ability, ['before', 'after'], true)) {
                continue;
            }

            // Check if ability exists in *any* policy class as a public method
            $exists = false;
            foreach ($policyClasses as $policyClass) {
                if (class_exists($policyClass)) {
                    $ref = new ReflectionClass($policyClass);
                    if ($ref->hasMethod($ability)) {
                        $method = $ref->getMethod($ability);
                        if ($method->isPublic()) {
                            $exists = true;
                            break;
                        }
                    }
                }
            }

            if (!$exists && !empty($policyClasses)) {
                $issues[] = new Issue(
                    severity: Severity::Medium,
                    message: "Authorization ability '{$ability}' does not exist as a public method on any registered policies.",
                    file: $file,
                    line: $abilityData['line'],
                    snippet: "authorize('{$ability}')",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
                        'ability' => $ability,
                    ]
                );
            }
        }

        return $issues;
    }
}
