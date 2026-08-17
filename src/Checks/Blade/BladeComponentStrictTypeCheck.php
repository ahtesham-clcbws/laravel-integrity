<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Blade;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbws\LaravelIntegrity\Data\Issue;

class BladeComponentStrictTypeCheck implements Check
{
    public function name(): string
    {
        return "Blade Component Strict Types";
    }

    public function description(): string
    {
        return "Statically verifies that required @props are passed to Blade components.";
    }

    public function run(array $files, bool $full = false): array
    {
        $issues = [];
        $components = [];

        // First Pass: Find all component definitions and extract their @props
        foreach ($files as $file) {
            $path = $file->getPathname();
            if (!str_ends_with($path, ".blade.php")) continue;
            
            // Heuristic for component name
            // e.g. resources/views/components/alert.blade.php -> x-alert
            if (str_contains($path, "views/components/")) {
                $name = str_replace(".blade.php", "", basename($path));
                
                $content = file_get_contents($path);
                // Extract @props(["user", "title"])
                if (preg_match("/@props\(\[(.*?)\]\)/s", $content, $matches)) {
                    $propsStr = $matches[1];
                    // Very simple parsing, expecting "propName" or "propName" => default
                    preg_match_all("/["\"]([a-zA-Z0-9_]+)["\"]\s*(?:=>|$|,)/", $propsStr, $propMatches);
                    // This is a naive extraction. Real one needs to track default values.
                    // If it has =>, it has a default and is not strictly required.
                    preg_match_all("/["\"]([a-zA-Z0-9_]+)["\"]\s*=>/", $propsStr, $defaultMatches);
                    
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
        if (empty($components)) return [];

        foreach ($files as $file) {
            $path = $file->getPathname();
            if (!str_ends_with($path, ".blade.php")) continue;
            
            $content = file_get_contents($path);
            
            foreach ($components as $componentTag => $requiredProps) {
                // Find <x-component ... >
                if (preg_match_all("/<{$componentTag}\s+([^>]*?)>/is", $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $match) {
                        $attributesStr = $match[0];
                        $offset = $match[1];
                        
                        foreach ($requiredProps as $prop) {
                            // Check if prop is passed as: prop="..." or :prop="..."
                            if (!preg_match("/:?{$prop}\s*=/", $attributesStr)) {
                                $issues[] = new Issue(
                                    $path,
                                    0, // Line number calculation requires offset mapping
                                    "Strict Types: Missing required prop `{$prop}` when rendering `<{$componentTag}>`."
                                );
                            }
                        }
                    }
                }
            }
        }

        return $issues;
    }
}
