<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Routing;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Clcbws\LaravelIntegrity\Engine\ContainerReflectionEngine;
use ReflectionClass;

final class DanglingControllerActionCheck implements CheckInterface
{
    public function __construct(private readonly ContainerReflectionEngine $reflection) {}

    public function key(): string
    {
        return 'dangling-controller';
    }

    public function name(): string
    {
        return 'Dangling Controller Actions';
    }

    public function description(): string
    {
        return 'Verify that all routes target callable controller actions and valid methods.';
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

            // Skip closure routes
            if ($uses instanceof \Closure || $uses === null) {
                continue;
            }

            $controllerClass = null;
            $methodName = '__invoke';

            if (is_string($uses)) {
                if (str_contains($uses, '@')) {
                    $parts = explode('@', $uses);
                    $controllerClass = $parts[0];
                    $methodName = $parts[1];
                } else {
                    $controllerClass = $uses;
                }
            } elseif (is_array($uses) && count($uses) === 2) {
                $controllerClass = is_object($uses[0]) ? get_class($uses[0]) : $uses[0];
                $methodName = $uses[1];
            }

            if ($controllerClass === null) {
                continue;
            }

            // Exclude framework internal fallback routes
            if (str_contains($controllerClass, 'FallbackPlaceholderController')) {
                continue;
            }

            $uri = $route->uri();
            $methods = implode('|', $route->methods());

            if (!class_exists($controllerClass)) {
                $issues[] = new Issue(
                    severity: Severity::Critical,
                    message: "Route [{$methods}] '{$uri}' targets non-existent controller class '{$controllerClass}'.",
                    file: 'routes/web.php',
                    line: null,
                    snippet: "controller: {$controllerClass}",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'uri' => $uri,
                        'controller' => $controllerClass,
                    ]
                );
                continue;
            }

            $ref = new ReflectionClass($controllerClass);
            $hasMethod = $ref->hasMethod($methodName);
            $isPublic = false;

            if ($hasMethod) {
                $method = $ref->getMethod($methodName);
                $isPublic = $method->isPublic();
            }

            if (!$hasMethod || !$isPublic) {
                $msg = !$hasMethod
                    ? "Route [{$methods}] '{$uri}' targets missing method '{$methodName}()' on '{$controllerClass}'."
                    : "Route [{$methods}] '{$uri}' targets method '{$methodName}()' on '{$controllerClass}' but is not public.";

                $issues[] = new Issue(
                    severity: Severity::Critical,
                    message: $msg,
                    file: 'routes/web.php',
                    line: null,
                    snippet: "method: {$methodName}",
                    fixable: false,
                    context: [
                        'check_name' => $this->name(),
                        'uri' => $uri,
                        'controller' => $controllerClass,
                        'method' => $methodName,
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
