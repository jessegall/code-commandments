<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Sins\Catalog;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * `commandments disable <sin>` / `commandments enable <sin>` — toggle a rule in the project's
 * `.commandments/config.php` without hand-editing it. The `<sin>` is a sin id (the `--sin=`
 * key, matched leniently); it's resolved to its {@see Sin} class and added to / removed from the
 * config's `$config->disable(...)` call via the AST ({@see ConfigFile}). One verb per instance
 * so the bin can route `disable`/`enable` to the same command.
 */
final class Configure
{
    public function __construct(private readonly string $action) {}

    public function run(array $args): int
    {
        $query = $this->firstArgument($args);

        if ($query === null) {
            fwrite(STDERR, "Usage: commandments {$this->action} <sin>\n");

            return 2;
        }

        $sin = $this->resolve($query);

        if ($sin === null) {
            fwrite(STDERR, "No sin matches \"{$query}\". Run `commandments judge --list` to see them.\n");

            return 2;
        }

        $file = ConfigFile::inProject();
        $changed = $this->action === 'enable' ? $file->enable($sin::class) : $file->disable($sin::class);

        $this->report($sin, $changed);

        return 0;
    }

    private function report(Sin $sin, bool $changed): void
    {
        $verb = $this->action === 'enable' ? 'enabled' : 'disabled';
        $noun = $this->action === 'enable' ? 'was not disabled' : 'already disabled';

        $message = $changed
            ? "\033[32m✓ {$verb} `{$sin->name()}`.\033[0m\n"
            : "\033[2m`{$sin->name()}` {$noun} — nothing to do.\033[0m\n";

        fwrite(STDOUT, $message);
    }

    /**
     * The sin for a query — an EXACT id first, then a unique lenient match; null when nothing
     * matches or the query is ambiguous.
     */
    private function resolve(string $query): ?Sin
    {
        $sins = Catalog::every();

        foreach ($sins as $sin) {
            if ($sin->name() === $query) {
                return $sin;
            }
        }

        $matches = array_values(array_filter($sins, static fn (Sin $sin): bool => $sin->matches($query)));

        return count($matches) === 1 ? $matches[0] : null;
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
