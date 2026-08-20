<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Security;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

final class ModelMassAssignmentCheck implements CheckInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'model-mass-assignment';
    }

    public function name(): string
    {
        return 'Model Mass Assignment Security';
    }

    public function description(): string
    {
        return 'Detects models with $guarded = [] that are high risk for mass assignment vulnerabilities.';
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
            if (strpos($file, 'app/Models') === false) {
                continue;
            }

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
                $properties = $nodeFinder->findInstanceOf($classNode, Property::class);
                foreach ($properties as $propertyNode) {
                    foreach ($propertyNode->props as $prop) {
                        if ($prop->name->toString() === 'guarded') {
                            if ($prop->default instanceof \PhpParser\Node\Expr\Array_) {
                                if (count($prop->default->items) === 0) {
                                    $issues[] = new Issue(
                                        severity: Severity::High,
                                        message: 'Model uses `protected $guarded = [];` which disables mass assignment protection. Requires strict validation on input.',
                                        file: $file,
                                        line: $prop->getLine(),
                                        snippet: 'protected $guarded = [];',
                                        fixable: false,
                                        context: [
                                            'check_name' => $this->name(),
                                            'file_path' => $file,
                                        ]
                                    );
                                }
                            }
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
