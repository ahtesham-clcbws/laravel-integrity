<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Blade;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Support\FileScanner;

final class BladeComponentStrictTypeCheck implements CheckInterface
{
    public function __construct(
        private readonly FileScanner $scanner
    ) {}

    public function key(): string
    {
        return 'blade-component-strict-type';
    }

    public function name(): string
    {
        return "Blade Component Strict Types";
    }

    public function description(): string
    {
        return "Statically verifies that required @props are passed to Blade components.";
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        $components = [];
        $files = $this->scanner->scanBladeFiles();

        // First Pass: Find all component definitions and extract their @props
        foreach ($files as $file) {
            $path = $file;
            
            // Heuristic for component name
            // e.g. resources/views/components/alert.blade.php -> x-alert
            if (str_contains($path, "views/components/")) {
                $name = str_replace(".blade.php", "", basename($path));
                
                $content = file_get_contents($path);
                if ($content === false) continue;
                
                // Extract @props(["user", "title"])
                if (preg_match("/@props\(\[(.*?)\]\)/s", $content, $matches)) {
                    $propsStr = $matches[1];
                    // Very simple parsing, expecting "propName" or "propName" => default
                    preg_match_all("/[\"']([a-zA-Z0-9_]+)[\"']\s*(?:=>|$|,)/", $propsStr, $propMatches);
                    // This is a naive extraction. Real one needs to track default values.
                    // If it has =>, it has a default and is not strictly required.
                    preg_match_all("/[\"']([a-zA-Z0-9_]+)[\"']\s*=>/", $propsStr, $defaultMatches);
                    
                    $allProps = $propMatches[1] ?? [];
                    $defaultProps = $defaultMatches[1] ?? [];
                    $requiredProps = array_diff($allProps, $defaultProps);
                    
                    if (!empty($requiredProps)) {
                        $components["x-{$name}"] = $requiredProps;
                    }
                }
            }
        }

        // Second Pass: Scan all blades for usages of those components
        if (!empty($components)) {
            foreach ($files as $file) {
                $path = $file;
                $content = file_get_contents($path);
                if ($content === false) continue;
                
                foreach ($components as $componentTag => $requiredProps) {
                    // Find <x-component ... >
                    if (preg_match_all("/<{$componentTag}\s+([^>]*?)>/is", $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[1] as $match) {
                            $attributesStr = $match[0];
                            $offset = $match[1];
                            $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;
                            
                            foreach ($requiredProps as $prop) {
                                // Check if prop is passed as: prop="..." or :prop="..."
                                if (!preg_match("/:?{$prop}\s*=/", $attributesStr)) {
                                    $issues[] = new Issue(
                                        severity: Severity::Medium,
                                        message: "Strict Types: Missing required prop `{$prop}` when rendering `<{$componentTag}>`.",
                                        file: $path,
                                        line: $line,
                                        snippet: "<{$componentTag} ...>",
                                        fixable: false,
                                        context: [
                                            'check_name' => $this->name(),
                                            'file_path' => $path,
                                            'component' => $componentTag,
                                            'missing_prop' => $prop,
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
