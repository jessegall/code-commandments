<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * Where the backend's type generator writes its output — a {@see Contract} the backend
 * publishes so a frontend detector can tell a GENERATED type from a hand-copied one. A
 * declaration inside this directory IS the single source of truth (the generator's
 * output), not a duplicate, so it must never be flagged.
 *
 * The path is read from the project's actual transformer configuration, so a non-default
 * output location is honoured.
 */
final class GeneratedTypes implements Contract
{
    /**
     * @param  string  $location  the generated output — a single FILE (matched exactly) or a
     *         DIRECTORY (matched for all its descendants).
     */
    public function __construct(public readonly string $location) {}

    /**
     * Is $file the generator's own output — the output file itself, or a file inside the
     * output directory — rather than hand-written code?
     */
    public function covers(string $file): bool
    {
        $file = realpath($file) ?: $file;

        return $file === $this->location || str_starts_with($file, $this->location . '/');
    }
}
