<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Support;

use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class LivewireComponentMapper
{
    /**
     * @var array<string, string> Map of view name to class FQN
     */
    private array $viewToClassMap = [];

    /**
     * @var array<string, string> Map of class FQN to view name
     */
    private array $classToViewMap = [];

    private bool $scanned = false;

    public function __construct(
        private readonly FileScanner $scanner,
        private readonly AstParserEngine $parser
    ) {}

    /**
     * Get class FQN backing the given Blade view template path.
     */
    public function getClassForView(string $viewPath): ?string
    {
        $this->ensureScanned();

        // Convert absolute path to Laravel view name (e.g. resources/views/livewire/counter.blade.php -> livewire.counter)
        $viewName = $this->resolveViewNameFromPath($viewPath);
        if ($viewName === null) {
            return null;
        }

        if (isset($this->viewToClassMap[$viewName])) {
            return $this->viewToClassMap[$viewName];
        }

        // Try mapping by convention (e.g. livewire.counter -> App\Livewire\Counter)
        if (str_starts_with($viewName, 'livewire.')) {
            $relative = substr($viewName, 9);
            $className = 'App\\Livewire\\' . $this->convertViewToClassName($relative);
            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Get view name mapping to the class.
     */
    public function getViewForClass(string $classFqn): ?string
    {
        $this->ensureScanned();

        if (isset($this->classToViewMap[$classFqn])) {
            return $this->classToViewMap[$classFqn];
        }

        // Convention fallback: App\Livewire\Admin\Dashboard -> livewire.admin.dashboard
        if (str_starts_with($classFqn, 'App\\Livewire\\')) {
            $relative = substr($classFqn, 13);
            return 'livewire.' . $this->convertClassToViewName($relative);
        }

        return null;
    }

    private function ensureScanned(): void
    {
        if ($this->scanned) {
            return;
        }

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

            $visitor = new class extends NodeVisitorAbstract {
                public ?string $classFqn = null;
                public ?string $viewName = null;

                public function enterNode(Node $node)
                {
                    if ($node instanceof Class_) {
                        $this->classFqn = $node->namespacedName ? $node->namespacedName->toString() : null;
                    }

                    // Extract static call to view('livewire.name')
                    if ($node instanceof FuncCall && $node->name instanceof Name) {
                        if ($node->name->toString() === 'view') {
                            $args = $node->getArgs();
                            if (isset($args[0]) && $args[0]->value instanceof String_) {
                                $this->viewName = $args[0]->value->value;
                            }
                        }
                    }
                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitor]);

            if ($visitor->classFqn && $visitor->viewName) {
                $this->viewToClassMap[$visitor->viewName] = $visitor->classFqn;
                $this->classToViewMap[$visitor->classFqn] = $visitor->viewName;
            }
        }

        $this->scanned = true;
    }

    private function resolveViewNameFromPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        
        // Find position of 'resources/views/'
        $pos = strpos($normalized, 'resources/views/');
        if ($pos === false) {
            return null;
        }

        $relative = substr($normalized, $pos + 16);
        
        // Strip .blade.php
        if (str_ends_with($relative, '.blade.php')) {
            $relative = substr($relative, 0, -10);
        }

        return str_replace('/', '.', $relative);
    }

    private function convertViewToClassName(string $viewName): string
    {
        $parts = explode('.', $viewName);
        $casedParts = array_map(static function (string $part): string {
            return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
        }, $parts);

        return implode('\\', $casedParts);
    }

    private function convertClassToViewName(string $className): string
    {
        $parts = explode('\\', $className);
        $lowercasedParts = array_map(static function (string $part): string {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $part));
        }, $parts);

        return implode('.', $lowercasedParts);
    }
}
