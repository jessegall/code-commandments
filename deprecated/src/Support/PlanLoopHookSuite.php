<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The opt-in "plan-loop" hook suite — an autonomous-continuation harness the
 * package can install into a consumer's `.claude/`. It is OFF by default and
 * only wired into the Claude settings when `commandments.hooks.plan_loop` is
 * true, because a `Stop` hook that refuses to idle-stop is aggressive for a
 * consumer that just wants linting.
 *
 * When enabled it ships its shell scripts into `.claude/hooks/` and registers:
 *   - PreToolUse(Bash) → guard-plan-marker.sh: blocks hand-deleting the marker.
 *   - PostToolUse ExitPlanMode → plan-approved.sh: arms the loop + a /loop net.
 *   - PostToolUse Bash        → phase-committed.sh: after a commit, resolve
 *                       sins AND refresh the plan-progress memory.
 *
 * The scripts are framework-agnostic (pure `sh`, keyed off `git rev-parse
 * --show-toplevel`), so the same suite serves Laravel and standalone consumers.
 * The runtime marker (`.claude/plan-active`) is kept out of git by
 * {@see GitignoreInstaller}.
 */
final class PlanLoopHookSuite
{
    /**
     * The packaged hook scripts, installed verbatim into `.claude/hooks/`.
     *
     * @var list<string>
     */
    public const SCRIPTS = [
        'plan-approved.sh',
        'phase-committed.sh',
        'plan-start.sh',
        'plan-release.sh',
        'guard-plan-marker.sh',
        'plan-session-reset.sh',
    ];

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_WRITE_FAILED = 'write_failed';

    /**
     * Whether the plan-loop is opted in via config.
     *
     * @param  array<string, mixed>  $config  the loaded `commandments` config array
     */
    public static function enabled(array $config): bool
    {
        $hooks = $config['hooks'] ?? [];

        return is_array($hooks) && (bool) ($hooks['plan_loop'] ?? false);
    }

    /**
     * The SessionStart entry — clears a stale plan marker on a genuinely NEW
     * session so a previous session's plan doesn't silently auto-continue (the
     * script itself no-ops on resume/compact, preserving an in-flight plan).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sessionStartEntries(): array
    {
        return [
            ['hooks' => [self::command('sh .claude/hooks/plan-session-reset.sh')]],
        ];
    }

    /**
     * The PreToolUse entry (guards the marker against hand-removal).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function preToolUseEntries(): array
    {
        return [
            ['matcher' => 'Bash', 'hooks' => [self::command('sh .claude/hooks/guard-plan-marker.sh')]],
        ];
    }

    /**
     * The PostToolUse entries. plan-approved (arm the loop on ExitPlanMode) is
     * always present; phase-committed (the per-commit sin-resolver nudge) is the
     * PER-PHASE JUDGE — omitted under a deferred-cadence profile like grind, which
     * reckons once at the end and must NOT judge after each commit.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function postToolUseEntries(bool $judgeEachPhase = true): array
    {
        $entries = [
            ['matcher' => 'ExitPlanMode', 'hooks' => [self::command('sh .claude/hooks/plan-approved.sh')]],
        ];

        if ($judgeEachPhase) {
            $entries[] = ['matcher' => 'Bash', 'hooks' => [self::command('sh .claude/hooks/phase-committed.sh')]];
        }

        return $entries;
    }

    /**
     * Copy the packaged scripts into `$basePath/.claude/hooks/` (idempotent,
     * executable). Returns a status string.
     */
    public static function install(string $basePath): string
    {
        $target = rtrim($basePath, '/') . '/.claude/hooks';

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return self::STATUS_WRITE_FAILED;
        }

        foreach (self::SCRIPTS as $script) {
            $source = self::stubsDir() . '/' . $script;
            $contents = @file_get_contents($source);

            if ($contents === false || @file_put_contents($target . '/' . $script, $contents) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($target . '/' . $script, 0755);
        }

        return self::STATUS_INSTALLED;
    }

    /**
     * Overwrite ONLY the suite scripts that already exist in the consumer's
     * `.claude/hooks/` with the current shipped versions — never adds a script
     * the consumer did not already have. Used on a package update to refresh a
     * stale/orphaned copy (e.g. a consumer that once had the plan-loop installed
     * but no longer opts in via config). Returns how many files were refreshed.
     */
    public static function refreshExisting(string $basePath): int
    {
        $dir = rtrim($basePath, '/') . '/.claude/hooks';
        $refreshed = 0;

        foreach (self::SCRIPTS as $script) {
            $target = $dir . '/' . $script;

            if (! is_file($target)) {
                continue;
            }

            $contents = @file_get_contents(self::stubsDir() . '/' . $script);

            if ($contents !== false && @file_put_contents($target, $contents) !== false) {
                @chmod($target, 0755);
                $refreshed++;
            }
        }

        return $refreshed;
    }

    private static function stubsDir(): string
    {
        return dirname(__DIR__, 2) . '/stubs/hooks';
    }

    /**
     * @return array{type: string, command: string}
     */
    private static function command(string $command): array
    {
        return ['type' => 'command', 'command' => $command];
    }
}
