<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Formatters;

use Clcbws\LaravelIntegrity\Contracts\FormatterInterface;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;
use Symfony\Component\Console\Output\OutputInterface;

final class JsonFormatter implements FormatterInterface
{
    public function __construct(private readonly OutputInterface $output) {}

    public function format(IssueRegistry $registry): void
    {
        $issues = array_map(static function ($issue) {
            return [
                'severity' => $issue->severity->value,
                'message' => $issue->message,
                'file' => $issue->file,
                'line' => $issue->line,
                'snippet' => $issue->snippet,
                'fixable' => $issue->fixable,
                'context' => $issue->context,
            ];
        }, $registry->getIssues());

        $data = [
            'passed' => !$registry->failed(),
            'duration_ms' => $registry->getDurationMs(),
            'issues' => $issues,
        ];

        $this->output->writeln((string) json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
    }
}
