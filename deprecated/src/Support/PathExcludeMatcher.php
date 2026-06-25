<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Single source of truth for exclude decisions across every code path that
 * scans files (`judgeScroll`, `judgeFiles`, `judgeFile`). The only entry
 * point that intentionally bypasses this is `judgePath` (the explicit
 * `--path` escape hatch).
 */
final class PathExcludeMatcher
{
    /**
     * Directories every scan should skip unless the caller explicitly
     * opts out. Kept here (not in the scanner / scroll manager) so every
     * exclude check uses the same baseline.
     */
    public const DEFAULT_EXCLUDES = [
        'vendor',
        'node_modules',
        'storage',
        '.git',
        'bootstrap/cache',
    ];

    /**
     * Convert a single exclude entry into a regex suitable for matching
     * against a file path. Supports `*` as a wildcard; all other regex
     * metacharacters are quoted literally.
     */
    public static function toRegex(string $excludePath): string
    {
        $quoted = preg_quote(rtrim($excludePath, '/'), '/');
        $expanded = str_replace('\*', '.*', $quoted);

        return '/' . $expanded . '/';
    }

    /**
     * Whether the given file path matches any of the exclude patterns.
     * Substring/regex match against the (typically absolute) file path,
     * so absolute exclude entries like `/abs/proj/tests` and relative
     * ones like `tests` both match `/abs/proj/tests/Foo.php`.
     *
     * @param  array<string>  $excludePaths
     */
    public static function matchesAny(string $filePath, array $excludePaths): bool
    {
        foreach ($excludePaths as $excludePath) {
            if (preg_match(self::toRegex($excludePath), $filePath) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authoritative exclude check used by every scanning entry point.
     * Combines the user's configured excludes with the always-on default
     * excludes (vendor, node_modules, etc.) so callers don't each have
     * to maintain their own copy of the defaults.
     *
     * @param  array<string>  $excludePaths  User-configured excludes (relative or absolute).
     */
    public static function shouldExclude(
        string $filePath,
        array $excludePaths,
        bool $applyDefaults = true,
    ): bool {
        if ($applyDefaults) {
            foreach (self::DEFAULT_EXCLUDES as $default) {
                if (str_contains($filePath, '/'.$default.'/')
                    || str_contains($filePath, '\\'.$default.'\\')) {
                    return true;
                }
            }
        }

        return self::matchesAny($filePath, $excludePaths);
    }
}
