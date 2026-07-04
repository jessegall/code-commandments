<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * An in-memory overlay of pending file edits over the real filesystem — the substrate that
 * lets `repent` run its whole scribe chain to a FIXPOINT, each sweep reading through the prior
 * sweep's edits and persisting the accumulated {@see changes} only at the end. Empty by default,
 * a transparent pass-through to disk.
 */
final class WorkingCopy
{
    /**
     * @param  array<string, string>  $files  path => pending content — an edit, or a file a
     *                                         step created that isn't on disk yet
     */
    public function __construct(private readonly array $files = []) {}

    /**
     * The source to parse for $path: the pending overlay content if there is any, else the
     * file's content on disk. Null when it is neither (an unreadable/absent path).
     */
    public function read(string $path): ?string
    {
        if (array_key_exists($path, $this->files)) {
            return $this->files[$path];
        }

        $disk = @file_get_contents($path);

        return $disk === false ? null : $disk;
    }

    /**
     * The overlay paths UNDER $root, with the given extension, that aren't on disk yet — the
     * files a prior step created (an extracted component), so a fresh scan discovers them
     * alongside the real files.
     *
     * @return list<string>
     */
    public function createdUnder(string $root, string $extension): array
    {
        $prefix = rtrim($root, '/') . '/';
        $created = [];

        foreach (array_keys($this->files) as $path) {
            if (str_ends_with($path, $extension) && str_starts_with($path, $prefix) && ! is_file($path)) {
                $created[] = $path;
            }
        }

        return $created;
    }

    /**
     * A new overlay with $rewrites folded on top (a later edit to a path wins).
     *
     * @param  array<string, string>  $rewrites  path => new content
     */
    public function with(array $rewrites): self
    {
        return new self([...$this->files, ...$rewrites]);
    }

    /**
     * The accumulated edits — path => final content — to persist or diff.
     *
     * @return array<string, string>
     */
    public function changes(): array
    {
        return $this->files;
    }
}
