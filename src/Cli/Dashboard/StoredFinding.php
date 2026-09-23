<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Dashboard;

use JesseGall\CodeCommandments\Finding;
use JsonSerializable;

/**
 * One sin as the dashboard keeps it between runs: which rule, which skill teaches the fix, and where —
 * the real path it was judged under, the path shown to a reader, and the line.
 */
final readonly class StoredFinding implements JsonSerializable
{
    public function __construct(
        public string $sin,
        public string $skill,
        public string $path,
        public string $file,
        public int $line,
        public string $scope,
    ) {}

    /**
     * $finding as the dashboard keeps it, its file named relative to $root.
     */
    public static function of(Finding $finding, string $root): self
    {
        $path = realpath($finding->file) ?: $finding->file;
        $prefix = rtrim($root, '/') . '/';

        return new self(
            $finding->sin,
            $finding->skill,
            $path,
            str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path,
            (int) substr($finding->location, (int) strrpos($finding->location, ':') + 1),
            $finding->scope,
        );
    }

    /**
     * A finding read back from the store it was written to.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function fromStored(array $stored): self
    {
        return new self((string) $stored['sin'], (string) $stored['skill'], (string) $stored['path'], (string) $stored['file'], (int) $stored['line'], (string) $stored['scope']);
    }

    /**
     * @return array{sin: string, skill: string, path: string, file: string, line: int, scope: string}
     */
    public function jsonSerialize(): array
    {
        return ['sin' => $this->sin, 'skill' => $this->skill, 'path' => $this->path, 'file' => $this->file, 'line' => $this->line, 'scope' => $this->scope];
    }
}
