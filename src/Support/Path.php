<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Small path helpers shared by the CLI commands that print file locations. One home so `hints` and
 * `repent` don't each keep a private copy of "strip the project root off an absolute path".
 */
final class Path
{
    /**
     * $path relative to $base when it lives under it (`{base}/foo` → `foo`), else $path unchanged.
     */
    public static function relative(string $path, string $base): string
    {
        return str_starts_with($path, $base . '/') ? substr($path, strlen($base) + 1) : $path;
    }

    /**
     * $path's resolved spelling — the ONE form in which two spellings of the same file agree, so a
     * set of paths can be asked `isset` rather than compared string by string. A path with nothing
     * behind it yet (a file a {@see \JesseGall\CodeCommandments\WorkingCopy} has created but not
     * written) keeps the spelling it came with: dropping it would answer for fewer files than the
     * caller asked about.
     */
    public static function resolved(string $path): string
    {
        return realpath($path) ?: $path;
    }

    /**
     * $paths as a set of {@see resolved} spellings — the membership test behind "is this file one of
     * the ones in hand?".
     *
     * @param  list<string>  $paths
     * @return array<string, true>
     */
    public static function setOf(array $paths): array
    {
        $set = [];

        foreach ($paths as $path) {
            $set[self::resolved($path)] = true;
        }

        return $set;
    }

    /**
     * $dir and then every directory above it, up to the filesystem root — the climb "which
     * ancestor holds this?" behind every search for a `composer.json`, a `node_modules`, or a
     * project root. Each of those wrote the walk out by hand, with its own idea of when to stop.
     *
     * @return iterable<string>
     */
    public static function selfAndAncestors(string $dir): iterable
    {
        while (true) {
            yield $dir;

            $parent = dirname($dir);

            if ($parent === $dir) {
                return;
            }

            $dir = $parent;
        }
    }
}
