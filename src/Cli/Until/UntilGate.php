<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Until;

use JesseGall\CodeCommandments\Cli\MarkerFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The conditions the user asked the agent to satisfy before it may stop — the state behind the
 * `until` Stop gate ({@see \JesseGall\CodeCommandments\Hooks\Handlers\UntilReminder}). The plan-free
 * sibling of {@see \JesseGall\CodeCommandments\Cli\Plan\PlanMarker}: no plan has to be active, the
 * user simply says "keep going until X" and the agent records X here. Conditions are a list; the gate
 * lifts only when it is empty ({@see met} strikes one off, {@see clear} drops them all). {@see blocks}
 * counts the consecutive stops the gate has held so a wedged agent is released instead of looped
 * forever — striking a condition off is progress and resets it. Session-scoped like every other
 * marker, so one session's gate never holds another's stop, and written in the shared
 * {@see MarkerFile} format.
 */
final class UntilGate
{
    public function __construct(private readonly MarkerFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new MarkerFile($workspace->path('.until')));
    }

    /**
     * Record one more condition and return its 1-based number — the handle `until met <n>` takes.
     * A condition is stored as a single line (any newlines in it collapse to spaces).
     */
    public function add(string $condition): int
    {
        $conditions = $this->all();
        $conditions[] = $this->oneLine($condition);

        $this->save($conditions, blocks: 0, stuck: false); // A new condition is a fresh gate: never
        // inherit the block count of the one before it.

        return count($conditions);
    }

    /**
     * @return list<string>  every condition still in force, in the order they were set
     */
    public function all(): array
    {
        return array_slice($this->file->values(), 2);
    }

    public function isOpen(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Strike the $number-th condition off as satisfied and return its text — null when there is no
     * such condition. Meeting one is progress, so the block count resets.
     */
    public function met(int $number): ?string
    {
        $conditions = $this->all();
        $index = $number - 1;

        if (! array_key_exists($index, $conditions)) {
            return null;
        }

        $condition = $conditions[$index];
        unset($conditions[$index]);

        $this->save(array_values($conditions), blocks: 0, stuck: false);

        return $condition;
    }

    /**
     * Count one held stop and return the running total. Reset by {@see add}/{@see met} — the counter
     * measures stops WITHOUT progress, so only a genuinely spinning agent reaches the cap.
     */
    public function recordBlock(): int
    {
        $blocks = $this->blocks() + 1;
        $this->save($this->all(), $blocks, stuck: false); // Holding a stop consumes the stuck signal.

        return $blocks;
    }

    public function blocks(): int
    {
        return (int) $this->file->value(0, '0');
    }

    /**
     * Signal that the agent is BLOCKED on a condition it cannot meet: the gate lets the NEXT stop
     * through so the agent can hand back to the user, but the conditions stay in force — the moment
     * it continues, the gate holds again. One-shot, exactly like `plan stuck`.
     */
    public function markStuck(): void
    {
        $this->save($this->all(), $this->blocks(), stuck: true);
    }

    public function isStuck(): bool
    {
        return $this->file->value(1, '0') === '1';
    }

    public function clearStuck(): void
    {
        $this->save($this->all(), $this->blocks(), stuck: false);
    }

    /**
     * Drop every condition — the gate is gone and stops stand on their own again.
     */
    public function clear(): void
    {
        $this->file->delete();
    }

    /**
     * @param  list<string>  $conditions
     */
    private function save(array $conditions, int $blocks, bool $stuck): void
    {
        if ($conditions === []) {
            $this->clear(); // No conditions IS no gate — never leave an empty marker behind.

            return;
        }

        $this->file->write([(string) $blocks, $stuck ? '1' : '0', ...$conditions], self::EXPLANATION);
    }

    /**
     * Flatten a condition to the single line the file format stores — a pasted multi-line condition
     * would otherwise read back as several conditions.
     */
    private function oneLine(string $condition): string
    {
        $parts = [];

        foreach (explode("\n", str_replace("\r", "\n", $condition)) as $part) {
            if (trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return implode(' ', $parts);
    }

    private const string EXPLANATION = <<<'TXT'
        Stop-gate conditions for code-commandments (`commandments until "<condition>"`). The lines above
        the separator are: the consecutive held-stop count, a one-shot stuck flag, then one condition per
        line. While any condition stands, the Stop hook blocks and tells the agent to verify it; the agent
        strikes one off with `commandments until met <n>` and the gate lifts when none are left. Safe to
        delete — deleting it simply lifts the gate.
        TXT;
}
