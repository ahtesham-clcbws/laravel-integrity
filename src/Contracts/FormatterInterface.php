<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Contracts;

use Clcbws\LaravelIntegrity\Engine\IssueRegistry;

interface FormatterInterface
{
    /**
     * Format and output the registered issues.
     */
    public function format(IssueRegistry $registry): void;
}
