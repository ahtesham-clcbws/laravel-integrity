<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Contracts;

use Clcbws\LaravelIntegrity\Engine\CheckResult;

interface CheckInterface
{
    /**
     * Get unique key identifier for the check.
     */
    public function key(): string;

    /**
     * Get human-readable name of the check.
     */
    public function name(): string;

    /**
     * Get description of what the check verifies.
     */
    public function description(): string;

    /**
     * Indicate if the check is resource-intensive/slow.
     */
    public function isExpensive(): bool;

    /**
     * Run the integrity check rules.
     */
    public function run(): CheckResult;
}
