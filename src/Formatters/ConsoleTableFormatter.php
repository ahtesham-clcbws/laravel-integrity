<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Formatters;

use Clcbws\LaravelIntegrity\Contracts\FormatterInterface;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

final class ConsoleTableFormatter implements FormatterInterface
{
    public function __construct(private readonly OutputInterface $output) {}

    public function format(IssueRegistry $registry): void
    {
        $issues = $registry->getIssues();

        if (empty($issues)) {
            $this->output->writeln('<info>✔ All integrity checks passed successfully.</info>');
            return;
        }

        $table = new Table($this->output);
        $table->setHeaders(['Severity', 'Check', 'Location', 'Message']);

        foreach ($issues as $issue) {
            $severityColor = match ($issue->severity) {
                Severity::Critical => 'red;options=bold',
                Severity::High => 'red',
                Severity::Medium => 'yellow',
                Severity::Low => 'cyan',
            };

            $location = $issue->line !== null
                ? basename($issue->file) . ':' . $issue->line
                : basename($issue->file);

            // Fetch a truncated display name for checks if FQN
            $checkName = $issue->context['check_name'] ?? 'Integrity Check';

            $table->addRow([
                sprintf('<fg=%s>%s</>', $severityColor, strtoupper($issue->severity->value)),
                $checkName,
                $location,
                $issue->message,
            ]);
        }

        $table->render();

        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '<error>✘ Found %d issue(s) during integrity scan.</error>',
            count($issues)
        ));
    }
}
