<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Wires code-commandments' Claude Code hooks into the project's `.claude/settings.json` — shared by
 * {@see Install} (first setup) and {@see Sync} (every `composer update`), so the hooks self-heal to
 * the current wiring instead of freezing at install time. Three hooks, one mechanism:
 *
 *  - the cardinal-rule heartbeat ({@see Remind}) under `PostToolUse`, and
 *  - the "did you judge?" nudge ({@see JudgeReminder}) under both `Stop` (turn ending) and
 *    `PreToolUse` matching `Bash` (a `git commit` about to run).
 *
 * Idempotent, and MIGRATING: it removes ONLY the hooks WE stamped ({@see STAMP}) — from every event,
 * then adds back exactly the current set under their current events. So an older hook wired under the
 * wrong event (a `remind` under `UserPromptSubmit`) moves; and CRUCIALLY, every hook the human wrote —
 * in any event, even one that itself runs `commandments` — is left completely untouched, because it
 * carries no stamp. (A one-time migration also matches our PRE-stamp reminder hooks so they upgrade to
 * stamped.) Idempotent by CONTENT: it writes only when the settings actually change.
 *
 * The current set: the {@see Remind} heartbeat, the {@see JudgeReminder} nudge (Stop + PreToolUse on
 * `git commit`), and the {@see PlanReminder} (PostToolUse on `ExitPlanMode` to open a plan, Stop to
 * keep it going). Add one by adding a {@see HOOKS} row; the wiring is generic.
 */
final class Hooks
{
    // Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the harness gives every hook) — a
    // relative `vendor/bin/...` silently dies when Claude's working directory isn't the project root.
    private const string REMIND = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" remind';

    private const string JUDGE = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" judge-reminder';

    private const string PLAN = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" plan-reminder';

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
     * Our hooks as (event, command, matcher). A null matcher means "every call of the event"; a
     * matcher (e.g. `Bash`) scopes to that tool. Add a hook by adding a row here; the wiring is generic.
     *
     * @var list<array{event: string, command: string, matcher: ?string}>
     */
    private const array HOOKS = [
        ['event' => 'PostToolUse', 'command' => self::REMIND, 'matcher' => null],
        ['event' => 'Stop', 'command' => self::JUDGE, 'matcher' => null],
        ['event' => 'PreToolUse', 'command' => self::JUDGE, 'matcher' => 'Bash'],
        ['event' => 'PostToolUse', 'command' => self::PLAN, 'matcher' => 'ExitPlanMode'],
        ['event' => 'Stop', 'command' => self::PLAN, 'matcher' => null],
    ];

    public static function wire(string $path): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $before = json_encode($settings);

        $hooks = self::stripOurs(is_array($settings['hooks'] ?? null) ? $settings['hooks'] : []);

        foreach (self::HOOKS as $hook) {
            $group = ['hooks' => [['type' => 'command', 'command' => $hook['command'] . ' ' . self::STAMP]]];

            if ($hook['matcher'] !== null) {
                $group = ['matcher' => $hook['matcher']] + $group;
            }

            $hooks[$hook['event']][] = $group;
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
