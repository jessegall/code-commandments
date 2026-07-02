<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Config;

/**
 * Wires code-commandments' Claude Code hooks into the project's `.claude/settings.json` — shared by
 * {@see Install} (first setup) and {@see Sync} (every `composer update`), so the hooks self-heal to
 * the current wiring instead of freezing at install time. The wiring is data-driven: each {@see Hook}
 * declares its own {@see Hook::bindings} (event + matcher), and every hook — the {@see BUILTINS} plus
 * any a consumer registered with `$config->hook(...)` — is wired the same way. Each is wired to run
 * through the generic `commandments hook '<class>'` entry point ({@see HookRunner}).
 *
 * Idempotent, and MIGRATING: it removes ONLY the hooks WE stamped ({@see STAMP}) — from every event,
 * then adds back exactly the current set under their current events. So an older hook wired under the
 * wrong event (a `remind` under `UserPromptSubmit`) moves; and CRUCIALLY, every hook the human wrote —
 * in any event, even one that itself runs `commandments` — is left completely untouched, because it
 * carries no stamp. (A one-time migration also matches our PRE-stamp reminder hooks so they upgrade to
 * stamped.) Idempotent by CONTENT: it writes only when the settings actually change.
 */
final class Hooks
{
    // Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the harness gives every hook) — a
    // relative `vendor/bin/...` silently dies when Claude's working directory isn't the project root.
    private const string BINARY = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments"';

    /**
     * The stamp appended to every command WE wire — a trailing shell comment (ignored when the hook
     * runs), so {@see stripOurs} can recognise and replace exactly our own hooks and NEVER touch one
     * the user wrote by hand, even if theirs also invokes `commandments`. This is the ONLY thing we
     * strip; a hook without this stamp is the human's and is preserved untouched.
     */
    private const string STAMP = '# @code-commandments-managed';

    /** Our reminder subcommands, for recognising PRE-stamp hooks we wrote so they migrate to stamped. */
    private const array LEGACY_SUBCOMMANDS = ['remind', 'judge-reminder', 'plan-reminder'];

    /**
     * The hooks that ship with the package. A consumer adds its own via `$config->hook(...)`; both
     * flow through {@see wire} the same way, wired from each hook's {@see Hook::bindings}.
     *
     * @var list<class-string<Hook>>
     */
    public const array BUILTINS = [Remind::class, JudgeReminder::class, PlanReminder::class];

    /**
     * The hooks to wire for the project at $dir — the {@see BUILTINS} plus any it registered with
     * `$config->hook(...)`. The one place the open set is assembled, so {@see Install}/{@see Sync}
     * can't drift.
     *
     * @return list<class-string<Hook>>
     */
    public static function forProject(string $dir): array
    {
        return [...self::BUILTINS, ...Config::load($dir)->hooks()];
    }

    /**
     * Wire $hookClasses (the {@see BUILTINS} by default; callers pass the built-ins PLUS a project's
     * registered hooks) into the settings at $path. Returns true when the file actually changed.
     *
     * @param  list<class-string<Hook>>  $hookClasses
     */
    public static function wire(string $path, array $hookClasses = self::BUILTINS): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $before = json_encode($settings);

        $hooks = self::stripOurs(is_array($settings['hooks'] ?? null) ? $settings['hooks'] : []);

        foreach ($hookClasses as $class) {
            foreach (new $class()->bindings() as $binding) {
                $group = ['hooks' => [['type' => 'command', 'command' => self::command($class)]]];

                if ($binding->matcher !== null) {
                    $group = ['matcher' => $binding->matcher] + $group;
                }

                $hooks[$binding->event][] = $group;
            }
        }

        $settings['hooks'] = $hooks;

        if (json_encode($settings) === $before) {
            return false;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }

    /**
     * The stamped command that runs a hook class through the generic runner. The class is single-
     * quoted so its namespace backslashes reach the CLI intact.
     *
     * @param  class-string<Hook>  $class
     */
    private static function command(string $class): string
    {
        return self::BINARY . " hook '" . $class . "' " . self::STAMP;
    }

    /**
     * Drop every one of OUR hooks from every event — each other hook, in any event, is preserved.
     * An event left empty is removed so it doesn't linger as an empty array.
     *
     * @param  array<string, mixed>  $hooks
     * @return array<string, mixed>
     */
    private static function stripOurs(array $hooks): array
    {
        foreach ($hooks as $event => $groups) {
            $rebuilt = [];

            foreach (is_array($groups) ? $groups : [] as $group) {
                if (! is_array($group) || ! is_array($group['hooks'] ?? null)) {
                    $rebuilt[] = $group;

                    continue;
                }

                $group['hooks'] = array_values(array_filter(
                    $group['hooks'],
                    static fn ($hook): bool => ! self::isOurs(is_array($hook) ? (string) ($hook['command'] ?? '') : ''),
                ));

                if ($group['hooks'] !== []) {
                    $rebuilt[] = $group;
                }
            }

            if ($rebuilt === []) {
                unset($hooks[$event]);
            } else {
                $hooks[$event] = array_values($rebuilt);
            }
        }

        return $hooks;
    }

    /**
     * Is $command one WE wrote — safe to strip and re-add? True for any command carrying our
     * {@see STAMP}, and (for one-time migration of pre-stamp installs) for a bare `commandments`
     * invocation ENDING in one of our own reminder {@see LEGACY_SUBCOMMANDS}. A hook the human wrote —
     * even one that runs `commandments judge`/`repent`/`sync` — carries no stamp and doesn't end in a
     * reminder subcommand, so it is NEVER matched here and is preserved untouched.
     */
    private static function isOurs(string $command): bool
    {
        if (str_contains($command, self::STAMP)) {
            return true;
        }

        if (! str_contains($command, 'commandments')) {
            return false;
        }

        $command = rtrim($command);

        foreach (self::LEGACY_SUBCOMMANDS as $subcommand) {
            if (str_ends_with($command, $subcommand)) {
                return true;
            }
        }

        return false;
    }
}
