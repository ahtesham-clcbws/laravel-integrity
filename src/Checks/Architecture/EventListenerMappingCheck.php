<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Checks\Architecture;

use Clcbws\LaravelIntegrity\Contracts\CheckInterface;
use Clcbws\LaravelIntegrity\Engine\CheckResult;
use Clcbws\LaravelIntegrity\Engine\Issue;
use Clcbws\LaravelIntegrity\Engine\Severity;
use Illuminate\Support\Facades\Event;
use ReflectionClass;

final class EventListenerMappingCheck implements CheckInterface
{
    public function key(): string
    {
        return 'event-listener-mapping';
    }

    public function name(): string
    {
        return 'Event Listener Mapping Check';
    }

    public function description(): string
    {
        return 'Verify that registered event listeners map to callable classes and methods.';
    }

    public function isExpensive(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $issues = [];

        try {
            $dispatcher = Event::getFacadeRoot();
            
            // Get all raw listeners in dispatcher
            $rawListeners = [];
            if (method_exists($dispatcher, 'getRawListeners')) {
                $rawListeners = $dispatcher->getRawListeners();
            }

            foreach ($rawListeners as $event => $listeners) {
                foreach ($listeners as $listener) {
                    // Resolve the listener target class and method
                    $listenerClass = null;
                    $methodName = 'handle';

                    if ($listener instanceof \Closure) {
                        continue;
                    }

                    if (is_string($listener)) {
                        // Class@method format
                        if (str_contains($listener, '@')) {
                            $parts = explode('@', $listener);
                            $listenerClass = $parts[0];
                            $methodName = $parts[1];
                        } else {
                            $listenerClass = $listener;
                        }
                    } elseif (is_array($listener) && count($listener) === 2) {
                        $listenerClass = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
                        $methodName = $listener[1];
                    }

                    if ($listenerClass === null || !is_string($listenerClass)) {
                        continue;
                    }

                    // Skip dynamic closure listeners wrapped in string formats
                    if (str_starts_with($listenerClass, 'Closure') || str_contains($listenerClass, 'anonymous')) {
                        continue;
                    }

                    if (!class_exists($listenerClass)) {
                        $issues[] = new Issue(
                            severity: Severity::High,
                            message: "Listener class '{$listenerClass}' registered for event '{$event}' does not exist on disk.",
                            file: 'app/Providers/AppServiceProvider.php', // Fallback config location
                            line: null,
                            snippet: "listener: {$listenerClass}",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'event' => $event,
                                'listener' => $listenerClass,
                            ]
                        );
                        continue;
                    }

                    $ref = new ReflectionClass($listenerClass);
                    $hasMethod = $ref->hasMethod($methodName);
                    $isPublic = false;

                    if ($hasMethod) {
                        $method = $ref->getMethod($methodName);
                        $isPublic = $method->isPublic();
                    }

                    if (!$hasMethod || !$isPublic) {
                        $msg = !$hasMethod
                            ? "Listener class '{$listenerClass}' is missing the method '{$methodName}()' registered for event '{$event}'."
                            : "Listener class '{$listenerClass}' has method '{$methodName}()' for event '{$event}' but it is not public.";

                        $issues[] = new Issue(
                            severity: Severity::High,
                            message: $msg,
                            file: 'app/Providers/AppServiceProvider.php',
                            line: null,
                            snippet: "method: {$methodName}",
                            fixable: false,
                            context: [
                                'check_name' => $this->name(),
                                'event' => $event,
                                'listener' => $listenerClass,
                                'method' => $methodName,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $issues[] = new Issue(
                severity: Severity::Critical,
                message: "Event dispatcher reflection failed: " . $e->getMessage(),
                file: 'app/Providers',
                line: null,
                snippet: null,
                fixable: false,
                context: [
                    'check_name' => $this->name(),
                    'error' => $e->getMessage(),
                ]
            );
        }

        return new CheckResult(
            passed: empty($issues),
            checkKey: $this->key(),
            issues: $issues,
            durationMs: (microtime(true) - $start) * 1000
        );
    }
}
