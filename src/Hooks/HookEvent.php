<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Workspace;

/**
 * One Claude Code hook invocation — the JSON payload the harness delivered, paired with the
 * worktree it fired in ({@see HookIO::projectRoot}). It reads the payload semantically so a
 * {@see Hook} never pokes at raw array keys: the event name it dispatches on, the tool a
 * `Pre`/`PostToolUse` concerns, the shell command a `Bash` call is about to run, and the boolean
 * flags (e.g. `stop_hook_active`). A bare CLI run (no payload) reports an empty event name.
 */
final class HookEvent
{
    /** Background-task statuses that are FINISHED — work no longer holding the turn open. */
    private const array SETTLED_STATUSES = ['completed', 'done', 'failed', 'cancelled', 'canceled', 'error'];

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
     * The Claude Code session this hook fired in, or '' for a manual CLI run.
     */
    public function sessionId(): string
    {
        return (string) ($this->payload['session_id'] ?? '');
    }

    /**
     * This event's {@see Workspace} — where every session-scoped state file for this hook lives.
     * The payload's `session_id` is authoritative; {@see Workspace::at} falls back to the
     * `CLAUDE_CODE_SESSION_ID` env var and then the shared `default` folder.
     */
    public function workspace(): Workspace
    {
        return Workspace::at($this->root, $this->sessionId() ?: null);
    }

    /**
     * Did this hook fire inside a spawned SUBAGENT (a `Task`/Explore agent) rather than the main coding
     * session? Claude Code stamps the payload with `agent_id`/`agent_type` when the invocation belongs to a
     * subagent; the main session carries neither. Our hooks — working-state injection, the judge/cardinal-rule
     * reminders, the plan and skill-invocation nudges — are meaningful ONLY to the session that owns the plan
     * and working state; in a read-only subagent they are noise that can derail its task, so every moment is
     * suppressed there. Absent fields (older Claude Code, a manual CLI run) read as the main session — the
     * guard is additive and never changes existing behaviour.
     */
    public function isSubagent(): bool
    {
        return ($this->payload['agent_id'] ?? '') !== '' || ($this->payload['agent_type'] ?? '') !== '';
    }

    /**
     * Is the session in PLAN MODE — the agent is researching and drafting a plan, not executing one?
     * Claude Code stamps hook payloads with `permission_mode` (`plan`, `default`, `acceptEdits`, …).
     * A Stop in plan mode is the agent presenting its plan for the user's approval; holding or nudging
     * that stop would fight the approval flow, so every Stop handler stays silent then ({@see
     * Hook::handle}). Absent field (older Claude Code, a manual CLI run) reads as not-plan-mode — the
     * guard is additive and never changes existing behaviour.
     */
    public function isPlanMode(): bool
    {
        return ($this->payload['permission_mode'] ?? '') === 'plan';
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
     * What triggered a `SessionStart` — `startup` (a fresh launch), `clear` (`/clear`), `resume`
     * (continuing an existing session), or `compact` (context compaction re-fires SessionStart). Only
     * `startup`/`clear` are a genuinely-new session; `resume`/`compact` continue a live one, so the
     * fresh-session cleanup skips them. Empty for a non-SessionStart event or a manual run.
     */
    public function source(): string
    {
        return (string) ($this->payload['source'] ?? '');
    }

    /**
     * The shell command a `Bash` tool call is about to run (empty for other tools).
     */
    public function command(): string
    {
        return (string) ($this->payload['tool_input']['command'] ?? '');
    }

    /**
     * The file an `Edit`/`Write`/`MultiEdit` tool call targets (empty for other tools) — the `file_path`
     * in the payload's `tool_input`.
     */
    public function filePath(): string
    {
        return (string) ($this->payload['tool_input']['file_path'] ?? '');
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
}
