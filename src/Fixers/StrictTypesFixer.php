<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Fixers;

final class StrictTypesFixer
{
    /**
     * Fix the missing strict_types declarations.
     *
     * @param array<int, \Clcbws\LaravelIntegrity\Engine\Issue> $issues
     * @return int Number of files successfully fixed.
     */
    public function fix(array $issues): int
    {
        $fixedCount = 0;

        foreach ($issues as $issue) {
            $file = $issue->file;
            if (!is_file($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Safe insert after open tag
            $pos = strpos($content, '<?php');
            if ($pos === false) {
                continue;
            }

            $insertPos = $pos + 5;
            $header = substr($content, 0, $insertPos);
            $rest = substr($content, $insertPos);

            // Clean leading whitespaces from rest to avoid extra newlines
            $rest = ltrim($rest);

            $newContent = $header . PHP_EOL . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL . $rest;

            // Atomic writing with validation
            $tempFile = $file . '.tmp';
            if (file_put_contents($tempFile, $newContent) === false) {
                continue;
            }

            // Syntax check validation
            exec(sprintf('php -l %s 2>&1', escapeshellarg($tempFile)), $output, $returnCode);

            if ($returnCode === 0) {
                rename($tempFile, $file);
                $fixedCount++;
            } else {
                unlink($tempFile);
            }
        }

        return $fixedCount;
    }
}
