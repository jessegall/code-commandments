<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

use JesseGall\CodeCommandments\Hooks\HookBinding;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use ReflectionClass;

/**
 * The self-describing catalogue of the WIRED Claude Code hooks — every {@see HookRegistry::BUILTINS}
 * handler, its Claude Code events (from its own `bindings()`), and its one-line `summary()`. The docs
 * generator emits a table from it and a test fails when a builtin lacks a summary, so a newly-wired hook
 * can't ship undocumented.
 */
final class HookCatalog
{
    /**
     * The hooks as a markdown table — the single source for both the README generator and its currency test.
     */
    public static function table(): string
    {
        $rows = "| Hook | Events | What it does |\n|---|---|---|\n";

        foreach (self::all() as $hook) {
            $rows .= $hook->row();
        }

        return $rows;
    }

    /**
     * @return list<HookEntry>
     */
    public static function all(): array
    {
        return array_map(static function (string $class): HookEntry {
            $hook = new $class();

            return new HookEntry(
                new ReflectionClass($class)->getShortName(),
                self::events($hook),
                $hook->summary(),
            );
        }, HookRegistry::BUILTINS);
    }

    /**
     * The distinct Claude Code events a hook binds — `Event` or `Event/Matcher` (e.g. `PostToolUse/ExitPlanMode`).
     */
    private static function events(Hook $hook): string
    {
        $events = array_map(static fn (HookBinding $binding): string => $binding->label(), $hook->bindings());

        return implode(', ', array_values(array_unique($events)));
    }
}
