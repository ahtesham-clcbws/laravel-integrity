<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Blade;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Illuminate\Support\Facades\Blade;

final class BladeCompileCheck implements CheckInterface
{
    public function __construct(private readonly FileScanner $scanner) {}

    public function key(): string
    {
        return 'blade-compile';
    }

    public function name(): string
    {
        return 'Blade Compile Check';
    }

    public function description(): string
    {
        return 'Compile all Blade template files to check for compilation syntax errors.';
    }

    public function isExpensive(): bool
    {
        return true;
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

            try {
                // Compile Blade to raw PHP code
                $compiledPhp = Blade::compileString($content);

                // Write to a temporary file for syntax checks
                $tempFile = tempnam(sys_get_temp_dir(), 'blade_compile_');
                if ($tempFile === false) {
                    continue;
                }

                file_put_contents($tempFile, $compiledPhp);

                // Check syntax of the compiled code
                exec(sprintf('php -l %s 2>&1', escapeshellarg($tempFile)), $output, $returnCode);
                unlink($tempFile);

                if ($returnCode !== 0) {
                    $errorMsg = !empty($output) ? implode(' ', $output) : 'Unknown compilation syntax error';
                    
                    // Simplify error log output
                    $errorMsg = preg_replace('/in .* on line \d+/', '', $errorMsg);

                    $issues[] = new Issue(
                        severity: Severity::High,
                        message: "Syntax error inside compiled Blade template: " . trim($errorMsg),
                        file: $file,
                        line: null,
                        snippet: null,
                        fixable: false,
                        context: [
                            'check_name' => $this->name(),
                            'file_path' => $file,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "Blade compiler error: " . $e->getMessage(),
                    file: $file,
                    line: null,
                    snippet: null,
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'file_path' => $file,
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
}
