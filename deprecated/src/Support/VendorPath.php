<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Decide whether a file path points inside a `vendor/` directory.
 *
 * The naive `str_contains($file, '/vendor/')` is WRONG for paths returned by
 * Composer's optimized autoloader, which expresses app-class locations
 * relative to `vendor/composer/`:
 *
 *     <root>/vendor/composer/../../src/Foo.php
 *
 * That string literally contains `/vendor/` even though the real file lives in
 * the project root — so app classes get mistaken for third-party ones and any
 * prophet that skips vendor code silently stops flagging them. Always route the
 * decision through here; the `../` segments are collapsed first.
 */
final class VendorPath
{
    public static function isVendor(string $file): bool
    {
        return str_contains(self::normalize($file), '/vendor/');
    }

    /**
     * Collapse `.` and `..` segments without touching the filesystem (so it
     * works for paths that don't exist yet and stays deterministic in tests).
     */
    public static function normalize(string $file): string
    {
        $file = str_replace('\\', '/', $file);

        $segments = [];

        foreach (explode('/', $file) as $segment) {
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            if ($segment === '.') {
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
