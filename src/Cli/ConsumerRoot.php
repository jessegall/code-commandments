<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * WHICH project `install`/`sync` are wiring — the directory whose `composer.json` declares us a
 * dependency, found by walking up from where the command was run. Composer runs its own hooks
 * there, so on the path that matters this is simply the current directory; the walk is for a human
 * who ran the verb from somewhere inside the project.
 */
final class ConsumerRoot
{
    /**
     * The consumer root at or above $cwd, or null when $cwd is not inside one — which is a REFUSAL,
     * not a fallback. These commands publish files into the directory they choose and sweep their
     * own artifacts back out of it; pointed at a home directory that is the user's global agent
     * configuration, and at nothing at all it is a stray half-integration wherever the shell stood.
     * A home directory is refused even when it does hold a `composer.json`, because what lives
     * beside it is global, not a project.
     */
    public static function from(string $cwd): ?string
    {
        $home = self::home();
        $dir = realpath($cwd);

        while ($dir !== false && $dir !== dirname($dir)) {
            if ($dir === $home) {
                return null;
            }

            if (is_file("{$dir}/composer.json")) {
                return $dir;
            }

            $dir = realpath(dirname($dir));
        }

        return null;
    }

    private static function home(): ?string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return is_string($home) && $home !== '' ? (realpath($home) ?: $home) : null;
    }
}
