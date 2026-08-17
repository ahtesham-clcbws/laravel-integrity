<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Livewire;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\ComponentRegistryBridge;
use Clcbws\LaravelIntegrity\Support\LivewireComponentMapper;
use ReflectionClass;

final class PropertyVisibilityCheck implements CheckInterface
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly ComponentRegistryBridge $livewire,
        private readonly LivewireComponentMapper $mapper
    ) {}

    public function key(): string
    {
        return 'wire-visibility';
    }

    public function name(): string
    {
        return 'Livewire Property Visibility';
    }

    public function description(): string
    {
        return 'Verify that properties bound via wire:model exist and are public on the component class.';
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        if (!$this->livewire->isInstalled()) {
            return new CheckResult(true, $this->key(), [], 0.0);
        }

        $files = $this->scanner->scanBladeFiles();

        foreach ($files as $file) {
            $classFqn = $this->mapper->getClassForView($file);
            if ($classFqn === null || !class_exists($classFqn)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Matches wire:model="name", wire:model.live="user.name"
            $pattern = '/wire:model(?:\.[a-zA-Z0-9-]+)*\s*=\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]/';

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $reflection = new ReflectionClass($classFqn);

                foreach ($matches[1] as $match) {
                    $binding = $match[0];
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;

                    // If it is dot nested (e.g. user.name), the root property is "user"
                    $parts = explode('.', $binding);
                    $propertyName = $parts[0];

                    $propertyExists = $reflection->hasProperty($propertyName);
                    $isPublic = false;

                    if ($propertyExists) {
                        $property = $reflection->getProperty($propertyName);
                        $isPublic = $property->isPublic();
                    }

                    if (!$propertyExists || !$isPublic) {
                        $msg = !$propertyExists
                            ? "Property '\${$propertyName}' bound via wire:model is not declared on Livewire class '{$classFqn}'."
                            : "Property '\${$propertyName}' bound via wire:model exists on Livewire class '{$classFqn}' but is not public.";

                        $issues[] = new Issue(
                            severity: Severity::High,
                            message: $msg,
                            file: $file,
                            line: $line,
                            snippet: "wire:model=\"{$binding}\"",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'class_fqn' => $classFqn,
                                'property' => $propertyName,
                            ]
                        );
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
