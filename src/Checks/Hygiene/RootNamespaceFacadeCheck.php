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
use Clcbws\LaravelIntegrity\Fixers\FacadeImportFixer;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;

final class RootNamespaceFacadeCheck implements CheckInterface, FixableInterface
{
    private static array $facades = [
        'DB', 'Auth', 'Cache', 'Log', 'Session', 'Config', 'Schema', 
        'Request', 'Response', 'Route', 'Mail', 'Event', 'Queue', 
        'Storage', 'Validator', 'Hash', 'Crypt', 'Gate', 'Lang', 
        'URL', 'View', 'Cookie', 'File', 'Notification', 'Bus', 
        'Blade', 'RateLimiter', 'Process'
    ];

    public function __construct(
        private readonly AstParserEngine $parser,
        private readonly FileScanner $scanner,
        private readonly FacadeImportFixer $fixer
    ) {}

    public function key(): string
    {
        return 'root-facade';
    }

    public function name(): string
    {
        return 'Root Namespace Facades';
    }

    public function description(): string
    {
        return 'Identify and fix root namespace facade calls (like \DB::) to use clean imports.';
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

            $visitor = new class(self::$facades) extends NodeVisitorAbstract {
                public array $violations = [];

                public function __construct(private readonly array $facades) {}

                public function enterNode(Node $node)
                {
                    if ($node instanceof StaticCall) {
                        $class = $node->class;
                        if ($class instanceof FullyQualified) {
                            $name = $class->toString();
                            // Root level namespace checks will have no slashes in FQN string representation in PHP-Parser
                            if (!str_contains($name, '\\') && in_array($name, $this->facades, true)) {
                                $this->violations[] = [
                                    'facade' => $name,
                                    'line' => $class->getStartLine(),
                                    'start_pos' => $class->getStartFilePos(),
                                    'end_pos' => $class->getEndFilePos(),
                                ];
                            }
                        }
                    }
                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitor]);

            foreach ($visitor->violations as $violation) {
                $issues[] = new Issue(
                    severity: Severity::Low,
                    message: "Root namespace call to facade '\\{$violation['facade']}' detected.",
                    file: $file,
                    line: $violation['line'],
                    snippet: "\\{$violation['facade']}::",
                    fixable: true,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
                        'facade' => $violation['facade'],
                        'start_pos' => $violation['start_pos'],
                        'end_pos' => $violation['end_pos'],
                    ]
                );
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
