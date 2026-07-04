<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * One Claude Code hook invocation — the JSON payload the harness delivered, paired with the
 * worktree it fired in ({@see HookIO::projectRoot}). It reads the payload semantically so a
 * {@see Hook} never pokes at raw array keys: the event name it dispatches on, the tool a
 * `Pre`/`PostToolUse` concerns, the shell command a `Bash` call is about to run, and the boolean
 * flags (e.g. `stop_hook_active`). A bare CLI run (no payload) reports an empty event name.
 */
final class HookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
        public readonly string $root,
    ) {}

    /**
     * The hook event (`PostToolUse`, `PreToolUse`, `Stop`, …), or '' for a manual CLI run.
     */
    public function name(): string
    {
        return (string) ($this->payload['hook_event_name'] ?? '');
    }

    /**
     * The tool a `Pre`/`PostToolUse` event concerns (`Bash`, `ExitPlanMode`, …).
     */
    public function tool(): string
    {
        return (string) ($this->payload['tool_name'] ?? '');
    }

    public function isTool(string $tool): bool
    {
        return $this->tool() === $tool;
    }

    /**
     * The shell command a `Bash` tool call is about to run (empty for other tools).
     */
    public function command(): string
    {
        return (string) ($this->payload['tool_input']['command'] ?? '');
    }

    /**
     * A boolean flag on the payload (e.g. `stop_hook_active`), false when absent.
     */
    public function flag(string $key): bool
    {
        return ($this->payload[$key] ?? null) === true;
    }

    /**
     * Is the agent stopping only to wait on background work — a `run_in_background` task or a subagent
     * that will auto-resume it — rather than genuinely ending its turn? True when the Stop payload's
     * `background_tasks` holds a task with a non-terminal status (an unknown or missing one counts as
     * active); a `Stop` hook stays silent then.
     */
    public function hasPendingBackgroundWork(): bool
    {
        $tasks = $this->payload['background_tasks'] ?? [];

        if (! is_array($tasks)) {
            return false;
        }

        foreach ($tasks as $task) {
            $status = is_array($task) ? ($task['status'] ?? null) : null;

            if (! in_array($status, self::SETTLED_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /** Background-task statuses that are FINISHED — work no longer holding the turn open. */
    private const array SETTLED_STATUSES = ['completed', 'done', 'failed', 'cancelled', 'canceled', 'error'];
}
