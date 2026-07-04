<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Discovers page components in Inertia apps by reading the glob pattern from `app.ts`
 * (never scraped). Every `.vue` beneath the pattern's fixed prefix directory is a page root.
 * Empty for non-Inertia apps or apps without the entry.
 */
final class PageRoots
{
    private const array ENTRIES = ['resources/js/app.ts', 'resources/js/app.js', 'resources/js/app.tsx'];

    /**
     * The absolute paths of every page component under the project root.
     *
     * @return list<string>
     */
    public static function discover(string $projectRoot): array
    {
        foreach (self::ENTRIES as $entry) {
            $file = $projectRoot . '/' . $entry;

            if (! is_file($file)) {
                continue;
            }

            $pattern = (new Script((string) file_get_contents($file)))->callStringArg('glob');

            if ($pattern !== null && str_ends_with($pattern, '.vue')) {
                return self::enumerate(dirname($file), $pattern);
            }
        }

        return [];
    }

    /**
     * The `.vue` files under the glob's fixed prefix directory — `./Pages/**\/*.vue` from
     * the entry dir → every component beneath `Pages/`.
     *
     * @return list<string>
     */
    private static function enumerate(string $entryDir, string $pattern): array
    {
        $prefix = explode('*', $pattern)[0]; // the fixed path before the first wildcard
        $dir = realpath($entryDir . '/' . $prefix);

        if ($dir === false || ! is_dir($dir)) {
            return [];
        }

        $pages = [];
        $walk = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($walk as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $pages[] = $file->getPathname();
            }
        }

        sort($pages); // stable order, independent of the filesystem

        return $pages;
    }
}
