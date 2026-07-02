<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The per-worktree record that a plan is being executed — the state behind the keep-going Stop
 * hook. Written when a plan is approved ({@see PlanReminder}), read on every stop to decide whether
 * to re-nudge, and cleared by `commandments plan done` ({@see PlanCommand}) or when the plan branch
 * is merged back to its base. It lives under the worktree's OWN `.commandments/`, so one worktree's
 * plan never nudges another.
 *
 * It also carries the loop-safety state. Two counters distinguish a productive plan from a stuck
 * one: `total` (every nudge, so {@see StopPolicy::RespectUserStops} can nudge exactly once) and
 * `stuck` (consecutive nudges with no new commit — it resets the moment HEAD moves, so a plan that
 * keeps committing is never capped, while an agent spinning without progress is). Format mirrors
 * the other hook markers: value lines, a separator, then a self-describing explanation.
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
     * Record that a plan is now active, cut from $baseBranch at $head. Resets the nudge counters.
     */
    public function activate(string $baseBranch, string $head): void
    {
        $this->write(['base' => $baseBranch, 'head' => $head, 'stuck' => 0, 'total' => 0]);
    }

    public function isActive(): bool
    {
        return is_file($this->path);
    }

    /**
     * The base branch the active plan was cut from, or '' when no plan is active.
     */
    public function baseBranch(): string
    {
        return (string) ($this->read()['base'] ?? '');
    }

    /**
     * Count one keep-going nudge at the current HEAD and return the fresh counts. `stuck` resets to
     * 1 whenever HEAD has moved since the last nudge (progress was made); otherwise it climbs.
     *
     * @return array{stuck: int, total: int}
     */
    public function recordNudge(string $currentHead): array
    {
        $state = $this->read();
        $progressed = $currentHead !== '' && $currentHead !== ($state['head'] ?? '');

        $stuck = $progressed ? 1 : (int) ($state['stuck'] ?? 0) + 1;
        $total = (int) ($state['total'] ?? 0) + 1;

        $this->write(['base' => (string) ($state['base'] ?? ''), 'head' => $currentHead, 'stuck' => $stuck, 'total' => $total]);

        return ['stuck' => $stuck, 'total' => $total];
    }

    public function clear(): void
    {
        @unlink($this->path);
    }

    /**
     * @return array{base?: string, head?: string, stuck?: int, total?: int}
     */
    private function read(): array
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

        return [
            'base' => $lines[0] ?? '',
            'head' => $lines[1] ?? '',
            'stuck' => (int) ($lines[2] ?? 0),
            'total' => (int) ($lines[3] ?? 0),
        ];
    }

    /**
     * @param  array{base: string, head: string, stuck: int, total: int}  $state
     */
    private function write(array $state): void
    {
        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, implode("\n", [
            $state['base'],
            $state['head'],
            (string) $state['stuck'],
            (string) $state['total'],
            self::SEPARATOR,
            self::EXPLANATION,
        ]) . "\n");
    }

    private const string EXPLANATION = <<<'TXT'
        Active-plan marker for the code-commandments keep-going Stop hook (`commandments plan-reminder`).
        The value lines above the separator are: the plan's base branch, the HEAD at the last nudge, the
        consecutive no-progress nudge count, and the total nudge count. Written when a plan is approved,
        read on every stop, cleared by `commandments plan done` or when the branch merges back. Safe to
        delete — deleting it simply ends the keep-going nudges for this plan.
        TXT;
}
