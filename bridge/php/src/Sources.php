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
     * @return list<string>
     */
    public static function in(array $paths): array
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
        }
        $files = array_keys($files);
        sort($files);

        return $files;
    }
}
