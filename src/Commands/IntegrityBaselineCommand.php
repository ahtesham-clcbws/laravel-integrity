<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Commands;

use Illuminate\Console\Command;
use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;

final class IntegrityBaselineCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'integrity:baseline';

    /**
     * The console command description.
     */
    protected $description = 'Snapshot existing codebase issues into the baseline configuration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checksConfig = config('integrity.checks', []);
        $registry = new IssueRegistry();

        $this->info("Scanning codebase to generate baseline configurations...");

        foreach ($checksConfig as $checkClass) {
            if (!class_exists($checkClass)) {
                continue;
            }

            /** @var CheckInterface $check */
            $check = $this->laravel->make($checkClass);

            if ($check instanceof CheckInterface) {
                // We run all checks for baseline generation, including expensive ones
                $result = $check->run();
                $registry->add($result);
            }
        }

        $baselineFile = config('integrity.baseline_file', base_path('.integrity-baseline.json'));
        $issues = $registry->getIssues();

        $baselineData = [];
        foreach ($issues as $issue) {
            $baselineData[] = [
                'file' => str_replace(base_path() . '/', '', $issue->file),
                'message' => $issue->message,
                'severity' => $issue->severity->value,
            ];
        }

        file_put_contents(
            $baselineFile,
            (string) json_encode($baselineData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info(sprintf(
            "✔ Successfully baselined %d issue(s) into [%s]",
            count($issues),
            basename($baselineFile)
        ));

        return Command::SUCCESS;
    }
}
