<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Contracts;

interface FixableInterface
{
    /**
     * Auto-remediate identified code violations.
     *
     * @return int Count of modified/fixed instances.
     */
    public function fix(): int;
}
