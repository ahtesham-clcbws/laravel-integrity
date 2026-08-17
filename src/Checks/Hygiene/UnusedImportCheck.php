<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Hygiene;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Contracts\FixableInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Fixers\UnusedImportFixer;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;

final class UnusedImportCheck implements CheckInterface, FixableInterface
{
    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly UnusedImportFixer $fixer
    ) {}

    public function key(): string
    {
        return 'unused-import';
    }

    public function name(): string
    {
        return 'Unused Imports';
    }

    public function description(): string
    {
        return 'Flag unused classes/interfaces/traits imported via use statements.';
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

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $stmts = $this->parser->parse($content);
            if ($stmts === null) {
                continue;
            }

            // Step 1: Collect all imports
            $visitorCollector = new class extends NodeVisitorAbstract {
                /**
                 * @var array<string, array{node: UseUse, parent: Use_}>
                 */
                public array $imports = [];

                public function enterNode(Node $node)
                {
                    if ($node instanceof Use_) {
                        foreach ($node->uses as $use) {
                            $alias = $use->alias ? $use->alias->name : $use->name->getLast();
                            $this->imports[$alias] = [
                                'node' => $use,
                                'parent' => $node,
                            ];
                        }
                        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }
                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitorCollector]);
            $imports = $visitorCollector->imports;

            if (empty($imports)) {
                continue;
            }

            // Step 2: Traverse AST to find usages of imports
            $visitorUsages = new class(array_keys($imports)) extends NodeVisitorAbstract {
                public array $usedAliases = [];

                public function __construct(private readonly array $importedAliases) {}

                public function enterNode(Node $node)
                {
                    // Ignore use declarations
                    if ($node instanceof Use_) {
                        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }

                    if ($node instanceof Name && !$node instanceof FullyQualified) {
                        $firstSegment = $node->getFirst();
                        if (in_array($firstSegment, $this->importedAliases, true)) {
                            $this->usedAliases[$firstSegment] = true;
                        }
                    }

                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitorUsages]);
            $usedAliases = $visitorUsages->usedAliases;

            // Step 3: Flag unused imports
            foreach ($imports as $alias => $importData) {
                if (!isset($usedAliases[$alias])) {
                    /** @var UseUse $node */
                    $node = $importData['node'];
                    /** @var Use_ $parent */
                    $parent = $importData['parent'];

                    $issues[] = new Issue(
                        severity: Severity::Low,
                        message: "Unused import statement found: '{$node->name->toString()}'.",
                        file: $file,
                        line: $node->getStartLine(),
                        snippet: "use {$node->name->toString()};",
                        fixable: true,
                        context: [
                            'check_name' => $this->name(),
                            'file_path' => $file,
                            'start_pos' => $parent->getStartFilePos(),
                            'end_pos' => $parent->getEndFilePos(),
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

    public function fix(): int
    {
        $result = $this->run();
        return $this->fixer->fix($result->issues);
    }
}
