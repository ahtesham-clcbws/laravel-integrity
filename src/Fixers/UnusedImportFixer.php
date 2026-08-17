<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Fixers;

use Clcbws\LaravelIntegrity\Engine\Issue;

final class UnusedImportFixer
{
    /**
     * Remove unused imports.
     *
     * @param array<int, Issue> $issues
     * @return int Number of files modified.
     */
    public function fix(array $issues): int
    {
        $fixedCount = 0;

        // Group issues by file
        $filesIssues = [];
        foreach ($issues as $issue) {
            $filesIssues[$issue->file][] = $issue;
        }

        foreach ($filesIssues as $file => $fileIssues) {
            if (!is_file($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Sort issues by start_pos descending to prevent index shifting
            usort($fileIssues, static function (Issue $a, Issue $b) {
                return ($b->context['start_pos'] ?? 0) <=> ($a->context['start_pos'] ?? 0);
            });

            $modified = false;
            foreach ($fileIssues as $issue) {
                $start = $issue->context['start_pos'] ?? null;
                $end = $issue->context['end_pos'] ?? null;

                if ($start === null || $end === null) {
                    continue;
                }

                $len = $end - $start + 1;
                
                // Verify the substring starts with 'use'
                $sub = substr($content, $start, 3);
                if ($sub === 'use') {
                    // Consume trailing newlines and spaces
                    while (isset($content[$start + $len]) && 
                           in_array($content[$start + $len], ["\r", "\n", " ", "\t"], true)
                    ) {
                        $len++;
                    }
                    
                    $content = substr_replace($content, '', $start, $len);
                    $modified = true;
                }
            }

            if ($modified) {
                // Atomic writing with validation
                $tempFile = $file . '.tmp';
                if (file_put_contents($tempFile, $content) !== false) {
                    exec(sprintf('php -l %s 2>&1', escapeshellarg($tempFile)), $output, $returnCode);

                    if ($returnCode === 0) {
                        rename($tempFile, $file);
                        $fixedCount += count($fileIssues);
                    } else {
                        unlink($tempFile);
                    }
                }
            }
        }

        return $fixedCount;
    }
}
