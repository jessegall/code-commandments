<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Turns scroll `exclude` entries into PCRE patterns and tests file paths
 * against them. Shared between GenericFileScanner (full scan) and
 * ScrollManager::judgeFiles (--git mode) so both paths honor the same
 * glob semantics.
 */
final class PathExcludeMatcher
{
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
}
