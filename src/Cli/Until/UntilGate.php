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
 * forever — striking a condition off is progress and resets it. The user can also set the whole gate
 * aside without losing it: {@see pause} moves the marker to `.until.pause` (nothing holds a stop while
 * it's there — not even a condition {@see add}ed after the pause, which waits there with the rest) and
 * {@see resume} brings every condition back. Session-scoped like every other
 * marker, so one session's gate never holds another's stop, and written in the shared
 * {@see MarkerFile} format.
 */
final class UntilGate
{
    private const string EXPLANATION = <<<'TXT'
        Stop-gate conditions for code-commandments (`commandments until "<condition>"`). The lines above
        the separator are: the consecutive held-stop count, a one-shot stuck flag, the last condition id
        handed out, then one `id<TAB>condition` per line. Ids are STABLE — striking a condition off never
        renumbers the rest. While any condition stands, the Stop hook blocks and tells the agent to verify
        it; the agent strikes one off with `commandments until met <n>` and the gate lifts when none are
        left. Safe to delete — deleting it simply lifts the gate.
        TXT;

    public function __construct(
        private readonly MarkerFile $file,
        private readonly MarkerFile $paused,
    ) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(
            new MarkerFile($workspace->path('.until')),
            new MarkerFile($workspace->path('.until.pause')),
        );
    }

    /**
     * Record one more condition and return its STABLE id — the handle `until met <n>` takes. Ids are
     * never renumbered or reused while the gate stands, so a batch of `met` calls read off one `list`
     * can never strike the wrong condition (#399). A condition is stored as a single line (any
     * newlines in it collapse to spaces).
     *
     * While the gate is PAUSED the condition joins what is set aside and stays paused until
     * {@see resume} — a pause is the user's directive that NOTHING holds, so recording a condition
     * must never silently re-arm the gate behind their back (#418).
     */
    public function add(string $condition): int
    {
        return $this->addTo($this->isPaused() ? $this->paused : $this->file, $condition);
    }

    /**
     * @return array<int, string>  every condition still in force, in the order they were set, keyed
     *                             by its STABLE id (ids keep their gaps once one is struck off)
     */
    public function all(): array
    {
        return $this->conditionsOf($this->file);
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
        $this->paused->delete(); // A cleared gate leaves nothing to resume.
    }

    /**
     * Set the whole gate aside — the marker moves to `.until.pause`, so every condition survives
     * verbatim while NOTHING holds a stop. The user's escape hatch when they want to do something
     * else in between without being sent back to the conditions ({@see resume} brings them back).
     * False when there is no gate standing to pause.
     *
     * Pausing when something is somehow live while the gate already stands aside MERGES into what is
     * set aside — it must never overwrite it, or the earlier conditions would be destroyed silently
     * (#403). {@see add} keeps that state from arising in the first place (#418), so this is how a
     * session upgraded mid-flight is put back together.
     */
    public function pause(): bool
    {
        return $this->handOver($this->file, $this->paused);
    }

    /**
     * Bring a paused gate back into force — every condition set aside holds again. False when nothing
     * is paused. A live marker can only survive from a session that predates #418 (a condition set
     * mid-pause used to re-arm the gate), so the paused ones are folded in behind whatever it holds
     * rather than overwriting it.
     */
    public function resume(): bool
    {
        return $this->handOver($this->paused, $this->file);
    }

    /**
     * Hand every condition $from holds over to $into and drop $from — the ONE merge behind
     * {@see pause} and {@see resume}, so the two directions can never drift apart. A target that does
     * not exist yet is simply taken over; otherwise the target KEEPS its ids and the newcomers get
     * fresh ones behind them, and a condition already on both sides is folded in once (#403). The
     * held-stop count does not survive the move: a gate that changes sides is a fresh gate. False
     * when $from holds nothing to hand over.
     */
    private function handOver(MarkerFile $from, MarkerFile $into): bool
    {
        if (! $from->exists()) {
            return false;
        }

        if (! $into->exists()) {
            return $from->moveTo($into);
        }

        $target = $this->parse($into);
        $conditions = $target['conditions'];
        $lastId = $target['lastId'];

        foreach ($this->conditionsOf($from) as $condition) {
            if (in_array($condition, $conditions, true)) {
                continue;
            }

            $conditions[++$lastId] = $condition;
        }

        $this->write($into, $conditions, $lastId, blocks: 0, stuck: false);
        $from->delete();

        return true;
    }

    public function isPaused(): bool
    {
        return $this->paused->exists();
    }

    /**
     * The conditions a paused gate is holding — what `until list` shows while it stands aside.
     *
     * @return array<int, string>  keyed by the id each condition had when it was paused
     */
    public function pausedConditions(): array
    {
        return $this->conditionsOf($this->paused);
    }

    /**
     * Record $condition in $file — the LIVE marker, or the paused one while the gate stands aside —
     * and return the stable id that signs it off. Setting a condition that is already there returns
     * its existing id instead of a twin: a condition IS its text, exactly as the {@see handOver}
     * merge reads it.
     */
    private function addTo(MarkerFile $file, string $condition): int
    {
        $state = $this->parse($file);
        $text = $this->oneLine($condition);
        $existing = array_search($text, $state['conditions'], true);

        if ($existing !== false) {
            return $existing;
        }

        $id = $state['lastId'] + 1;
        $conditions = $state['conditions'] + [$id => $text];

        $this->write($file, $conditions, $id, blocks: 0, stuck: false); // A new condition is a fresh
        // gate: never inherit the block count of the one before it.

        return $id;
    }

    /**
     * The highest id ever handed out while this gate stands — the next {@see add} continues from it,
     * so a struck-off condition's id is never reused for a different condition.
     */
    private function lastId(): int
    {
        return $this->parse($this->file)['lastId'];
    }

    /**
     * @return array<int, string>  the conditions held by $file, keyed by their stable ids
     */
    private function conditionsOf(MarkerFile $file): array
    {
        return $this->parse($file)['conditions'];
    }

    /**
     * The marker decoded: blocks and stuck from the header, then the last-handed-out id and the
     * `id<TAB>text` condition lines. A legacy marker (pre-stable-ids: bare condition texts, no id
     * line) reads back with positional ids — the next save rewrites it in the current format.
     *
     * @return array{lastId: int, conditions: array<int, string>}
     */
    private function parse(MarkerFile $file): array
    {
        $tail = array_slice($file->values(), 2);

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
            $this->file->delete(); // No conditions IS no gate — never leave an empty marker behind.
            // Only the LIVE marker: a paused gate is untouched state, and still waits to be resumed.

            return;
        }

        $this->write($this->file, $conditions, $lastId, $blocks, $stuck);
    }

    /**
     * Serialise a gate into $file — the ONE place the marker's line format is produced, so the live
     * store and the set-aside one can never drift apart.
     *
     * @param  array<int, string>  $conditions  keyed by stable id
     */
    private function write(MarkerFile $file, array $conditions, int $lastId, int $blocks, bool $stuck): void
    {
        $lines = [(string) $blocks, $stuck ? '1' : '0', (string) $lastId];

        foreach ($conditions as $id => $text) {
            $lines[] = "{$id}\t{$text}";
        }

        $file->write($lines, self::EXPLANATION);
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
}
