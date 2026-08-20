<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Hygiene;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use PhpParser\NodeFinder;
use PhpParser\Node\Expr\FuncCall;

final class MissingEnvVariableCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'missing-env-variable';
    }

    public function name(): string
    {
        return "Env Variable Contract Checking";
    }

    public function description(): string
    {
        return "Scans for env(\"KEY\") calls and ensures they exist in .env.example.";
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        
        $envExamplePath = base_path(".env.example");
        if (!file_exists($envExamplePath)) {
            return new CheckResult(true, $this->key(), [], (microtime(true) - $start) * 1000);
        }
        
        $envExampleContent = file_get_contents($envExamplePath);
        if ($envExampleContent === false) {
            return new CheckResult(true, $this->key(), [], (microtime(true) - $start) * 1000);
        }

        preg_match_all("/^([A-Z0-9_]+)=/m", $envExampleContent, $matches);
        $expectedKeys = $matches[1] ?? [];
        
        $files = $this->scanner->scanPhpFiles();

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

            $nodeFinder = new NodeFinder;
            $funcCalls = $nodeFinder->findInstanceOf($stmts, FuncCall::class);

            foreach ($funcCalls as $call) {
                if ($call->name instanceof \PhpParser\Node\Name && $call->name->toString() === "env") {
                    if (isset($call->args[0]) && $call->args[0]->value instanceof \PhpParser\Node\Scalar\String_) {
                        $envKey = $call->args[0]->value->value;
                        if (!in_array($envKey, $expectedKeys)) {
                            $issues[] = new Issue(
                                severity: Severity::Medium,
                                message: "Env variable `{$envKey}` is used but not documented in `.env.example`.",
                                file: $file,
                                line: $call->getLine(),
                                snippet: "env('{$envKey}')",
                                fixable: false,
                                context: [
                                    'check_name' => $this->name(),
                                    'file_path' => $file,
                                    'env_key' => $envKey,
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
