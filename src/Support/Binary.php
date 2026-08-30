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

    /**
     * Does this shell command RUN our own executable? Asked of the command rather than of the tool,
     * because `Bash` is not the exemption — a gate that let every `Bash` through would be no gate, and
     * one that matched a single literal path would stop recognising its own CLI the day the binary
     * moved. It is the same question {@see in} answers, asked of a string somebody already wrote.
     *
     * A hook that refuses tool use has to leave the CLI reachable, since the CLI is how the state it
     * refuses on is read and repaired; a refusal with no way out is a lockout, and this package has
     * already shipped one.
     */
    public static function isInvokedBy(string $command): bool
    {
        foreach (self::segments($command) as $segment) {
            if (self::runsTheBinary($segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the FIRST word of this segment our executable — allowing an interpreter in front of it, which
     * is how a checkout with no execute bit runs it? The leading word is what a shell runs; a path in
     * any later position is an argument, and `echo bin/commandments` is talk about the command.
     */
    private static function runsTheBinary(string $segment): bool
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($segment)) ?: []));

        foreach ($words as $index => $word) {
            if ($index === 0 && self::isInterpreter($word)) {
                continue;
            }

            return self::names(trim($word, '\'"'));
        }

        return false;
    }

    private static function isInterpreter(string $word): bool
    {
        return basename($word) === 'php';
    }

    /**
     * Is this word one of the paths our executable is known by — as written, or as the tail of an
     * absolute or `./`-relative one? The candidates are the same list {@see in} chooses from, so the
     * two answers can never drift apart.
     */
    private static function names(string $word): bool
    {
        foreach (self::CANDIDATES as $candidate) {
            if ($word === $candidate || str_ends_with($word, '/' . $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The command split into the pieces a shell would run separately, so a CLI call still reads as one
     * when it is chained or piped (`cd lane && vendor/bin/commandments queue`, `… queue | head`).
     *
     * @return list<string>
     */
    private static function segments(string $command): array
    {
        return array_values(array_filter(preg_split('/&&|\|\||;|\|/', $command) ?: []));
    }
}
