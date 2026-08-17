<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

final class CheckResult
{
    /**
     * @param array<int, Issue> $issues
     */
    public function __construct(
        public readonly bool $passed,
        public readonly string $checkKey,
        public readonly array $issues = [],
        public readonly float $durationMs = 0.0
    ) {}
}
