<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;

final class UnreferencedPrivateMethodCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'unreferenced-private-method';
    }

    public function name(): string
    {
        return "Dead Code Elimination (DCE)";
    }

    public function description(): string
    {
        return "Detects private methods that are never called locally within the same class (\$this->method()).";
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
        $nodeFinder = new NodeFinder;

        foreach ($files as $file) {
            $code = file_get_contents($file);
            if ($code === false) {
                continue;
            }
            try {
                $stmts = $this->parser->parse($code);
            } catch (\Throwable $e) {
                continue;
            }
            if ($stmts === null) {
                continue;
            }

            $classes = $nodeFinder->findInstanceOf($stmts, Class_::class);

            foreach ($classes as $classNode) {
                $methods = $classNode->getMethods();
                $privateMethods = [];
                
                foreach ($methods as $method) {
                    if ($method->isPrivate() && $method->name->toString() !== "__construct") {
                        $privateMethods[$method->name->toString()] = $method;
                    }
                }

                if (empty($privateMethods)) {
                    continue;
                }

                $methodCalls = $nodeFinder->findInstanceOf($classNode, MethodCall::class);
                $calledMethods = [];

                foreach ($methodCalls as $call) {
                    if ($call->var instanceof \PhpParser\Node\Expr\Variable && $call->var->name === "this") {
                        if ($call->name instanceof \PhpParser\Node\Identifier) {
                            $calledMethods[] = $call->name->toString();
                        }
                    }
                }

                foreach ($privateMethods as $name => $methodNode) {
                    if (!in_array($name, $calledMethods)) {
                        $issues[] = new Issue(
                            severity: Severity::Low,
                            message: "Dead Code: Private method `{$name}()` is never called within the class.",
                            file: $file,
                            line: $methodNode->getLine(),
                            snippet: "private function {$name}()",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'method_name' => $name,
                            ]
                        );
                    }
                }
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
