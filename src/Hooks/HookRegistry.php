<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\ConstraintReminder;
use JesseGall\CodeCommandments\Cli\Install;
use JesseGall\CodeCommandments\Cli\Sync;
/**
 * Wires code-commandments' Claude Code hooks into `.claude/settings.json` — one stamped entry per
 * distinct MOMENT, each running the {@see HookDispatch} entry point that fans out to the handler
 * registry. Shared by {@see Install} and {@see Sync}; idempotent, and strips only the entries it
 * stamped ({@see STAMP}), leaving hooks the user wrote untouched.
 */
final class HookRegistry
{
    // Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the harness gives every hook) — a
    // relative `vendor/bin/...` silently dies when Claude's working directory isn't the project root.
    private const string BINARY = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments"';

    /**
     * The stamp appended to every command we wire — a trailing shell comment (ignored when the hook
     * runs) that lets {@see stripOurs} replace exactly our own hooks and leave the user's untouched.
     */
    private const string STAMP = '# @code-commandments-managed';

    /** Our reminder subcommands, for recognising PRE-stamp hooks we wrote so they migrate to stamped. */
    private const array LEGACY_SUBCOMMANDS = ['remind', 'judge-reminder', 'plan-reminder'];

    /**
     * The hooks that ship with the package; a consumer adds its own via `$config->hook(...)`.
     *
     * @var list<class-string<Hook>>
     */
    public const array BUILTINS = [Remind::class, JudgeReminder::class, PlanReminder::class, ConstraintReminder::class];

    /**
     * The hooks to wire for the project at $dir — the {@see BUILTINS} plus any it registered with
     * `$config->hook(...)`.
     *
     * @return list<class-string<Hook>>
     */
    public static function forProject(string $dir): array
    {
        return [...self::BUILTINS, ...Config::load($dir)->hooks()];
    }

    /**
     * Wire $hookClasses into the settings at $path. Returns true when the file actually changed.
     *
     * @param  list<class-string<Hook>>  $hookClasses
     */
    public static function wire(string $path, array $hookClasses = self::BUILTINS): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $before = json_encode($settings);

        $hooks = self::stripOurs(is_array($settings['hooks'] ?? null) ? $settings['hooks'] : []);

        foreach (self::moments($hookClasses) as $event => $matcher) {
            $group = ['hooks' => [['type' => 'command', 'command' => self::command()]]];

            if ($matcher !== null) {
                $group = ['matcher' => $matcher] + $group;
            }

            $hooks[$event][] = $group;
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
     * The stamped command every wired moment runs — the {@see HookDispatch} entry point, which reads
     * the event from the payload and fans out to the registry.
     */
    private static function command(): string
    {
        return self::BINARY . ' hooks ' . self::STAMP;
    }

    /**
     * The distinct MOMENTS to wire, from the union of every handler's {@see Hook::bindings} — one entry
     * per event, carrying a matcher only when every handler for that event shares one (else unmatched, so
     * the handlers self-filter by tool).
     *
     * @param  list<class-string<Hook>>  $hookClasses
     * @return array<string, ?string>  event => matcher (null = unmatched)
     */
    private static function moments(array $hookClasses): array
    {
        $matchersByEvent = [];

        foreach ($hookClasses as $class) {
            foreach (new $class()->bindings() as $binding) {
                $matchersByEvent[$binding->event][] = $binding->matcher;
            }
        }

        $moments = [];

        foreach ($matchersByEvent as $event => $matchers) {
            $unique = array_values(array_unique($matchers, SORT_REGULAR));

            $moments[$event] = count($unique) === 1 && $unique[0] !== null ? $unique[0] : null;
        }

        return $moments;
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
     * Is $command one we wrote — safe to strip and re-add? True for any command carrying our
     * {@see STAMP}, and (for one-time migration of pre-stamp installs) for a bare `commandments`
     * invocation ending in one of our reminder {@see LEGACY_SUBCOMMANDS}.
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
