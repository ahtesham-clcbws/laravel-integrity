<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Formatters;

use Clcbws\LaravelIntegrity\Contracts\FormatterInterface;
use Clcbws\LaravelIntegrity\Engine\IssueRegistry;
use Clcbws\LaravelIntegrity\Engine\Severity;

final class FileLogFormatter implements FormatterInterface
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $format = 'text'
    ) {}

    public function format(IssueRegistry $registry): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $issues = $registry->getIssues();

        if ($this->format === 'json') {
            $formattedIssues = array_map(static function ($issue) {
                return [
                    'severity' => $issue->severity->value,
                    'message' => $issue->message,
                    'file' => $issue->file,
                    'line' => $issue->line,
                ];
            }, $issues);

            $logData = [
                'timestamp' => $timestamp,
                'passed' => !$registry->failed(),
                'duration_ms' => $registry->getDurationMs(),
                'issues' => $formattedIssues,
            ];

            file_put_contents(
                $this->filePath,
                json_encode($logData, JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND
            );
            return;
        }

        // Default 'text' format
        $logContent = "=== Integrity Check Run at [{$timestamp}] ===" . PHP_EOL;
        $logContent .= "Status: " . ($registry->failed() ? "FAILED" : "PASSED") . PHP_EOL;
        $logContent .= "Duration: " . round($registry->getDurationMs(), 2) . "ms" . PHP_EOL;

        if (empty($issues)) {
            $logContent .= "No issues found." . PHP_EOL;
        } else {
            foreach ($issues as $issue) {
                $location = $issue->line !== null
                    ? "{$issue->file}:{$issue->line}"
                    : $issue->file;

                $logContent .= sprintf(
                    "[%s] %s | %s | %s",
                    strtoupper($issue->severity->value),
                    $location,
                    $issue->message,
                    PHP_EOL
                );
            }
        }
        $logContent .= "===========================================" . PHP_EOL . PHP_EOL;

        file_put_contents($this->filePath, $logContent, FILE_APPEND);
    }
}
