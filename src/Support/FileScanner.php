<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileScanner
{
    /**
     * @var array<string, string>
     */
    private array $paths;

    /**
     * @var array<string, array<int, string>>
     */
    private array $ignore;

    private bool $dirty = false;

    public function __construct()
    {
        $this->paths = config('integrity.paths', []);
        $this->ignore = config('integrity.ignore', []);
    }

    /**
     * Enable or disable Git staged/modified scanning.
     */
    public function setDirty(bool $dirty): void
    {
        $this->dirty = $dirty;
    }

    /**
     * Scan configured paths for PHP files, excluding ignores.
     *
     * @return array<int, string> List of absolute file paths.
     */
    public function scanPhpFiles(): array
    {
        if ($this->dirty) {
            return $this->scanGitDirtyFiles('php');
        }

        $files = [];
        $scanPaths = [
            $this->paths['app'] ?? null,
            $this->paths['routes'] ?? null,
            $this->paths['database'] ?? null,
            $this->paths['config'] ?? null,
        ];

        foreach (array_filter($scanPaths) as $path) {
            if (!is_dir($path)) {
                if (is_file($path) && !$this->isIgnored($path)) {
                    $files[] = $path;
                }
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $filePath = $file->getRealPath();
                    if ($filePath !== false && !$this->isIgnored($filePath)) {
                        $files[] = $filePath;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Scan configured paths for Blade files, excluding ignores.
     *
     * @return array<int, string> List of absolute Blade file paths.
     */
    public function scanBladeFiles(): array
    {
        if ($this->dirty) {
            return $this->scanGitDirtyFiles('blade');
        }

        $files = [];
        $viewsPath = $this->paths['views'] ?? null;

        if ($viewsPath && is_dir($viewsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($viewsPath)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $filePath = $file->getRealPath();
                    if ($filePath !== false && !$this->isIgnored($filePath, 'views')) {
                        $files[] = $filePath;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Scan for modified, staged, or untracked files in Git.
     *
     * @return array<int, string> List of absolute paths.
     */
    private function scanGitDirtyFiles(string $type): array
    {
        $files = [];
        exec('git status --porcelain', $output, $returnCode);

        if ($returnCode !== 0) {
            return [];
        }

        foreach ($output as $line) {
            $relativeFile = trim(substr($line, 3));
            $filePath = base_path($relativeFile);

            if (!is_file($filePath)) {
                continue;
            }

            // Verify file extension
            if ($type === 'php' && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            if ($type === 'blade' && !str_ends_with($filePath, '.blade.php')) {
                continue;
            }

            // Verify the file lies within a scanned folder config
            if (!$this->isWithinScanPaths($filePath)) {
                continue;
            }

            if ($this->isIgnored($filePath, $type === 'blade' ? 'views' : 'paths')) {
                continue;
            }

            $files[] = $filePath;
        }

        return $files;
    }

    /**
     * Verify if a file is within configured scanning directories.
     */
    private function isWithinScanPaths(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($this->paths as $scanDir) {
            if ($scanDir) {
                $normalizedDir = str_replace('\\', '/', $scanDir);
                if (str_starts_with($normalized, $normalizedDir)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a file path is ignored.
     */
    private function isIgnored(string $path, string $type = 'paths'): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $patterns = $this->ignore[$type] ?? [];

        foreach ($patterns as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);
            
            if (str_contains($normalizedPattern, '*')) {
                $matchPattern = '*' . trim($normalizedPattern, '/');
                if (fnmatch($matchPattern, $normalizedPath)) {
                    return true;
                }
            } elseif (str_contains($normalizedPath, $normalizedPattern)) {
                return true;
            }
        }

        return false;
    }
}
