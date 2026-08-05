<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Files;

use JesseGall\CodeCommandments\Located;

/**
 * One FILE as something a rule can judge — its {@see path}, its {@see name}, the {@see stem} a module
 * is actually called and its {@see extension}. A poetic module name is the same sin as a poetic
 * identifier and just as contagious, but a detector could only ever see the declarations INSIDE a
 * file, never what the file itself is called (#445). Engine-agnostic on purpose: a path is a path
 * whether the file holds PHP or a Vue component.
 */
class FileMatch implements Located
{
    public function __construct(public readonly string $path) {}

    public function file(): string
    {
        return $this->path;
    }

    /**
     * `file:line` for the report — line 1, because the name is the file's first fact.
     */
    public function location(): string
    {
        return "{$this->path}:1";
    }

    public function scope(): string
    {
        return $this->name();
    }

    /**
     * The file name with its extension — `PaymentData.php`, `standing.ts`.
     */
    public function name(): string
    {
        return basename($this->path);
    }

    /**
     * What the module is CALLED — the name with its extension dropped (`standing`).
     */
    public function stem(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * The extension without its dot, lowercased (`php`, `vue`, `ts`); empty when the file has none.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    /**
     * The directory the file sits in.
     */
    public function directory(): string
    {
        return dirname($this->path);
    }

    /**
     * The path split into its segments, the file name last — so a rule can judge where a module
     * lives as well as what it is called.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), static fn (string $part): bool => $part !== ''));
    }
}
