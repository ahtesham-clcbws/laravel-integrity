<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Blade;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Illuminate\Support\Facades\View;

final class OrphanedComponentCheck implements CheckInterface
{
    public function __construct(private readonly FileScanner $scanner) {}

    public function key(): string
    {
        return 'orphaned-component';
    }

    public function name(): string
    {
        return 'Orphaned Blade Components';
    }

    public function description(): string
    {
        return 'Identify anonymous or class-based components (<x-...>) that are missing their backing class/view.';
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        $files = $this->scanner->scanBladeFiles();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Find all <x- component opening tags
            // Pattern matches: <x-name, <x-name-spaced.name, <x-name::nested etc.
            if (preg_match_all('/<x-([a-zA-Z0-9\-._:]+)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $componentName = $match[0];
                    $offset = $match[1];

                    // Convert offset to line number
                    $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;

                    // Ignore components configured in exclusions
                    $exclusions = config('integrity.orphaned_component_exclusions', ['slot', 'dynamic-component', 'mail::', 'heroicon-']);
                    $shouldIgnore = false;
                    foreach ($exclusions as $exclusion) {
                        if ($componentName === $exclusion || str_starts_with($componentName, rtrim($exclusion, '-') . '-')) {
                            $shouldIgnore = true;
                            break;
                        }
                        // Also handle direct colon prefixes like slot: or mail::
                        if (str_ends_with($exclusion, ':') && str_starts_with($componentName, $exclusion)) {
                            $shouldIgnore = true;
                            break;
                        }
                    }
                    if ($shouldIgnore) {
                        continue;
                    }

                    if (!$this->componentExists($componentName)) {
                        $issues[] = new Issue(
                            severity: Severity::High,
                            message: "Blade component '<x-{$componentName}>' does not have a backing class or view template.",
                            file: $file,
                            line: $line,
                            snippet: "<x-{$componentName}>",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'component_name' => $componentName,
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

    /**
     * Resolve if a Blade component exists.
     */
    private function componentExists(string $tagName): bool
    {
        // 1. Check anonymous view resolution (e.g. components.alert)
        $viewName = 'components.' . $tagName;
        if (View::exists($viewName)) {
            return true;
        }

        // 2. Check class resolution (e.g., App\View\Components\Alert)
        // Convert tagName inputs.text-field -> Inputs\TextField
        $parts = explode('.', $tagName);
        $casedParts = array_map(static function (string $part): string {
            // inputs-field -> InputsField -> namespace parts
            $subParts = explode(':', $part);
            $casedSubParts = array_map(static function (string $sub): string {
                return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $sub)));
            }, $subParts);
            return implode('\\', $casedSubParts);
        }, $parts);

        $className = 'App\\View\\Components\\' . implode('\\', $casedParts);
        if (class_exists($className)) {
            return true;
        }

        return false;
    }
}
