<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Support\JsonFile;
use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\ConstraintReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\TestingReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\StopConditionReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SessionReset;
use JesseGall\CodeCommandments\Hooks\Handlers\SourceReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SkillReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SharedBranchGate;
use JesseGall\CodeCommandments\Hooks\Handlers\ModelChoiceReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\WorkingState;
use JesseGall\CodeCommandments\Cli\Install;
use JesseGall\CodeCommandments\Cli\Sync;
use JesseGall\CodeCommandments\Support\Binary;
/**
 * Wires code-commandments' Claude Code hooks into `.claude/settings.json` — one stamped entry per
 * distinct MOMENT, each running the {@see HookDispatch} entry point that fans out to the handler
 * registry. Shared by {@see Install} and {@see Sync}; idempotent, and strips only the entries it
 * stamped ({@see STAMP}), leaving hooks the user wrote untouched.
 */
final class HookRegistry
{
    /**
     * The settings file we are a guest in. Stated here, once, because wiring a hook is something we
     * do for a PROJECT — where the harness keeps its settings is our business, not the caller's.
     */
    private const string SETTINGS = '.claude/settings.json';

    /**
     * The stamp appended to every command we wire — a trailing shell comment (ignored when the hook
     * runs) that lets {@see stripOurs} replace exactly our own hooks and leave the user's untouched.
     */
    private const string STAMP = '# @code-commandments-managed';

    /**
     * Our reminder subcommands, for recognising PRE-stamp hooks we wrote so they migrate to stamped —
     * `remind` among them precisely because the command is GONE: a wired call to it is recognised as
     * ours, stripped, and never re-added, so an old install stops invoking a subcommand that would now
     * fail rather than being left to error on every tool use.
     */
    private const array LEGACY_SUBCOMMANDS = ['remind', 'judge-reminder', 'plan-reminder'];

    /**
     * The hooks that ship with the package; a consumer adds its own via `$config->hook(...)`.
     *
     * @var list<class-string<Hook>>
     */
    public const array BUILTINS = [
        JudgeReminder::class,
        PlanReminder::class,
        ConstraintReminder::class,
        TestingReminder::class,
        StopConditionReminder::class,
        SharedBranchGate::class,
        ModelChoiceReminder::class,
        SessionReset::class,
        SourceReminder::class,
        SkillReminder::class,
        WorkingState::class,
    ];

    /**
     * The hooks to wire for the project at $dir — the {@see BUILTINS} plus any it registered with
     * `$config->hook(...)`, minus any it silenced with `$config->disable(...)`. The disable filter
     * runs here so it governs BOTH callers at once: {@see wire} (the moment never gets wired) and
     * {@see HookDispatch} (a still-wired moment's handler never runs).
     *
     * @return list<class-string<Hook>>
     */
    public static function forProject(string $dir): array
    {
        $config = Config::load($dir);

        return $config->enabledHooks([...self::BUILTINS, ...$config->hooks()]);
    }

    /**
     * Wire $hookClasses into the project at $root. Returns true when its settings actually changed.
     *
     * A settings file that exists but does not READ as JSON is left alone and reported. It used to
     * decode to `null`, which cast to an empty array — and the write below then replaced the user's
     * whole settings file, permissions and MCP servers and their own hooks included, with nothing
     * but ours. We are a guest in that file; we may add to it, never rebuild it from nothing.
     *
     * @param  list<class-string<Hook>>  $hookClasses
     */
    public static function wire(string $root): bool
    {
        // The set is DERIVED from the project, not asked for: every caller held the root and handed
        // back what this could work out from it, which is one rule about which hooks a project gets,
        // written in two places.
        $hookClasses = self::forProject($root);
        $path = "{$root}/" . self::SETTINGS;
        $file = new JsonFile($path);
        $settings = $file->exists() ? $file->read() : [];

        if ($settings === null) {
            fwrite(STDERR, "⚠ {$path} is not readable JSON — hooks left unwired rather than overwrite it.\n");

            return false;
        }

        $before = json_encode($settings);

        $hooks = self::stripOurs(is_array($settings['hooks'] ?? null) ? $settings['hooks'] : []);

        foreach (self::moments($hookClasses) as $event => $matcher) {
            $group = ['hooks' => [['type' => 'command', 'command' => self::command($root)]]];

            if ($matcher !== null) {
                $group = ['matcher' => $matcher] + $group;
            }

            $hooks[$event][] = $group;
        }

        $settings['hooks'] = $hooks;

        if (json_encode($settings) === $before) {
            return false;
        }

        $file->write($settings);

        return true;
    }

    /**
     * The stamped command every wired moment runs — the {@see HookDispatch} entry point, which reads
     * the event from the payload and fans out to the registry.
     *
     * Anchored at `$CLAUDE_PROJECT_DIR` (the absolute project root the harness gives every hook): a
     * relative path silently dies when Claude's working directory isn't the project root. Which
     * executable it points at is {@see Binary}'s to answer, not ours to assume.
     */
    private static function command(string $root): string
    {
        return 'php "$CLAUDE_PROJECT_DIR/' . Binary::in($root) . '" hooks ' . self::STAMP;
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
