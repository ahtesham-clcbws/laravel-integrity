<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Livewire;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\ComponentRegistryBridge;

final class ComponentManifestCheck implements CheckInterface
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly ComponentRegistryBridge $livewire
    ) {}

    public function key(): string
    {
        return 'livewire-manifest';
    }

    public function name(): string
    {
        return 'Livewire Component Manifest';
    }

    public function description(): string
    {
        return 'Identify Livewire calls (<livewire:...) referencing unregistered components.';
    }

    public function isExpensive(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        // If Livewire is not installed, skip the check
        if (!$this->livewire->isInstalled()) {
            return new CheckResult(true, $this->key(), [], 0.0);
        }

        $files = $this->scanner->scanBladeFiles();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // 1. Scan for <livewire:alias tags
            if (preg_match_all('/<livewire:([a-zA-Z0-9\-._:]+)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $alias = $match[0];
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;

                    if ($this->livewire->getClass($alias) === null) {
                        $issues[] = new Issue(
                            severity: Severity::Critical,
                            message: "Livewire component alias '{$alias}' does not resolve to any registered class.",
                            file: $file,
                            line: $line,
                            snippet: "<livewire:{$alias} />",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'alias' => $alias,
                            ]
                        );
                    }
                }
            }

            // 2. Scan for @livewire('alias') directives
            if (preg_match_all('/@livewire\(\s*[\'"]([a-zA-Z0-9\-._:]+)[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $alias = $match[0];
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), PHP_EOL) + 1;

                    if ($this->livewire->getClass($alias) === null) {
                        $issues[] = new Issue(
                            severity: Severity::Critical,
                            message: "Livewire component alias '{$alias}' does not resolve to any registered class.",
                            file: $file,
                            line: $line,
                            snippet: "@livewire('{$alias}')",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'file_path' => $file,
                                'alias' => $alias,
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
