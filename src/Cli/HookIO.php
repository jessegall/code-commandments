<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * The plumbing every Claude Code hook command shares: read the JSON payload the harness pipes on
 * STDIN, emit a JSON response on STDOUT, and resolve the WORKTREE the hook is running in. Written
 * once here and reused by {@see Remind}, {@see JudgeReminder}, and {@see PlanReminder}, so the hook
 * mechanics never diverge between them.
 *
 * The worktree resolution is the load-bearing bit: `CLAUDE_PROJECT_DIR` is pinned to the main
 * checkout and SHARED across every worktree, so anchoring state to it makes a fresh worktree read
 * the main checkout's state and share its markers. Resolving the git toplevel of the current
 * directory instead gives each worktree its OWN root — so a plan running in one worktree never
 * nudges or clobbers another.
 *
 * Not final: it is the IO seam a test substitutes to feed a payload and capture emissions
 * ({@see \JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO}), instead of driving STDIN/STDOUT.
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
