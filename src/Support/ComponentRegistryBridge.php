<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Support;

final class ComponentRegistryBridge
{
    /**
     * Check if Livewire is installed and loaded.
     */
    public function isInstalled(): bool
    {
        return class_exists(\Livewire\Livewire::class);
    }

    /**
     * Get the Livewire component class name for the given alias.
     */
    public function getClass(string $alias): ?string
    {
        if (!$this->isInstalled()) {
            return null;
        }

        try {
            if (class_exists(\Livewire\Mechanisms\ComponentRegistry::class)) {
                return app(\Livewire\Mechanisms\ComponentRegistry::class)->getClass($alias);
            }
            return \Livewire\Livewire::getClass($alias);
        } catch (\Throwable) {
            return null;
        }
    }
}
