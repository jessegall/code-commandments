<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * The SINGLE source of truth for the package's Claude Code hook wiring
 * (`.claude/settings.json`). Both runners use it so the artisan (`php artisan
 * commandments:…`) and standalone (`vendor/bin/commandments …`) variants can
 * never drift:
 *
 *  - `install-hooks` / `init` build the wiring with {@see self::build()}.
 *  - `sync` RE-ASSERTS it with {@see self::reassert()} on every package update,
 *    so a NEW hook the package adds (e.g. handoff-detect) reaches existing
 *    consumers automatically — the same guarantee skills, scaffold, and the
 *    .gitignore block already have. Without this, a new SessionStart/Stop hook
 *    was dead-on-arrival until the consumer manually re-ran install-hooks.
 *
 * The merge is idempotent ({@see HookConfigMerger}): our entries are added only
 * when absent, and hand-added hooks are never removed.
 */
final class ClaudeHooksInstaller
{
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_NO_SETTINGS = 'no_settings';
    public const STATUS_WRITE_FAILED = 'write_failed';

    /** The artisan runner: subcommands are colon-suffixed (`php artisan commandments:judge`). */
    public const ARTISAN = ['php artisan commandments', ':'];

    /** The standalone runner: subcommands are space-separated (`vendor/bin/commandments judge`). */
    public const STANDALONE = ['vendor/bin/commandments', ' '];

    /**
     * Build the hook events the package owns for a given runner.
     *
     * @param  string  $binary  the runner prefix (e.g. `php artisan commandments`)
     * @param  string  $sep  subcommand separator (`:` for artisan, ` ` for standalone)
     * @return array<string, list<array<string, mixed>>>
     */
    public static function build(string $binary, string $sep, bool $planLoop): array
    {
        $run = static fn (string $sub, string $tail = ' 2>/dev/null || true'): array => [
            'type' => 'command',
            'command' => $binary . $sep . $sub . $tail,
        ];

        $config = [
            'SessionStart' => [
                [
                    'hooks' => [
                        $run('scripture'),
                        $run('reports --check'),
                        $run('scaffold --auto'),
                        $run('install-skills --auto'),
                        $run('skills'),
                        // Binary-independent shell hook — offers a resume when a
                        // HANDOFF.md is present.
                        ['type' => 'command', 'command' => 'sh .claude/hooks/handoff-detect.sh 2>/dev/null || true'],
                    ],
                ],
            ],
            'Stop' => [
                [
                    'hooks' => [
                        $run('judge --git', ' 2>/dev/null; exit 0'),
                    ],
                ],
            ],
            'PostToolUse' => [
                [
                    'matcher' => 'Bash',
                    'hooks' => [
                        ['type' => 'command', 'command' => self::postCommitReminderCommand($binary, $sep)],
                    ],
                ],
            ],
        ];

        // Opt-in plan-loop suite: drives an approved plan to completion. When on,
        // phase-committed.sh supersedes the inline post-commit reminder.
        if ($planLoop) {
            $config['PreToolUse'] = PlanLoopHookSuite::preToolUseEntries();
            $config['Stop'][] = PlanLoopHookSuite::stopEntry();
            $config['PostToolUse'] = PlanLoopHookSuite::postToolUseEntries();
        }

        return $config;
    }

    /**
     * A PostToolUse (Bash) hook command: when the tool call was a git commit,
     * inject a reminder to re-read the commandments and resolve every sin.
     */
    public static function postCommitReminderCommand(string $binary, string $sep): string
    {
        $message = 'A commit just landed — a phase is complete. Re-read the Code Commandments '
            . 'section of CLAUDE.md now and act as a sin resolver: run `' . $binary . $sep . 'judge --next --git` '
            . 'and handle every finding before starting the next phase. Fix each sin — even pre-existing ones in '
            . 'files you touched. Warnings: default to FIXING; absolve only when the rubric LEAVE-WHEN genuinely '
            . 'applies, with a reason. Absolve is not a dismiss button. I did not cause this is never a reason to '
            . 'leave a sin in place.';

        $json = '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"' . $message . '"}}';

        return 'in=$(cat); printf "%s" "$in" | grep -q "git commit" && printf '
            . escapeshellarg($json) . '; exit 0';
    }

    /**
     * Re-assert our hooks into `$basePath/.claude/settings.json`, idempotently
     * (new entries added, existing/hand-added ones preserved). Only acts when a
     * settings.json already exists — a routine sync must not impose Claude hooks
     * on a consumer that never opted into them; first-time wiring stays
     * install-hooks/init's job.
     *
     * @param  array{0: string, 1: string}  $runner  one of self::ARTISAN / self::STANDALONE
     */
    public static function reassert(string $basePath, array $runner, bool $planLoop): string
    {
        $file = rtrim($basePath, '/') . '/.claude/settings.json';

        if (! is_file($file)) {
            return self::STATUS_NO_SETTINGS;
        }

        $settings = json_decode((string) @file_get_contents($file), true);

        if (! is_array($settings)) {
            $settings = [];
        }

        $before = $settings['hooks'] ?? [];
        $after = self::apply($before, $runner, $planLoop);

        if ($after === $before) {
            return self::STATUS_UNCHANGED;
        }

        $settings['hooks'] = $after;
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || @file_put_contents($file, $json . T_String::NEWLINE) === false) {
            return self::STATUS_WRITE_FAILED;
        }

        return self::STATUS_INSTALLED;
    }

    /**
     * Reconcile an existing hooks config to the CURRENT package wiring: every
     * entry the package OWNS is dropped and replaced with the freshly-built set,
     * so a changed/renamed owned hook updates cleanly and a removed one (e.g. the
     * plan-loop suite when its config is turned off) disappears — while EVERY hook
     * the consumer added is preserved untouched. This is what both install-hooks/
     * init and `sync` apply, so the wiring is always the latest version.
     *
     * @param  array<string, list<array<string, mixed>>>  $existing
     * @param  array{0: string, 1: string}  $runner  self::ARTISAN / self::STANDALONE
     * @return array<string, list<array<string, mixed>>>
     */
    public static function apply(array $existing, array $runner, bool $planLoop): array
    {
        $ours = self::build($runner[0], $runner[1], $planLoop);
        $result = $existing;

        $events = array_values(array_unique([...array_keys($existing), ...array_keys($ours)]));

        foreach ($events as $event) {
            // Keep only the consumer's own entries; drop everything we own…
            $kept = array_values(array_filter(
                $existing[$event] ?? [],
                static fn (array $entry): bool => ! self::entryIsOwned($entry),
            ));

            // …then append our current owned set for this event.
            $merged = [...$kept, ...($ours[$event] ?? [])];

            if ($merged === []) {
                unset($result[$event]);
            } else {
                $result[$event] = $merged;
            }
        }

        return $result;
    }

    /**
     * Whether an entry is entirely package-owned (so it is safe to replace). True
     * only when it has commands and EVERY one is recognisably ours — a hand-added
     * or mixed entry is treated as the consumer's and left alone.
     *
     * @param  array<string, mixed>  $entry
     */
    private static function entryIsOwned(array $entry): bool
    {
        $commands = [];

        foreach (($entry['hooks'] ?? []) as $hook) {
            if (isset($hook['command']) && is_string($hook['command'])) {
                $commands[] = $hook['command'];
            }
        }

        if ($commands === []) {
            return false;
        }

        foreach ($commands as $command) {
            if (! self::isOwnedCommand($command)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a hook command is one the package owns — it invokes the commandments
     * runner, or one of the package's own hook scripts.
     */
    private static function isOwnedCommand(string $command): bool
    {
        if (str_contains(strtolower($command), 'commandments')) {
            return true;
        }

        foreach (self::OWNED_SCRIPTS as $script) {
            if (str_contains($command, $script)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hook scripts the package ships (and therefore owns) that don't mention the
     * runner in their command string.
     *
     * @var list<string>
     */
    private const OWNED_SCRIPTS = [
        'handoff-detect.sh',
        'handoff.sh',
        'resume.sh',
        'plan-approved.sh',
        'plan-start.sh',
        'plan-release.sh',
        'keep-going.sh',
        'guard-plan-marker.sh',
        'phase-committed.sh',
    ];
}
