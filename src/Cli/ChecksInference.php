<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Infers a project's end-gate check commands from the scripts it already declares — read from
 * `composer.json` (`composer <script>`) and `package.json` (`npm run <script>`). It is what
 * {@see Sync} feeds into the injected `planExecution()->onComplete(...)` so a freshly-wired
 * consumer has a sensible gate without hand-writing one; the human edits it freely after.
 *
 * Deliberately CONSERVATIVE: one command per category (test, then lint, then static analysis),
 * first match wins, so it never emits `composer test` AND `composer phpunit`. It only recognises
 * conventionally-named scripts — anything unusual is left for the human to add. Empty when a
 * project declares nothing recognisable.
 */
final class ChecksInference
{
    /**
     * The recognised script names, grouped by category and ordered by preference within each — the
     * first present in a group is picked, the rest ignored. Shared across both ecosystems.
     *
     * @var list<list<string>>
     */
    private const array CATEGORIES = [
        ['test', 'tests', 'pest', 'phpunit'],
        ['lint', 'pint', 'cs', 'format', 'csfixer'],
        ['analyse', 'analyze', 'stan', 'phpstan', 'types', 'typecheck'],
    ];

    /**
     * The inferred `onComplete` commands for the project at $dir — composer scripts first, then npm.
     *
     * @return list<string>
     */
    public static function detect(string $dir): array
    {
        return [
            ...self::pick(self::scripts("{$dir}/composer.json"), static fn (string $name): string => "composer {$name}"),
            ...self::pick(self::scripts("{$dir}/package.json"), static fn (string $name): string => "npm run {$name}"),
        ];
    }

    /**
     * One command per category, first recognised script name wins.
     *
     * @param  list<string>  $scriptNames
     * @param  callable(string): string  $format
     * @return list<string>
     */
    private static function pick(array $scriptNames, callable $format): array
    {
        $commands = [];

        foreach (self::CATEGORIES as $candidates) {
            foreach ($candidates as $name) {
                if (! in_array($name, $scriptNames, true)) {
                    continue;
                }

                $commands[] = $format($name);

                break;
            }
        }

        return $commands;
    }

    /**
     * The `scripts` object's keys from a JSON manifest, or `[]` when the file is absent or malformed.
     *
     * @return list<string>
     */
    private static function scripts(string $manifest): array
    {
        if (! is_file($manifest)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($manifest), true);
        $scripts = is_array($json) && is_array($json['scripts'] ?? null) ? $json['scripts'] : [];

        return array_values(array_filter(array_keys($scripts), 'is_string'));
    }
}
