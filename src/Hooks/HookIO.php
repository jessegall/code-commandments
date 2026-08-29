<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
/**
 * Shared plumbing for hook commands: reads JSON payload from STDIN, emits JSON response to STDOUT,
 * resolves the worktree (git toplevel for worktree scope). Not final — tests substitute it to feed/capture.
 */
class HookIO
{
    /**
     * The hook events that actually carry an `additionalContext` channel. Emitting the shape on any other
     * event is not merely ignored — the harness REJECTS the whole payload as invalid, so the hook fails
     * loudly on every fire. Stated once here, so no handler can re-learn it the hard way (`PreCompact`
     * did: it supports only `decision`/`reason`/`continue`/`systemMessage`).
     *
     * `PreCompact` is absent because it has no CONTEXT channel; it speaks through a different one. Its
     * plain stdout is taken verbatim as the compaction's own `newCustomInstructions` — the channel for the
     * one thing that moment is for, telling the summariser what must survive ({@see
     * HookResponse::instructing}).
     */
    private const array INJECTABLE = [
        'SessionStart', 'Setup', 'SubagentStart', 'UserPromptSubmit', 'UserPromptExpansion',
        'PreToolUse', 'PostToolUse', 'PostToolUseFailure', 'PostToolBatch', 'Stop', 'SubagentStop',
    ];

    public function __construct(private readonly GitFiles $git = new GitFiles) {}

    /**
     * The hook payload the harness pipes on STDIN, or an empty array for a manual CLI run (a TTY,
     * or no data). Never blocks on a terminal.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        if (stream_isatty(STDIN)) {
            return [];
        }

        $data = json_decode((string) stream_get_contents(STDIN), true);

        return is_array($data) ? $data : [];
    }

    /**
     * The worktree the hook is running in — the git toplevel of the current directory, so each worktree
     * is scoped to itself, EXCEPT when that toplevel belongs to another repository. `CLAUDE_PROJECT_DIR`
     * is the harness stating which project this session is; a shell that has stepped into an unrelated
     * checkout must not re-point the session's state at it, or that project's own session files are
     * written under this session's key and its own are left unread. Falls back to cwd outside a repository.
     */
    public function projectRoot(): string
    {
        $cwd = getcwd() ?: '.';
        $root = $this->git->root($cwd);
        $project = getenv('CLAUDE_PROJECT_DIR') ?: null;

        if ($project === null) {
            return $root ?? $cwd;
        }

        return $root !== null && $this->git->belongsTo($root, $project) ? $root : $project;
    }

    public function git(): GitFiles
    {
        return $this->git;
    }

    /**
     * A `Stop` block-and-continue: Claude sees $reason and gets one more turn.
     */
    public function block(string $reason): void
    {
        $this->emit(HookResponse::blocking($reason), 'Stop');
    }

    /**
     * A non-blocking context injection: the tool/turn proceeds; Claude reads $context as context.
     * When $quietly the harness keeps it out of the transcript. Silent on an event with no context
     * channel — better nothing than an invalid payload.
     */
    public function inject(string $event, string $context, bool $quietly = false): void
    {
        if (! in_array($event, self::INJECTABLE, true)) {
            return;
        }

        $this->emit(HookResponse::injecting($context, $quietly), $event);
    }

    /**
     * Hand $response to the harness for $event. The wire shape is the response's own
     * ({@see HookResponse::json}); this only writes it.
     */
    public function emit(HookResponse $response, string $event): void
    {
        fwrite(STDOUT, $response->json($event));
    }
}
