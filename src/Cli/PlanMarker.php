<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The per-worktree record that a plan is being executed — the state behind the keep-going Stop
 * hook. Written when a plan is approved ({@see PlanReminder}), read on every stop to decide whether
 * to re-nudge, and cleared by `commandments plan done` ({@see PlanCommand}) or when the plan branch
 * is merged back to its base. It lives under the worktree's OWN `.commandments/`, so one worktree's
 * plan never nudges another. It stores ONLY the {@see PlanState} counters — nothing config-derived,
 * so the base branch/policy stay live from config. The file format mirrors the other hook markers:
 * value lines, a separator, then a self-describing explanation.
 */
final class PlanMarker
{
    private const string SEPARATOR = '-----';

    public function __construct(private readonly string $path) {}

    public static function inWorktree(string $root): self
    {
        return new self($root . '/.commandments/.plan-active');
    }

    /**
     * Record that a plan is now active at $head, with the nudge counters reset.
     */
    public function activate(string $head): void
    {
        $this->save(new PlanState($head, 0, 0));
    }

    public function isActive(): bool
    {
        return is_file($this->path);
    }

    /**
     * Count one keep-going nudge at $currentHead and return the fresh {@see PlanState}.
     */
    public function recordNudge(string $currentHead): PlanState
    {
        $state = $this->state()->nudged($currentHead);
        $this->save($state);

        return $state;
    }

    public function clear(): void
    {
        @unlink($this->path);
    }

    /**
     * The persisted {@see PlanState}, or an empty one when there is no marker (or it's truncated
     * below its four value lines) — absence is modelled as the empty state, never patched per-field.
     */
    private function state(): PlanState
    {
        $lines = $this->valueLines();

        if (count($lines) < 3) {
            return new PlanState('', 0, 0);
        }

        return new PlanState($lines[0], (int) $lines[1], (int) $lines[2]);
    }

    /**
     * The value lines above the {@see SEPARATOR}, or [] when no marker file exists.
     *
     * @return list<string>
     */
    private function valueLines(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $lines = [];

        foreach (preg_split('/\R/', (string) file_get_contents($this->path)) ?: [] as $line) {
            if ($line === self::SEPARATOR) {
                break;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function save(PlanState $state): void
    {
        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, implode("\n", [
            $state->head,
            (string) $state->stuck,
            (string) $state->total,
            self::SEPARATOR,
            self::EXPLANATION,
        ]) . "\n");
    }

    private const string EXPLANATION = <<<'TXT'
        Active-plan marker for the code-commandments keep-going Stop hook (`commandments plan-reminder`).
        The value lines above the separator are: the HEAD at the last nudge, the consecutive no-progress
        nudge count, and the total nudge count. Written when a plan is approved, read on every stop,
        cleared by `commandments plan done` or when the branch merges back. Safe to delete — deleting it
        simply ends the keep-going nudges for this plan.
        TXT;
}
