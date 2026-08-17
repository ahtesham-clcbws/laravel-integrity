<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Routing;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\ContainerReflectionEngine;

final class RouteSerializationCheck implements CheckInterface
{
    public function __construct(private readonly ContainerReflectionEngine $reflection) {}

    public function key(): string
    {
        return 'route-serialization';
    }

    public function name(): string
    {
        return 'Route Serialization Check';
    }

    public function description(): string
    {
        return 'Assert that no routes contain closures, which prevents route caching (route:cache).';
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];
        $routes = $this->reflection->getRoutes();

        foreach ($routes as $route) {
            $action = $route->getAction();
            $uses = $action['uses'] ?? null;

            if ($uses instanceof \Closure) {
                $uri = $route->uri();
                $methods = implode('|', $route->methods());

                $issues[] = new Issue(
                    severity: Severity::High,
                    message: "Route [{$methods}] '{$uri}' targets a Closure. Closures cannot be serialized for caching.",
                    file: 'routes/web.php', // Fallback identifier
                    line: null,
                    snippet: "Route::get('{$uri}', function() ...)",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'uri' => $uri,
                        'methods' => $route->methods(),
                    ]
                );
            }
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }
}
