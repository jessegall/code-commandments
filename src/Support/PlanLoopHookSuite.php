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
 * When enabled it ships six shell scripts into `.claude/hooks/` and registers:
 *   - PreToolUse(Bash) → guard-plan-marker.sh: blocks hand-deleting the marker.
 *   - Stop            → keep-going.sh: drives an approved plan to completion
 *                       (worktree-keyed marker, 200-continuation backstop).
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
        'keep-going.sh',
        'phase-committed.sh',
        'plan-start.sh',
        'plan-release.sh',
        'guard-plan-marker.sh',
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
     * The Stop entry (auto-continue an approved plan).
     *
     * @return array<string, mixed>
     */
    public static function stopEntry(): array
    {
        return ['hooks' => [self::command('sh .claude/hooks/keep-going.sh')]];
    }

    /**
     * The PostToolUse entries — replaces the inline post-commit reminder, since
     * phase-committed.sh does the sin-resolver nudge AND the plan-progress memory.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function postToolUseEntries(): array
    {
        return [
            ['matcher' => 'ExitPlanMode', 'hooks' => [self::command('sh .claude/hooks/plan-approved.sh')]],
            ['matcher' => 'Bash', 'hooks' => [self::command('sh .claude/hooks/phase-committed.sh')]],
        ];
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
