<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Until;

use JesseGall\CodeCommandments\Cli\MarkerFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The conditions the user asked the agent to satisfy before it may stop — the state behind the
 * `until` Stop gate ({@see \JesseGall\CodeCommandments\Hooks\Handlers\UntilReminder}). The plan-free
 * sibling of {@see \JesseGall\CodeCommandments\Cli\Plan\PlanMarker}: no plan has to be active, the
 * user simply says "keep going until X" and the agent records X here. Conditions are keyed by a
 * STABLE id (never renumbered or reused while the gate stands, so batched `met` calls can't strike
 * the wrong one — #399); the gate lifts only when none remain ({@see met} strikes one off,
 * {@see clear} drops them all). {@see blocks}
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
     * Record one more condition and return its STABLE id — the handle `until met <n>` takes. Ids are
     * never renumbered or reused while the gate stands, so a batch of `met` calls read off one `list`
     * can never strike the wrong condition (#399). A condition is stored as a single line (any
     * newlines in it collapse to spaces).
     */
    public function add(string $condition): int
    {
        $conditions = $this->all();
        $id = $this->lastId() + 1;
        $conditions[$id] = $this->oneLine($condition);

        $this->save($conditions, $id, blocks: 0, stuck: false); // A new condition is a fresh gate:
        // never inherit the block count of the one before it.

        return $id;
    }

    /**
     * @return array<int, string>  every condition still in force, in the order they were set, keyed
     *                             by its STABLE id (ids keep their gaps once one is struck off)
     */
    public function all(): array
    {
        return $this->parse()['conditions'];
    }

    public function isOpen(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Strike the condition with stable id $id off as satisfied and return its text — null when no
     * condition carries that id (already met, or never existed). The surviving conditions KEEP their
     * ids, so the numbers a `list` showed stay valid across strikes. Meeting one is progress, so the
     * block count resets.
     */
    public function met(int $id): ?string
    {
        $conditions = $this->all();

        if (! array_key_exists($id, $conditions)) {
            return null;
        }

        $condition = $conditions[$id];
        unset($conditions[$id]);

        $this->save($conditions, $this->lastId(), blocks: 0, stuck: false);

        return $condition;
    }

    /**
     * Count one held stop and return the running total. Reset by {@see add}/{@see met} — the counter
     * measures stops WITHOUT progress, so only a genuinely spinning agent reaches the cap.
     */
    public function recordBlock(): int
    {
        $blocks = $this->blocks() + 1;
        $this->save($this->all(), $this->lastId(), $blocks, stuck: false); // Holding a stop consumes the stuck signal.

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
        $this->save($this->all(), $this->lastId(), $this->blocks(), stuck: true);
    }

    public function isStuck(): bool
    {
        return $this->file->value(1, '0') === '1';
    }

    public function clearStuck(): void
    {
        $this->save($this->all(), $this->lastId(), $this->blocks(), stuck: false);
    }

    /**
     * Drop every condition — the gate is gone and stops stand on their own again.
     */
    public function clear(): void
    {
        $this->file->delete();
    }

    /**
     * The highest id ever handed out while this gate stands — the next {@see add} continues from it,
     * so a struck-off condition's id is never reused for a different condition.
     */
    private function lastId(): int
    {
        return $this->parse()['lastId'];
    }

    /**
     * The marker decoded: blocks and stuck from the header, then the last-handed-out id and the
     * `id<TAB>text` condition lines. A legacy marker (pre-stable-ids: bare condition texts, no id
     * line) reads back with positional ids — the next save rewrites it in the current format.
     *
     * @return array{lastId: int, conditions: array<int, string>}
     */
    private function parse(): array
    {
        $tail = array_slice($this->file->values(), 2);

        if ($this->isCurrentFormat($tail)) {
            $conditions = [];

            foreach (array_slice($tail, 1) as $line) {
                [$id, $text] = explode("\t", $line, 2);
                $conditions[(int) $id] = $text;
            }

            return ['lastId' => (int) ($tail[0] ?? 0), 'conditions' => $conditions];
        }

        $conditions = [];

        foreach ($tail as $text) {
            if ($text !== '') {
                $conditions[count($conditions) + 1] = $text;
            }
        }

        return ['lastId' => count($conditions), 'conditions' => $conditions];
    }

    /**
     * Does this tail follow the current format — a numeric last-id line, then only `id<TAB>text`
     * lines? Anything else is a legacy marker still holding bare condition texts.
     *
     * @param  list<string>  $tail
     */
    private function isCurrentFormat(array $tail): bool
    {
        if ($tail === []) {
            return true;
        }

        if (! ctype_digit($tail[0])) {
            return false;
        }

        foreach (array_slice($tail, 1) as $line) {
            $parts = explode("\t", $line, 2);

            if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $conditions  keyed by stable id
     */
    private function save(array $conditions, int $lastId, int $blocks, bool $stuck): void
    {
        if ($conditions === []) {
            $this->clear(); // No conditions IS no gate — never leave an empty marker behind.

            return;
        }

        $lines = [(string) $blocks, $stuck ? '1' : '0', (string) $lastId];

        foreach ($conditions as $id => $text) {
            $lines[] = "{$id}\t{$text}";
        }

        $this->file->write($lines, self::EXPLANATION);
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
        the separator are: the consecutive held-stop count, a one-shot stuck flag, the last condition id
        handed out, then one `id<TAB>condition` per line. Ids are STABLE — striking a condition off never
        renumbers the rest. While any condition stands, the Stop hook blocks and tells the agent to verify
        it; the agent strikes one off with `commandments until met <n>` and the gate lifts when none are
        left. Safe to delete — deleting it simply lifts the gate.
        TXT;
}
