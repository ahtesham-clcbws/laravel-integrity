<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IntegrityPrReviewCommand extends Command
{
    protected $signature = "integrity:pr-review {report-file} {--repo=} {--pr=}";
    
    protected $description = "Reads a JSON integrity report and posts inline review comments to a GitHub Pull Request.";

    public function handle(): int
    {
        $reportFile = $this->argument("report-file");
        if (!file_exists($reportFile)) {
            $this->error("Report file not found.");
            return Command::FAILURE;
        }

        $token = getenv("GITHUB_TOKEN");
        if (!$token) {
            $this->error("GITHUB_TOKEN environment variable is not set.");
            return Command::FAILURE;
        }

        $repo = $this->option("repo") ?: getenv("GITHUB_REPOSITORY");
        $pr = $this->option("pr") ?: $this->getPrNumberFromEvent();

        if (!$repo || !$pr) {
            $this->error("Repository and PR number must be provided or available via GitHub Actions environment.");
            return Command::FAILURE;
        }

        $report = json_decode(file_get_contents($reportFile), true);
        if (!$report || !isset($report["issues"])) {
            $this->error("Invalid report format.");
            return Command::FAILURE;
        }

        $issues = $report["issues"];
        if (empty($issues)) {
            $this->info("No issues found. Nothing to report.");
            return Command::SUCCESS;
        }

        // We would map issues to GitHub review comments here
        $comments = [];
        foreach ($issues as $issue) {
            if ($issue["line"] > 0) {
                // Determine relative path
                $relativePath = str_replace(base_path() . "/", "", $issue["file"]);
                $comments[] = [
                    "path" => $relativePath,
                    "line" => $issue["line"],
                    "side" => "RIGHT",
                    "body" => "**Laravel Integrity (`{$issue["severity"]}`)**: {$issue["message"]}"
                ];
            }
        }

        if (empty($comments)) {
            $this->info("No line-specific issues to report.");
            return Command::SUCCESS;
        }

        // Send to GitHub API
        $url = "https://api.github.com/repos/{$repo}/pulls/{$pr}/reviews";
        
        $response = Http::withToken($token)
            ->withHeaders(["Accept" => "application/vnd.github.v3+json"])
            ->post($url, [
                "event" => "COMMENT",
                "comments" => $comments
            ]);

        if ($response->successful()) {
            $this->info("Successfully posted review to PR #{$pr}.");
            return Command::SUCCESS;
        }

        $this->error("Failed to post review: " . $response->body());
        return Command::FAILURE;
    }

    private function getPrNumberFromEvent(): ?string
    {
        $eventPath = getenv("GITHUB_EVENT_PATH");
        if ($eventPath && file_exists($eventPath)) {
            $event = json_decode(file_get_contents($eventPath), true);
            return (string) ($event["pull_request"]["number"] ?? "");
        }
        return null;
    }
}
