<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Commands;

use Illuminate\Console\Command;
use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;
use Clcbws\LaravelIntegrity\Support\FileScanner;
use Clcbws\LaravelIntegrity\Support\BaselineManager;
use Clcbws\LaravelIntegrity\Formatters\ConsoleTableFormatter;
use Clcbws\LaravelIntegrity\Formatters\JsonFormatter;
use Clcbws\LaravelIntegrity\Formatters\FileLogFormatter;

final class IntegrityAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'integrity:check
                            {--strict : Fail on Medium, High, or Critical severity issues}
                            {--dirty : Run checks only on git staged/modified files}
                            {--format=text : Output format (text or json)}
                            {--only= : Filter checks by category (comma-separated list)}
                            {--full : Include slow/expensive checks}';

    /**
     * The console command description.
     */
    protected $description = 'Run static analysis, AST code remediation checks, and post-deploy reflection rules.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Configure FileScanner with dirty flag state
        $scanner = $this->laravel->make(FileScanner::class);
        $scanner->setDirty((bool) $this->option('dirty'));

        // 2. Instantiate BaselineManager
        $baseline = $this->laravel->make(BaselineManager::class);

        $checksConfig = config('integrity.checks', []);
        $registry = new IssueRegistry();

        $onlyCategories = $this->option('only')
            ? explode(',', (string) $this->option('only'))
            : [];

        $includeExpensive = (bool) $this->option('full');

        foreach ($checksConfig as $checkClass) {
            if (!class_exists($checkClass)) {
                continue;
            }

            /** @var CheckInterface $check */
            $check = $this->laravel->make($checkClass);

            if (!$check instanceof CheckInterface) {
                continue;
            }

            // Exclude expensive checks unless --full is passed
            if ($check->isExpensive() && !$includeExpensive) {
                continue;
            }

            // Filter checks by category if requested
            if (!empty($onlyCategories)) {
                $matchesCategory = false;
                foreach ($onlyCategories as $category) {
                    if (str_contains(strtolower($check->key()), strtolower($category))) {
                        $matchesCategory = true;
                        break;
                    }
                }
                if (!$matchesCategory) {
                    continue;
                }
            }

            // Execute the check rule
            $result = $check->run();

            // Filter out baseline issues
            $filteredIssues = [];
            foreach ($result->issues as $issue) {
                if (!$baseline->has($issue)) {
                    $filteredIssues[] = $issue;
                }
            }

            $filteredResult = new CheckResult(
                passed: empty($filteredIssues),
                checkKey: $result->checkKey,
                issues: $filteredIssues,
                durationMs: $result->durationMs
            );

            $registry->add($filteredResult);
        }

        // Output formatting
        $format = $this->option('format') === 'json' ? 'json' : 'text';

        if ($format === 'json') {
            $formatter = new JsonFormatter($this->output);
        } else {
            $formatter = new ConsoleTableFormatter($this->output);
        }

        $formatter->format($registry);

        // File logging
        if (config('integrity.logging.enabled', true)) {
            $logFile = config('integrity.logging.file', storage_path('logs/integrity.log'));
            $logFormat = config('integrity.logging.format', 'text');
            $logFormatter = new FileLogFormatter($logFile, $logFormat);
            $logFormatter->format($registry);
        }

        // Return exit codes
        if ($registry->failed() && $this->option('strict')) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
