<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

final class Issue
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly Severity $severity,
        public readonly string $message,
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly ?string $snippet = null,
        public readonly bool $fixable = false,
        public readonly array $context = []
    ) {}
}
