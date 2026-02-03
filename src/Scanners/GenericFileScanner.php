<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scanners;

use JesseGall\CodeCommandments\Contracts\FileScanner;
use Symfony\Component\Finder\Finder;

/**
 * Generic file scanner using Symfony Finder.
 */
class GenericFileScanner implements FileScanner
{
    public function scan(string|array $path, array $extensions = [], array $excludePaths = []): iterable
    {
        $paths = is_array($path) ? $path : [$path];
        $validPaths = array_filter($paths, fn (string $p) => is_dir($p));

        if (empty($validPaths)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($validPaths);

        if (!empty($extensions)) {
            $patterns = array_map(fn (string $ext) => '*.' . ltrim($ext, '.'), $extensions);
            $finder->name($patterns);
        }

        foreach ($excludePaths as $excludePath) {
            $finder->notPath($excludePath);
        }

        // Exclude common directories
        $finder->exclude([
            'vendor',
            'node_modules',
            'storage',
            '.git',
            'bootstrap/cache',
        ]);

        foreach ($finder as $file) {
            yield $file;
        }
    }
}
