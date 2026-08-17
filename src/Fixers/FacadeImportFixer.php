<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Fixers;

use Clcbws\LaravelIntegrity\Engine\Issue;

final class FacadeImportFixer
{
    /**
     * Fix root namespace facade calls.
     *
     * @param array<int, Issue> $issues
     * @return int Number of modifications applied.
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

            // Sort issues by start_pos descending to prevent index shifting bugs
            usort($fileIssues, static function (Issue $a, Issue $b) {
                return ($b->context['start_pos'] ?? 0) <=> ($a->context['start_pos'] ?? 0);
            });

            $modified = false;
            $facadesToImport = [];

            foreach ($fileIssues as $issue) {
                $start = $issue->context['start_pos'] ?? null;
                $end = $issue->context['end_pos'] ?? null;
                $facade = $issue->context['facade'] ?? null;

                if ($start === null || $end === null || $facade === null) {
                    continue;
                }

                // Verify the substring is indeed "\facade" or similar before replacing
                $len = $end - $start + 1;
                $sub = substr($content, $start, $len);
                if (str_starts_with($sub, '\\')) {
                    $content = substr_replace($content, $facade, $start, $len);
                    $facadesToImport[$facade] = true;
                    $modified = true;
                }
            }

            if ($modified) {
                // Add import statements for used facades if they are not already imported
                foreach (array_keys($facadesToImport) as $facade) {
                    $importStr = "use Illuminate\\Support\\Facades\\{$facade};";
                    if (str_contains($content, $importStr)) {
                        continue;
                    }

                    // Insert import statement
                    $content = $this->insertImport($content, $importStr);
                }

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

    /**
     * Insert import statement in PHP code.
     */
    private function insertImport(string $content, string $importStr): string
    {
        // 1. Insert after namespace declaration
        if (preg_match('/namespace\s+[a-zA-Z0-9_\\\\]+;/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            return substr($content, 0, $insertPos) . PHP_EOL . $importStr . substr($content, $insertPos);
        }

        // 2. Insert after declare statement
        if (preg_match('/declare\s*\([^)]+\)\s*;/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            return substr($content, 0, $insertPos) . PHP_EOL . PHP_EOL . $importStr . substr($content, $insertPos);
        }

        // 3. Fallback insert after open tag
        $pos = strpos($content, '<?php');
        if ($pos !== false) {
            $insertPos = $pos + 5;
            return substr($content, 0, $insertPos) . PHP_EOL . PHP_EOL . $importStr . PHP_EOL . substr($content, $insertPos);
        }

        return $importStr . PHP_EOL . $content;
    }
}
