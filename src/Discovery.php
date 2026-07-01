<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Turns a directory tree into the fully-qualified class names it holds — the shared discovery
 * the {@see Detectors\Catalog}, {@see Sins\Catalog} and {@see Skills\Catalog} all glob through,
 * so none of them hand-rolls a scan. Recurses, so a SUBFOLDER becomes a SUB-NAMESPACE:
 * `Backend/Laravel/FacadeCallDetector.php` under namespace `…\Detectors\Backend` resolves to
 * `…\Detectors\Backend\Laravel\FacadeCallDetector`. That's what lets a package's rules live in
 * their own `Laravel/` / `SpatieData/` folder and still auto-enrol, no list to maintain.
 */
final class Discovery
{
    /**
     * Every class whose file ends with `{$suffix}.php` anywhere under $dir, as an FQCN built
     * from its path below $dir (prefixed with $namespace). Pass a `''` suffix for every `.php`.
     *
     * @return list<class-string>
     */
    public static function classes(string $dir, string $namespace, string $suffix = ''): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $classes = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), "{$suffix}.php")) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $classes[] = $namespace . '\\' . str_replace('/', '\\', $relative);
        }

        sort($classes);

        return $classes;
    }
}
