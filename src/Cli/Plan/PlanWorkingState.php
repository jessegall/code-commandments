<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

/**
 * The living WORKING-STATE record for the active plan — the agent-authored note that survives context
 * compaction. It captures ONLY what `git log` + the plan can't reconstruct: decisions and their rejected
 * alternatives, plan changes agreed in conversation, hard-won gotchas, and the exact next step, over a
 * done/doing/next cursor. The agent WRITES it (a normal file at {@see path}); this class only reads and
 * clears it. Worktree-scoped like {@see PlanMarker}, and — unlike the other markers — it must survive
 * `compact`/`resume` (it exists precisely to be re-read then), so {@see SessionReset} wipes it only on a
 * genuinely-new session.
 */
final class PlanWorkingState
{
    public function __construct(private readonly string $path) {}

    public static function inWorktree(string $root): self
    {
        return new self($root . '/.commandments/.plan-working-state');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * The record's content, or '' when the agent hasn't written one yet.
     */
    public function read(): string
    {
        return $this->exists() ? trim((string) file_get_contents($this->path)) : '';
    }

    public function clear(): void
    {
        @unlink($this->path);
    }

    /**
     * The archive path — the previous plan's record, kept for reference across a re-plan.
     */
    public function previousPath(): string
    {
        return $this->path . '.previous';
    }

    /**
     * Preserve the current record as `.previous` (replacing any earlier archive) instead of losing it, so a
     * re-plan that resets the plan state keeps the compaction lifeline: the agent can still read the prior
     * plan's decisions. No-op when there is nothing to archive.
     */
    public function archive(): void
    {
        if ($this->exists()) {
            @rename($this->path, $this->previousPath());
        }
    }
}
