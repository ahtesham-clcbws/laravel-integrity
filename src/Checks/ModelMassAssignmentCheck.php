<?php

namespace Clcbws\LaravelIntegrity\Checks\Security;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;

use PhpLanguage\Ast\PhpParser;
use PhpParser\Node\Stmt\Class;
use PhpParser\Node\Stmt\Property;

class ModelMassAssignmentCheck implements Check
{
    public function name(): string
    {
        return 'Model Mass Assignment Security';
    }

    public function description(): string
    {
        return 'Detects models with $guarded = [] that are high risk for mass assignment vulnerabilities.';
    }

    public function run(array $files, bool $full = false): array
    {
        $issues = [];
        $parser = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion();

        foreach ($files as $file) {
            if (strpos($file->getPathname(), 'app/Models') === false) {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            try {
                $stmts = $parser->parse($code);
            } catch (\Exception $e) {
                continue;
            }

            $nodeFinder = new \PhpParser\NodeFinder;
            $classes = $nodeFinder->findInstanceOf($stmts, Class_::class);

            foreach ($classes as $classNode) {
                $properties = $nodeFinder->findInstanceOf($classNode, Property::class);
                foreach ($properties as $propertyNode) {
                    foreach ($propertyNode->props as $prop) {
                        if ($prop->name->toString() === 'guarded') {
                            if ($prop->default instanceof \PhpParser\Node\Expr\Array_) {
                                if (count($prop->default->items) === 0) {
                                    $issues[] = new Issue(
                                        $file->getPathname(),
                                        $prope->getLine(),
                                        'Model uses `protected $guarded = [];` which disables mass assignment protection. Requires strict validation on input.'
                                    );
                            }
                        }
                    }
                }
            }
        }
        }
        return $issues;
    }
}
