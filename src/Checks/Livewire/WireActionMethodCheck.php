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

final class WireActionMethodCheck implements CheckInterface
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly ComponentRegistryBridge $livewire,
        private readonly LivewireComponentMapper $mapper
    ) {}

    public function key(): string
    {
        return 'wire-action-method';
    }

    public function name(): string
    {
        return 'Livewire Action Methods';
    }

    public function description(): string
    {
        return 'Verify that methods bound to wire:click/submit actions exist and are public on the component class.';
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

            // Matches patterns like wire:click="save", wire:submit="delete(1)"
            $pattern = '/wire:(?:click|submit|keydown|keyup|change|input|model)(?:\.[a-zA-Z0-9-]+)*\s*=\s*[\'"]([a-zA-Z0-9_]+)(?:\([^)]*\))?[\'"]/';

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $reflection = new ReflectionClass($classFqn);

                foreach ($matches[1] as $match) {
                    $methodName = $match[0];
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;

                    // If it is wire:model, skip it (dealt with in PropertyVisibilityCheck)
                    // We only check callable methods here
                    if (str_contains($content, 'wire:model') && $this->isProperty($reflection, $methodName)) {
                        continue;
                    }

                    // Ignore standard magic methods of Livewire (like $set, $toggle, $refresh, $parent, $dispatch)
                    if (str_starts_with($methodName, '$')) {
                        continue;
                    }

                    $methodExists = $reflection->hasMethod($methodName);
                    $isPublic = false;

                    if ($methodExists) {
                        $method = $reflection->getMethod($methodName);
                        $isPublic = $method->isPublic();
                    }

                    if (!$methodExists || !$isPublic) {
                        $msg = !$methodExists
                            ? "Action method '{$methodName}()' does not exist on Livewire class '{$classFqn}'."
                            : "Action method '{$methodName}()' exists on Livewire class '{$classFqn}' but is not public.";

                        $issues[] = new Issue(
                            severity: Severity::Critical,
                            message: $msg,
                            file: $file,
                            line: $line,
                            snippet: "method: {$methodName}",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'class_fqn' => $classFqn,
                                'method' => $methodName,
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

    private function isProperty(ReflectionClass $reflection, string $name): bool
    {
        return $reflection->hasProperty($name);
    }
}
