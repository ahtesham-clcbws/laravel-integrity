<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

final class IssueRegistry
{
    /**
     * @var array<string, CheckResult>
     */
    private array $results = [];

    /**
     * Add a CheckResult to the registry.
     */
    public function add(CheckResult $result): void
    {
        $this->results[$result->checkKey] = $result;
    }

    /**
     * Get all registered CheckResults.
     *
     * @return array<string, CheckResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get all aggregated issues.
     *
     * @return array<int, Issue>
     */
    public function getIssues(): array
    {
        $issues = [];
        foreach ($this->results as $result) {
            foreach ($result->issues as $issue) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * Check if any rules failed.
     */
    public function failed(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->passed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate total duration of all runs.
     */
    public function getDurationMs(): float
    {
        $duration = 0.0;
        foreach ($this->results as $result) {
            $duration += $result->durationMs;
        }

        return $duration;
    }
}
