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
    /**
     * Background-task statuses that are FINISHED — work no longer holding the turn open.
     */
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
        return $this->named('agent_id') || $this->named('agent_type');
    }

    /**
     * Does the payload carry a real value under $key? Absent and blank are asked SEPARATELY rather
     * than coalesced into one another: the harness omits a field it has nothing for, and a blank one
     * is a field it filled with nothing — different facts, even where the answer here is the same.
     */
    private function named(string $key): bool
    {
        $value = $this->payload[$key] ?? null;

        return $value !== null && $value !== '';
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
     * The `.jsonl` transcript of this session — the COMPLETE, lossless conversation, which the harness
     * stamps on every hook payload. It is the record: the journal indexes it and reads text from it live
     * rather than keeping a copy, so there is never a second home for a message the transcript already
     * holds. Empty for a manual CLI run.
     */
    public function transcriptPath(): string
    {
        return (string) ($this->payload['transcript_path'] ?? '');
    }

    /**
     * What triggered a compaction — `auto` (the harness ran out of context) or `manual` (the user typed
     * `/compact`). It is also the hook MATCHER for `PreCompact`/`PostCompact`, so a hook that speaks only
     * to automatic compaction binds `new HookBinding('PreCompact', 'auto')` and never fires on the user's
     * own deliberate one.
     */
    public function trigger(): string
    {
        return (string) ($this->payload['trigger'] ?? '');
    }

    /**
     * The summary compaction produced, off a `PostCompact` payload — what the conversation was rewritten
     * INTO. Recorded at the boundary so a later reader can see what the summary claimed, beside what the
     * journal knows actually happened.
     */
    public function compactSummary(): string
    {
        return (string) ($this->payload['compact_summary'] ?? '');
    }

    /**
     * The text of the assistant message that was ending, off a `Stop` payload. The harness supplies it
     * precisely so a Stop hook need not open and parse the transcript — a 65MB read to answer a question
     * about one message.
     */
    public function lastAssistantMessage(): string
    {
        return (string) ($this->payload['last_assistant_message'] ?? '');
    }

    /**
     * What the user just typed, off a `UserPromptSubmit` payload — their OWN words, which is the one thing
     * a compaction summary reliably loses.
     */
    public function prompt(): string
    {
        return (string) ($this->payload['prompt'] ?? '');
    }

    /**
     * The assistant message a `MessageDisplay` flush belongs to. Stable across every flush of the same
     * message, so the flushes of one message accumulate under one key.
     */
    public function messageId(): string
    {
        return (string) ($this->payload['message_id'] ?? '');
    }

    /**
     * The turn a `MessageDisplay` flush belongs to — what groups the messages the agent wrote between one
     * user prompt and the next.
     */
    public function turnId(): string
    {
        return (string) ($this->payload['turn_id'] ?? '');
    }

    /**
     * The newly completed lines of an assistant message since the previous `MessageDisplay` flush. Always
     * whole lines, except on the final flush which may end mid-line.
     */
    public function delta(): string
    {
        return (string) ($this->payload['delta'] ?? '');
    }

    /**
     * Is this the LAST flush of its message? Exactly one flush per message carries it, and it is the
     * end-of-message signal REGARDLESS of the delta — a message ending on a newline has an empty final
     * delta, so emptiness is not the test.
     */
    public function isFinalFlush(): bool
    {
        return $this->flag('final');
    }

    /**
     * Is this the FIRST flush of its message? A delta carries only the lines completed since the previous
     * flush, so a message's opening — its {@see \JesseGall\CodeCommandments\Cli\Journal\Tag} prefix and
     * its first line — exists in this flush alone. A long message reaches its `final` flush having long
     * since streamed the part that says what it is.
     */
    public function isFirstFlush(): bool
    {
        return ($this->payload['index'] ?? null) === 0;
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
     * The to-do list a `TodoWrite` call wrote ({@see TodoList}) — empty for every other tool.
     */
    public function todos(): TodoList
    {
        return TodoList::from($this->payload['tool_input']['todos'] ?? []);
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
