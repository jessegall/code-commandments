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
}
