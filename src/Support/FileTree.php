<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use FilesystemIterator;
use JesseGall\CodeCommandments\ExcludedPaths;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The ONE walk over a source tree — what counts as a file to read, and what is never descended,
 * decided once for every engine rather than re-spelled at each of them. Written because it WAS
 * re-spelled: the PHP walk checked the exclusions on a file handed to it directly but not its
 * extension, and the Vue walk checked the extension but not the exclusions, so each let through
 * exactly what the other refused.
 */
final class FileTree
{
    /**
     * Directories no walk descends: a dependency tree is not the project's source, and its size is
     * what exhausts a scan started at a project root. For Python that is an installed package tree and
     * the interpreter's bytecode cache — names the interpreter itself writes.
     */
    private const array SKIP_DIRS = ['vendor', 'node_modules', 'site-packages', '__pycache__'];

    /**
     * The file every Python virtual environment carries at its root, whatever the folder is named.
     */
    private const string VIRTUAL_ENVIRONMENT = 'pyvenv.cfg';

    /**
     * The endings of directories a package build writes beside the source — `shop.egg-info`.
     */
    private const array SKIP_SUFFIXES = ['.egg-info'];

    /**
     * Every file under $path carrying $extension. A $path that IS a file answers for itself, held to
     * the same two questions as one the walk reaches.
     *
     * @return iterable<string>
     */
    public static function filesIn(string $path, string $extension, ExcludedPaths $excluded = new ExcludedPaths()): iterable
    {
        if (is_file($path)) {
            if (self::isWanted(new SplFileInfo($path), $extension) && ! $excluded->covers($path)) {
                yield $path;
            }

            return;
        }

        if (! is_dir($path) || $excluded->covers($path)) {
            return;
        }

        $pruned = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => ! $file->isDir() || self::descends($file, $excluded),
        );

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if ($file instanceof SplFileInfo && self::isWanted($file, $extension)) {
                yield $file->getPathname();
            }
        }
    }

    private static function isWanted(SplFileInfo $file, string $extension): bool
    {
        return $file->isFile() && $file->getExtension() === $extension;
    }

    /**
     * Is $directory one to descend? Never a symlink, which can point back up the tree and recurse for
     * ever; never a hidden directory, which is tooling rather than source; never a dependency tree; and
     * never an excluded subtree — pruned HERE rather than filtered from the findings later, because a
     * monorepo's build output is megabytes a run would otherwise read and parse in full before
     * discarding every sin it found there.
     */
    private static function descends(SplFileInfo $directory, ExcludedPaths $excluded): bool
    {
        return ! $directory->isLink()
            && ! str_starts_with($directory->getFilename(), '.')
            && ! in_array($directory->getFilename(), self::SKIP_DIRS, true)
            && ! array_any(self::SKIP_SUFFIXES, static fn (string $suffix): bool => str_ends_with($directory->getFilename(), $suffix))
            && ! is_file($directory->getPathname() . '/' . self::VIRTUAL_ENVIRONMENT)
            && ! $excluded->covers($directory->getPathname());
    }
}
