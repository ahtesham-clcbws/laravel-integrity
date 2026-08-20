<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Database;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class SeederClassExistsCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'seeder-exists';
    }

    public function name(): string
    {
        return 'Seeder Class Existence';
    }

    public function description(): string
    {
        return 'Verify that seeder classes called via $this->call() exist and resolve on disk.';
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
        
        $seederPath = config('integrity.paths.database', database_path('seeders'));

        foreach ($files as $file) {
            // Only scan files in the seeders database path
            if (!str_contains($file, 'seeders')) {
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
                public array $seederCalls = [];

                public function leaveNode(Node $node)
                {
                    if ($node instanceof MethodCall && 
                        $node->var instanceof Variable && 
                        $node->var->name === 'this' &&
                        $node->name instanceof Identifier &&
                        $node->name->toString() === 'call'
                    ) {
                        $args = $node->getArgs();
                        if (isset($args[0])) {
                            $val = $args[0]->value;
                            if ($val instanceof Array_) {
                                foreach ($val->items as $item) {
                                    if ($item !== null) {
                                        $this->extractClass($item->value, $node->getStartLine());
                                    }
                                }
                            } else {
                                $this->extractClass($val, $node->getStartLine());
                            }
                        }
                    }
                    return null;
                }

                private function extractClass(Node $node, int $line): void
                {
                    // ClassName::class
                    if ($node instanceof ClassConstFetch && $node->class instanceof Name) {
                        $this->seederCalls[] = [
                            'class' => $node->class->toString(),
                            'line' => $line,
                        ];
                    }
                    // 'ClassName' string
                    if ($node instanceof String_) {
                        $this->seederCalls[] = [
                            'class' => $node->value,
                            'line' => $line,
                        ];
                    }
                }
            };

            // Run name resolver first to FQN resolve seeder class references
            $this->parser->traverse($stmts, [new NameResolver(), $visitor]);

            foreach ($visitor->seederCalls as $call) {
                $className = $call['class'];
                
                // If it is self or static referer, ignore
                if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
                    continue;
                }

                if (!class_exists($className)) {
                    $issues[] = new Issue(
                        severity: Severity::High,
                        message: "Seeder class '{$className}' called via \$this->call() does not exist.",
                        file: $file,
                        line: $call['line'],
                        snippet: "call({$className}::class)",
                        fixable: false,
                        context: [
                            'check_name' => $this->name(),
                            'file_path' => $file,
                            'seeder_class' => $className,
                        ]
                    );
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
