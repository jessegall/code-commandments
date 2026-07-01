<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Sins\Catalog;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Skill;

/**
 * `commandments disable <sin|skill>` / `commandments enable <sin|skill>` — toggle a rule in the
 * project's `.commandments/config.php` without hand-editing it. The argument is a sin id OR a
 * skill slug (the `--sin=` / `--skill=` keys, matched leniently); it's resolved to its {@see Sin}
 * or {@see Skill} class and added to / removed from the config's `$config->disable(...)` call via
 * the AST ({@see ConfigFile}) — disabling a skill silences every detector it teaches the fix for.
 * One verb per instance so the bin can route `disable`/`enable` to the same command.
 */
final class Configure
{
    public function __construct(private readonly string $action) {}

    public function run(array $args): int
    {
        $query = $this->firstArgument($args);

        if ($query === null) {
            fwrite(STDERR, "Usage: commandments {$this->action} <sin|skill>\n");

            return 2;
        }

        $target = $this->resolve($query);

        if ($target === null) {
            fwrite(STDERR, "No sin or skill matches \"{$query}\". Run `commandments judge --list` to see them.\n");

            return 2;
        }

        $file = ConfigFile::inProject();
        $changed = $this->action === 'enable' ? $file->enable($target::class) : $file->disable($target::class);

        $this->report($target, $changed);

        return 0;
    }

    private function report(Sin|Skill $target, bool $changed): void
    {
        $label = $target instanceof Skill ? "skill `{$target->slug}`" : "`{$target->name()}`";
        $verb = $this->action === 'enable' ? 'enabled' : 'disabled';
        $noun = $this->action === 'enable' ? 'was not disabled' : 'already disabled';

        $message = $changed
            ? "\033[32m✓ {$verb} {$label}.\033[0m\n"
            : "\033[2m{$label} {$noun} — nothing to do.\033[0m\n";

        fwrite(STDOUT, $message);
    }

    /**
     * The sin or skill for a query — an EXACT id/slug first, then a unique lenient match across
     * both; null when nothing matches or the query is ambiguous.
     */
    private function resolve(string $query): Sin|Skill|null
    {
        foreach (Catalog::every() as $sin) {
            if ($sin->name() === $query) {
                return $sin;
            }
        }

        foreach (Skills::all() as $skill) {
            if ($skill->slug === $query) {
                return $skill;
            }
        }

        $matches = [
            ...array_filter(Catalog::every(), static fn (Sin $sin): bool => $sin->matches($query)),
            ...array_filter(Skills::all(), static fn (Skill $skill): bool => $skill->matches($query)),
        ];

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    private function firstArgument(array $args): ?string
    {
        foreach ($args as $arg) {
            if (! str_starts_with($arg, '--')) {
                return $arg;
            }
        }

        return null;
    }
}
