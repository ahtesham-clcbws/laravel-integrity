<?php

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;
use PhpParser\ParserFactory;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;

class UnreferencedPrivateMethodCheck implements Check
{
    public function name(): string
    {
        return "Dead Code Elimination (DCE)";
    }

    public function description(): string
    {
        return "Detects private methods that are never called locally within the same class ($this->method()).";
    }

    public function run(array $files, bool $full = false): array
    {
        $issues = [];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        foreach ($files as $file) {
            $code = file_get_contents($file->getPathname());
            try {
                $stmts = $parser->parse($code);
            } catch (\Exception $e) {
                continue;
            }

            $nodeFinder = new NodeFinder;
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
                            $file->getPathname(),
                            $methodNode->getLine(),
                            "Dead Code: Private method `{$name}()` is never called within the class."
                        );
                    }
                }
            }
        }

        return $issues;
    }
}
