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
     * The worktree the hook is running in — the git toplevel of the current directory, so each
     * worktree is scoped to itself. Falls back to `CLAUDE_PROJECT_DIR` / cwd outside a repository.
     */
    public function projectRoot(): string
    {
        $cwd = getcwd() ?: '.';

        return $this->git->root($cwd) ?? (getenv('CLAUDE_PROJECT_DIR') ?: $cwd);
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
        $this->emit(['decision' => 'block', 'reason' => $reason]);
    }

    /**
     * A non-blocking context injection: the tool/turn proceeds; Claude reads $context as context.
     */
    public function inject(string $event, string $context): void
    {
        $this->emit(['hookSpecificOutput' => [
            'hookEventName' => $event,
            'additionalContext' => $context,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function emit(array $payload): void
    {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
