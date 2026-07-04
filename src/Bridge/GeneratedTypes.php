<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * The generated-types output contract — a declaration here IS the single source of truth
 * (the generator's output), never a duplicate. The path comes from the transformer config.
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
