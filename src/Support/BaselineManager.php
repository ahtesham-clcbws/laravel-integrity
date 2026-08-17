<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Support;

use Clcbws\LaravelIntegrity\Engine\Issue;

final class BaselineManager
{
    /**
     * @var array<int, array{file: string, message: string}>
     */
    private array $baseline = [];

    private bool $loaded = false;

    public function __construct() {}

    /**
     * Determine if an issue is recorded in the baseline.
     */
    public function has(Issue $issue): bool
    {
        $this->ensureLoaded();

        $relativeFile = str_replace(base_path() . '/', '', $issue->file);

        foreach ($this->baseline as $entry) {
            if ($entry['file'] === $relativeFile && $entry['message'] === $issue->message) {
                return true;
            }
        }

        return false;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $baselineFile = config('integrity.baseline_file', base_path('.integrity-baseline.json'));

        if (file_exists($baselineFile)) {
            try {
                $content = file_get_contents($baselineFile);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $this->baseline = $decoded;
                    }
                }
            } catch (\Throwable) {
                // Fail silently and assume empty baseline
            }
        }

        $this->loaded = true;
    }
}
