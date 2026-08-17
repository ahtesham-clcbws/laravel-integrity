<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Formatters;

use Clcbws\LaravelIntegrity\Contracts\FormatterInterface;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;
use Symfony\Component\Console\Output\OutputInterface;

final class HtmlFormatter implements FormatterInterface
{
    public function __construct(private readonly OutputInterface $output) {}

    public function format(IssueRegistry $registry): void
    {
        $issues = $registry->getIssues();
        $passed = !$registry->failed();
        $duration = $registry->getDurationMs();
        
        $statusColor = $passed ? "#10b981" : "#ef4444";
        $statusText = $passed ? "Passed" : "Failed";
        
        $html = "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Laravel Integrity Report</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        h1 { border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        .summary { display: flex; gap: 2rem; margin-bottom: 2rem; padding: 1rem; background: #f9fafb; border-radius: 6px; }
        .summary-item { display: flex; flex-direction: column; }
        .label { font-size: 0.875rem; color: #6b7280; font-weight: 600; text-transform: uppercase; }
        .value { font-size: 1.5rem; font-weight: 700; }
        .issue { border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1rem; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; }
        .issue-title { font-weight: 600; font-size: 1.125rem; margin-bottom: 0.5rem; }
        .issue-meta { font-family: ui-monospace, monospace; font-size: 0.875rem; color: #4b5563; background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>Laravel Integrity Audit Report</h1>
        <div class=\"summary\">
            <div class=\"summary-item\">
                <span class=\"label\">Status</span>
                <span class=\"value\" style=\"color: {$statusColor};\">{$statusText}</span>
            </div>
            <div class=\"summary-item\">
                <span class=\"label\">Duration</span>
                <span class=\"value\">{$duration} ms</span>
            </div>
            <div class=\"summary-item\">
                <span class=\"label\">Total Issues</span>
                <span class=\"value\">" . count($issues) . "</span>
            </div>
        </div>
        
        <h2>Issues</h2>";
        
        if (empty($issues)) {
            $html .= "<p>No issues found! Your codebase is clean.</p>";
        } else {
            foreach ($issues as $issue) {
                $html .= "
        <div class=\"issue\">
            <div class=\"issue-title\">{$issue->message}</div>
            <div class=\"issue-meta\">{$issue->file}:{$issue->line}</div>
        </div>";
            }
        }
        
        $html .= "
    </div>
</body>
</html>";

        // Assuming output to file or console based on configuration
        // The command currently passes Console OutputInterface. 
        // We will write the HTML to storage/logs/integrity-report.html if requested, 
        // but since this is a formatter for output, we just writeln.
        $this->output->writeln($html);
    }
}
