<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

class ProjectDetector
{
    private const SKIP_DIRS = ['.git', 'vendor', 'node_modules'];

    /**
     * Scan the base path and its immediate subdirectories for projects.
     *
     * @return DetectedProject[]
     */
    public function detect(string $basePath): array
    {
        $projects = [];

        $rootProject = $this->analyzeDirectory($basePath);

        if ($rootProject->hasPhp || $rootProject->hasFrontend) {
            $projects[] = $rootProject;
        }

        foreach ($this->getSubdirectories($basePath) as $dir) {
            $project = $this->analyzeDirectory($dir);

            if ($project->hasPhp || $project->hasFrontend) {
                $projects[] = $project;
            }
        }

        return $projects;
    }

    private function analyzeDirectory(string $path): DetectedProject
    {
        $hasComposer = file_exists($path . '/composer.json');
        $hasPackageJson = file_exists($path . '/package.json');

        $phpSourcePath = null;
        $hasPhp = false;

        if ($hasComposer) {
            $phpSourcePath = $this->detectPhpSourcePath($path);
            $hasPhp = $phpSourcePath !== null;
        }

        $frontendSourcePath = null;
        $hasFrontend = false;

        if ($hasPackageJson) {
            $frontendSourcePath = $this->detectFrontendSourcePath($path);
            $hasFrontend = $frontendSourcePath !== null;
        }

        return new DetectedProject(
            name: basename($path),
            path: $path,
            hasPhp: $hasPhp,
            hasFrontend: $hasFrontend,
            phpSourcePath: $phpSourcePath,
            frontendSourcePath: $frontendSourcePath,
        );
    }

    private function detectPhpSourcePath(string $path): ?string
    {
        if (is_dir($path . '/app') && $this->hasFilesWithExtension($path . '/app', 'php')) {
            return 'app';
        }

        if (is_dir($path . '/src') && $this->hasFilesWithExtension($path . '/src', 'php')) {
            return 'src';
        }

        return null;
    }

    private function detectFrontendSourcePath(string $path): ?string
    {
        $frontendExtensions = ['vue', 'ts', 'tsx'];

        if (is_dir($path . '/resources/js') && $this->hasFilesWithExtensions($path . '/resources/js', $frontendExtensions)) {
            return 'resources/js';
        }

        if (is_dir($path . '/src') && $this->hasFilesWithExtensions($path . '/src', $frontendExtensions)) {
            return 'src';
        }

        if ($this->hasFilesWithExtensions($path, $frontendExtensions)) {
            return '.';
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getSubdirectories(string $basePath): array
    {
        $dirs = [];

        $entries = scandir($basePath);

        if ($entries === false) {
            return $dirs;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            if (in_array($entry, self::SKIP_DIRS, true)) {
                continue;
            }

            $fullPath = $basePath . '/' . $entry;

            if (is_dir($fullPath)) {
                $dirs[] = $fullPath;
            }
        }

        return $dirs;
    }

    private function hasFilesWithExtension(string $dir, string $extension): bool
    {
        return $this->hasFilesWithExtensions($dir, [$extension]);
    }

    /**
     * @param string[] $extensions
     */
    private function hasFilesWithExtensions(string $dir, array $extensions): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, '/node_modules/') || str_contains($path, '/vendor/')) {
                continue;
            }

            if (in_array($file->getExtension(), $extensions, true)) {
                return true;
            }
        }

        return false;
    }
}
