<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scanners;

use JesseGall\CodeCommandments\Contracts\FileScanner;
use JesseGall\CodeCommandments\Support\PathExcludeMatcher;
use Symfony\Component\Finder\Finder;

/**
 * Generic file scanner using Symfony Finder.
 */
class GenericFileScanner implements FileScanner
{
    public function scan(
        string|array $path,
        array $extensions = [],
        array $excludePaths = [],
        bool $honorDefaultExcludes = true,
    ): iterable {
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

        if ($honorDefaultExcludes) {
            // Performance: prune directory-shaped excludes at scan time so
            // Finder never descends into them. Pattern-shaped excludes
            // (globs, file paths) fall through to the authoritative
            // post-filter below.
            $directoryExcludes = [];

            foreach ($excludePaths as $excludePath) {
                $normalized = $this->normalizeExclude((string) $excludePath, $validPaths);

                if ($normalized === '' || ! $this->looksLikeDirectory($normalized)) {
                    continue;
                }

                $directoryExcludes[] = $normalized;
            }

            if ($directoryExcludes !== []) {
                $finder->exclude($directoryExcludes);
            }

            $finder->exclude(PathExcludeMatcher::DEFAULT_EXCLUDES);
        }

        foreach ($finder as $file) {
            // Authoritative check — same logic used by --git/--file paths.
            // Catches anything Finder didn't prune (absolute excludes that
            // didn't normalize, file/glob patterns, etc.).
            if ($honorDefaultExcludes) {
                $real = $file->getRealPath();

                if ($real !== false
                    && PathExcludeMatcher::shouldExclude($real, $excludePaths)) {
                    continue;
                }
            }

            yield $file;
        }
    }

    /**
     * Convert an absolute exclude entry to a relative path against one of the
     * scan roots. Symfony Finder matches `notPath`/`exclude` against paths
     * relative to the search root, so absolute entries (e.g. `__DIR__.'/tests/'`)
     * never match without normalization.
     *
     * Returns the entry unchanged when it's already relative or lives outside
     * every scan root. Returns an empty string when the entry IS one of the
     * scan roots — there's nothing to exclude in that case.
     *
     * @param  array<string>  $roots
     */
    private function normalizeExclude(string $excludePath, array $roots): string
    {
        $excludePath = rtrim($excludePath, '/\\');

        if ($excludePath === '' || ! $this->isAbsolute($excludePath)) {
            return $excludePath;
        }

        foreach ($roots as $root) {
            $root = rtrim($root, '/\\');

            if ($excludePath === $root) {
                return '';
            }

            if (str_starts_with($excludePath, $root . '/')
                || str_starts_with($excludePath, $root . '\\')) {
                return substr($excludePath, strlen($root) + 1);
            }
        }

        return $excludePath;
    }

    private function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        // Windows drive letter (C:\, D:/...).
        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private function looksLikeDirectory(string $relative): bool
    {
        if (str_contains($relative, '*')) {
            return false;
        }

        $basename = basename($relative);

        // A `.` in the basename (`Console/Kernel.php`, `*.d.ts`) signals a
        // file pattern. Bare names like `tests` or `app/Console` are dirs.
        return ! str_contains($basename, '.');
    }
}
