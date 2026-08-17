<?php

namespace Clcbws\LaravelIntegrity\Checks\Hygiene;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;
use PhpParser\ParserFactory;
use PhpParser\NodeFinder;
use PhpParser\Node\Expr\FuncCall;

class MissingEnvVariableCheck implements Check
{
    public function name(): string
    {
        return "Env Variable Contract Checking";
    }

    public function description(): string
    {
        return "Scans for env(\"KEY\") calls and ensures they exist in .env.example.";
    }

    public function run(array $files, bool $full = false): array
    {
        $issues = [];
        
        $envExamplePath = base_path(".env.example");
        if (!file_exists($envExamplePath)) {
            return []; // Cannot check if there is no .env.example
        }
        
        $envExampleContent = file_get_contents($envExamplePath);
        preg_match_all("/^([A-Z0-9_]+)=/m", $envExampleContent, $matches);
        $expectedKeys = $matches[1] ?? [];
        
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        foreach ($files as $file) {
            // Usually we shouldn"t call env() outside of config/ anyway, but this checks all uses
            $code = file_get_contents($file->getPathname());
            try {
                $stmts = $parser->parse($code);
            } catch (\Exception $e) {
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
                                $file->getPathname(),
                                $call->getLine(),
                                "Env variable `{$envKey}` is used but not documented in `.env.example`."
                            );
                        }
                    }
                }
            }
        }

        return $issues;
    }
}
