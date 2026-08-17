<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Commands;

use Illuminate\Console\Command;
use Clcbws\LaravelIntegrity\Contracts\FixableInterface;
use Clcbws\LaravelIntegrity\Support\FileScanner;

final class IntegrityFixCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'integrity:fix
                            {--dirty : Fix issues only on git staged/modified files}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically fix repairable violations (strict_types, facade imports, unused imports).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Configure FileScanner with dirty flag state
        $scanner = $this->laravel->make(FileScanner::class);
        $scanner->setDirty((bool) $this->option('dirty'));

        $checksConfig = config('integrity.checks', []);
        $totalFixed = 0;

        foreach ($checksConfig as $checkClass) {
            if (!class_exists($checkClass)) {
                continue;
            }

            $check = $this->laravel->make($checkClass);

            if ($check instanceof FixableInterface) {
                $this->info("Running fixer for check: " . $checkClass);
                $fixedCount = $check->fix();
                $totalFixed += $fixedCount;

                if ($fixedCount > 0) {
                    $this->comment("Fixed {$fixedCount} instance(s).");
                }
            }
        }

        if ($totalFixed > 0) {
            $this->info("✔ Auto-remediation complete. Fixed {$totalFixed} issue(s) in total.");
        } else {
            $this->info("✔ No fixable issues found.");
        }

        return Command::SUCCESS;
    }
}
