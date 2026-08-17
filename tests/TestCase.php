<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Clcbws\LaravelIntegrity\IntegrityServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IntegrityServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Define temporary folders for test file scanner
        $app['config']->set('integrity.paths', [
            'app' => __DIR__ . '/Fixtures/app',
            'views' => __DIR__ . '/Fixtures/resources/views',
            'routes' => __DIR__ . '/Fixtures/routes',
            'database' => __DIR__ . '/Fixtures/database',
            'config' => __DIR__ . '/Fixtures/config',
        ]);

        $app['config']->set('integrity.ignore', [
            'routes' => [],
            'views' => [],
            'paths' => [],
        ]);

        $app['config']->set('integrity.baseline_file', __DIR__ . '/Fixtures/.integrity-baseline.json');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create fixtures folder if missing
        $fixturesDir = __DIR__ . '/Fixtures';
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup fixtures folder
        $this->cleanupDirectory(__DIR__ . '/Fixtures');
        parent::tearDown();
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
