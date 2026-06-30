<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Discovers the PAGE COMPONENTS of an Inertia app — the roots of the render tree, the entry
 * points from which every prop type flows downward. The bootstrap declares them:
 * `app.ts` calls `import.meta.glob('./Pages/**\/*.vue')`, so the glob pattern (read off the
 * AST by {@see Script::callStringArg}, never scraped) names exactly the page set.
 *
 * The pattern's fixed prefix — everything before its first `*` — is the pages directory,
 * resolved against the entry file; every `.vue` beneath it is a page root. Empty when the
 * app isn't Inertia / has no such entry (a Blade-bootstrapped app needs its own adapter).
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
