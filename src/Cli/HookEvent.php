<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

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
     * Is the agent stopping ONLY to WAIT on background work — a `run_in_background` task or a subagent
     * that will auto-resume it — rather than genuinely ending its turn? A `Stop` hook must stay silent
     * then: a parked-waiting agent isn't done, and nudging or blocking it is noise (and the block cap
     * would burn on stops it doesn't control). Keyed on the `background_tasks` the harness reports pending
     * on the Stop payload; absent/empty ⇒ a real stop, so behaviour is unchanged when the field isn't sent.
     */
    public function hasPendingBackgroundWork(): bool
    {
        $tasks = $this->payload['background_tasks'] ?? null;

        return is_array($tasks) && $tasks !== [];
    }
}
