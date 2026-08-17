<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Class_;
use ReflectionClass;

final class ContainerReflectionEngine
{
    private bool $scannedModels = false;
    private array $models = [];

    public function __construct(
        private readonly FileScanner $scanner,
        private readonly AstParserEngine $parser
    ) {}

    /**
     * Get all registered routes.
     */
    public function getRoutes(): array
    {
        return Route::getRoutes()->getRoutes();
    }

    /**
     * Get all Eloquent models defined in the application.
     *
     * @return array<int, string> List of class FQNs.
     */
    public function getModels(): array
    {
        if ($this->scannedModels) {
            return $this->models;
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

                public function enterNode(Node $node)
                {
                    if ($node instanceof Class_) {
                        $this->classFqn = $node->namespacedName ? $node->namespacedName->toString() : null;
                    }
                    return null;
                }
            };

            $this->parser->traverse($stmts, [$visitor]);

            if ($visitor->classFqn && class_exists($visitor->classFqn)) {
                $ref = new ReflectionClass($visitor->classFqn);
                if ($ref->isSubclassOf(Model::class) && !$ref->isAbstract()) {
                    $this->models[] = $visitor->classFqn;
                }
            }
        }

        $this->scannedModels = true;
        return $this->models;
    }

    /**
     * Get all registered policies from the Gate.
     *
     * @return array<string, string> Map of Model class to Policy class.
     */
    public function getPolicies(): array
    {
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        
        if (method_exists($gate, 'policies')) {
            return $gate->policies();
        }

        return [];
    }
}
