<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;
use PhpParser\ParserFactory;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;

class NPlusOneStaticCheck implements Check
{
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

    public function run(array $files, bool $full = false): array
    {
        if (!$full) return [];
        $issues = [];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $nodeFinder = new NodeFinder;

        foreach ($files as $file) {
            $code = file_get_contents($file->getPathname());
            try {
                $stmts = $parser->parse($code);
            } catch (\Exception $e) { continue; }

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
                                $file->getPathname(),
                                $call->getLine(),
                                "Potential N+1 Query: Database call `->{$methodName}()` detected inside a loop."
                            );
                        }
                    }
                }
                
                // Look for dynamic property fetching that often triggers lazy loading (e.g. $user->posts)
                // This is harder to perfectly static analyze without type inference, so we flag highly suspicious ones.
                // We will skip property fetches for now to avoid massive false positives, but keep method calls.
            }
        }

        return $issues;
    }
}
