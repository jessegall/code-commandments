<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Files;

use JesseGall\CodeCommandments\Query;

/**
 * The fluent query over FILES — `whereFile()` on either engine's codebase, narrowed by
 * `where`/`reject` on a {@see FileMatch} and closed by the same terminals as every other query.
 * One implementation for both engines: a file name is not a language, so the machinery that judges
 * one must not be written twice.
 */
final class FileQuery extends Query
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(private readonly array $paths) {}

    /**
     * Only files with one of these extensions (given with or without the dot).
     */
    public function withExtension(string ...$extensions): static
    {
        $wanted = array_map(static fn (string $extension): string => strtolower(ltrim($extension, '.')), $extensions);

        return $this->filter(static fn (FileMatch $file): bool => in_array($file->extension(), $wanted, true));
    }

    protected function selected(): iterable
    {
        return $this->paths;
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        $class = $as ?? FileMatch::class;

        return new $class((string) $candidate);
    }

    protected function matchClass(): string
    {
        return FileMatch::class;
    }
}
