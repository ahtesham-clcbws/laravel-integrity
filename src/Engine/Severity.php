<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
