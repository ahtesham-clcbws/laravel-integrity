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
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;

final class NPlusOneStaticCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'n-plus-one';
    }

    public function name(): string
    {
        return "Static N+1 Query Detection";
    }

    public function description(): string
    {
        return "Detects Eloquent queries or lazy-loaded property accesses inside loops (N+1 vulnerability).";
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        
        $files = $this->scanner->scanPhpFiles();
        $nodeFinder = new NodeFinder;

        foreach ($files as $file) {
            $code = file_get_contents($file);
            if ($code === false) continue;
            try {
                $stmts = $this->parser->parse($code);
            } catch (\Throwable $e) { continue; }
            if ($stmts === null) continue;

            // Find all loops
            $loops = array_merge(
                $nodeFinder->findInstanceOf($stmts, Foreach_::class),
                $nodeFinder->findInstanceOf($stmts, While_::class),
                $nodeFinder->findInstanceOf($stmts, For_::class)
            );

            foreach ($loops as $loop) {
                // Look for method calls inside the loop like ->get(), ->first(), ->all(), ->count(), ->save()
                $methodCalls = $nodeFinder->findInstanceOf($loop, MethodCall::class);
                foreach ($methodCalls as $call) {
                    if ($call->name instanceof \PhpParser\Node\Identifier) {
                        $methodName = $call->name->toString();
                        if (in_array($methodName, ["get", "first", "all", "count", "save", "update", "delete", "create"])) {
                            $issues[] = new Issue(
                                severity: Severity::High,
                                message: "Potential N+1 Query: Database call `->{$methodName}()` detected inside a loop.",
                                file: $file,
                                line: $call->getLine(),
                                snippet: "->{$methodName}()",
                                fixable: false,
                                context: [
                                    'check_name' => $this->name(),
                                    'file_path' => $file,
                                    'method_name' => $methodName,
                                ]
                            );
                        }
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
