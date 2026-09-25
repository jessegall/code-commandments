<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;
use SplFileInfo;

/** The .php files a set of paths holds, sorted, each once; vendor and dot folders are not the project's own. */
final class Sources
{
    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $contents  a file here that is under a folder and not on disk is read too
     * @return list<string>
     */
    public static function in(array $paths, array $contents = []): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[$path] = true;
                continue;
            }
            $folders = new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                static fn (SplFileInfo $entry): bool => ! $entry->isDir()
                    || ($entry->getFilename() !== 'vendor' && ! str_starts_with($entry->getFilename(), '.')),
            );
            foreach (new RecursiveIteratorIterator($folders) as $entry) {
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[realpath($entry->getPathname())] = true;
                }
            }
            foreach (array_keys($contents) as $file) {
                if (str_ends_with($file, '.php') && str_starts_with($file, rtrim($path, '/') . '/') && ! is_file($file)) {
                    $files[$file] = true;
                }
            }
        }
        $files = array_keys($files);
        sort($files);

        return $files;
    }
}
