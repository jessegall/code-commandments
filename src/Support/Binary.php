<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Where a project's `commandments` executable actually is — the one home of a path we used to
 * assume, and state as a literal, in every command we write into a project.
 */
final class Binary
{
    /**
     * The candidates, best first: composer's shim, then the executable a checkout carries itself.
     */
    private const array CANDIDATES = ['vendor/bin/commandments', 'bin/commandments'];

    /**
     * The path, relative to $root, that a command written into this project should invoke.
     *
     * A consumer has composer's `vendor/bin/` shim, which is why that was hardcoded everywhere. But
     * composer never shims a package's OWN bin into its OWN vendor, so in this package — the one
     * project that must be able to run its own disciplines against itself — every wired hook and
     * every generated check pointed at a file that is not there, and failed on each tool call.
     *
     * Relative on purpose: a hook is anchored at `$CLAUDE_PROJECT_DIR` and a check runs from the
     * project root, so both need the path WITHIN the project, not this machine's copy of it.
     */
    public static function in(string $root): string
    {
        foreach (self::CANDIDATES as $candidate) {
            if (is_file("{$root}/{$candidate}")) {
                return $candidate;
            }
        }

        // Nothing installed yet — name the path a consumer will have, so wiring a project before
        // its first `composer install` still writes the command that works once it has one.
        return self::CANDIDATES[0];
    }
}
