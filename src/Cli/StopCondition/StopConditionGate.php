<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\Line;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The conditions the user asked the agent to satisfy before it may stop — the state behind the
 * `until` Stop gate ({@see \JesseGall\CodeCommandments\Hooks\Handlers\StopConditionReminder}). The plan-free
 * sibling of {@see \JesseGall\CodeCommandments\Cli\Plan\PlanMarker}: no plan has to be active, the
 * user simply says "keep going until X" and the agent records X here. Conditions are keyed by a
 * STABLE id (never renumbered or reused while the gate stands, so batched `met` calls can't strike
 * the wrong one — #399); the gate lifts only when none remain ({@see met} strikes one off,
 * {@see clear} drops them all). {@see heldStops} counts the consecutive stops the gate has held so a
 * wedged agent is released instead of looped forever — striking a condition off is progress and
 * resets it. The user can also set the whole gate aside without losing it: {@see pause} raises a flag
 * so nothing holds a stop while every condition is kept verbatim, and {@see resume} puts them back in
 * force. Being BLOCKED is recorded per condition ({@see markBlocked}), so `stuck` is exactly the
 * moment {@see unblocked} is empty. ONE session-scoped {@see StateFile} holds all of it — conditions
 * and their blocks, the held-stop count, the to-do drift, any pending claim — so lifting the gate
 * deletes the whole state at once and no half survives to answer for the next one.
 */
final class StopConditionGate
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.until'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'Stop-gate conditions for code-commandments (`commandments stop-condition "<condition>"`). While any '
                . 'condition stands, the Stop hook blocks and tells the agent to VERIFY it; the agent strikes one '
                . 'off with `commandments stop-condition met <id>` and the gate lifts when none are left.',
            [
                'held_stops' => 'consecutive stops held with no condition met — 10 sets the gate aside',
                'todo_drift' => 'work done since the visible to-do list was last updated (TodoWrite)',
                'last_id' => 'the highest condition id ever handed out. Ids are STABLE: striking one off never '
                    . 'renumbers the rest, and an id is never reused',
                'paused' => 'yes = THE USER set the gate aside. Every condition is kept verbatim and nothing '
                    . 'holds a stop until `commandments stop-condition resume`',
                'stuck' => 'yes = one stop is released, every standing condition having been named as blocked. '
                    . 'One-shot: the next held stop consumes it and drops the blocks, so a claim is always made '
                    . 'afresh about the list as it stands then',
                'claim_round' => 'how many challenges the pending `stuck` claim has answered (0 = none pending)',
                'work' => 'pieces of work done since the gate was set. Never reset, so a gate that has seen '
                    . 'work and held no stop can say the Stop hook is not reaching it',
            ],
            defaults: new State(
                held_stops: 0,
                todo_drift: 0,
                last_id: 0,
                paused: false,
                stuck: false,
                claim_round: 0,
                work: 0,
            ),
            list: 'one `id<TAB>condition` per line — what you may not stop until it holds. A third '
                . 'tab-separated column is the reason that ONE condition cannot move without the user '
                . '(`commandments stop-condition blocked <id> --reason="…"`); `stuck` is released only when every line '
                . 'carries one.',
            safe: 'deleting it simply lifts the gate',
        );
    }

    /**
     * How many challenges the standing `stuck` claim has already answered — 0 when none is pending. A claim
     * survives only until the agent is sent back in ({@see dropClaim}), so it can never be answered half now
     * and half in an hour's time.
     */
    public function claimRound(): int
    {
        return $this->file->read()->int('claim_round');
    }

    /**
     * Answer one challenge and return the number of challenges now answered.
     */
    public function advanceClaim(): int
    {
        $round = $this->claimRound() + 1;

        $this->save(fn (State $state): State => $state->with(claim_round: $round));

        return $round;
    }

    /**
     * Forget any pending claim — a met condition, a gate paused, resumed or cleared, so a half-answered
     * challenge never carries into a situation that has moved on.
     */
    public function dropClaim(): void
    {
        $this->save(fn (State $state): State => $state->with(claim_round: 0));
    }

    /**
     * Has this gate seen any work at all? A gate that has and never held one stop is not being asked.
     */
    public function workDone(): bool
    {
        return $this->file->read()->int('work') > 0;
    }

    /**
     * Count one piece of work against the to-do list the USER can see, and answer whether it has now
     * drifted $every pieces from the work actually being done — the moment to tell the agent to true it
     * up. Firing restarts the count, so the nudge lands once per stretch of drift rather than on every
     * tool use after the threshold. This one IS a count on purpose: it measures how long that list has
     * gone untouched, not which condition is moving.
     */
    public function driftedFor(int $every): bool
    {
        $drift = $this->file->read()->int('todo_drift') + 1;
        $due = $drift >= $every;

        $this->save(fn (State $state): State => $state->with(todo_drift: $due ? 0 : $drift, work: $state->int('work') + 1));

        return $due;
    }

    /**
     * The visible list was just trued up — the drift starts over.
     */
    public function resetDrift(): void
    {
        $this->save(fn (State $state): State => $state->with(todo_drift: 0));
    }

    /**
     * Record one more condition and return its STABLE id — the handle `stop-condition met <n>` takes. Ids are
     * never renumbered or reused while the gate stands, so a batch of `met` calls read off one `list`
     * can never strike the wrong condition (#399). A condition is stored as a single line (any
     * newlines in it collapse to spaces). Setting a condition that is already there returns its
     * existing id instead of a twin: a condition IS its text.
     *
     * While the gate is PAUSED the condition joins what is set aside and stays paused until
     * {@see resume} — a pause is the user's directive that NOTHING holds, so recording a condition
     * must never silently re-arm the gate behind their back (#418).
     */
    public function add(string $condition): int
    {
        $state = $this->file->read();
        $conditions = $this->conditionsOf($state);
        $text = Line::flatten($condition);

        foreach ($conditions as $existing) {
            if ($existing->text === $text) {
                return $existing->id;
            }
        }

        $id = $state->int('last_id') + 1;

        // A new condition is a fresh gate: never inherit the held-stop count, or a stuck release, of
        // the one before it — and it is one more thing a standing claim never accounted for.
        $this->write(
            $state->with(last_id: $id, held_stops: 0, stuck: false, claim_round: 0),
            [...$conditions, Condition::stated($id, $text)],
        );

        return $id;
    }

    /**
     * @return array<int, string>  every condition still in force, in the order they were set, keyed
     *                             by its STABLE id (ids keep their gaps once one is struck off). A
     *                             PAUSED gate holds nothing in force — its conditions are
     *                             {@see pausedConditions} until the user resumes them.
     */
    public function all(): array
    {
        return $this->texts($this->standing());
    }

    public function isOpen(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Record that ONE condition cannot move without the user, and why — the claim `stuck` is made of,
     * stated a condition at a time so the agent can mark them in whatever order it meets them. False
     * when no condition carries $id, or the reason is blank (a claim with no reason is no claim).
     */
    public function markBlocked(int $id, string $reason): bool
    {
        $conditions = $this->standing();

        if (Line::flatten($reason) === '' || ! array_key_exists($id, $conditions)) {
            return false;
        }

        $conditions[$id] = $conditions[$id]->blockedBy($reason);
        $this->write($this->file->read(), $conditions);

        return true;
    }

    /**
     * The standing conditions the agent has named as waiting on the user, with the reason each gave.
     *
     * @return array<int, string>  id → reason
     */
    public function blocked(): array
    {
        return array_map(
            static fn (Condition $c): string => $c->blockedBecause->unwrapOr($c->text),
            array_filter($this->standing(), static fn (Condition $c): bool => $c->isBlocked()),
        );
    }

    /**
     * The standing conditions NOT yet named as blocked — what a `stuck` claim still owes. `stuck` says
     * NOT ONE condition can move without the user, so while this is non-empty the claim is not the one
     * being made: these are conditions the agent has yet to speak for, and it has to work them or say
     * why they cannot move.
     *
     * @return array<int, string>  id → condition
     */
    public function unblocked(): array
    {
        return $this->texts(array_filter($this->standing(), static fn (Condition $c): bool => ! $c->isBlocked()));
    }

    /**
     * Drop every block — what a held stop does. A claim is about the list as it stands at the moment
     * it is made, so after the agent is sent back in it states one afresh, condition by condition,
     * rather than inheriting what it said an hour ago.
     */
    public function clearBlocks(): void
    {
        $this->write($this->file->read(), array_map(
            fn (Condition $c): Condition => $c->unblocked(),
            $this->conditionsOf($this->file->read()),
        ));
    }

    /**
     * Strike the condition with stable id $id off as satisfied and return its text — null when no
     * condition carries that id (already met, never existed, or set aside by a pause). The surviving
     * conditions KEEP their ids, so the numbers a `list` showed stay valid across strikes. Meeting one
     * is progress, so the block count resets and any pending claim is void.
     */
    public function met(int $id): ?string
    {
        $conditions = $this->standing();

        if (! array_key_exists($id, $conditions)) {
            return null;
        }

        $condition = $conditions[$id];
        unset($conditions[$id]);

        // Progress: whatever the agent was claiming, the list has moved under it.
        $this->write($this->file->read()->with(held_stops: 0, claim_round: 0), $conditions);

        return $condition->text;
    }

    /**
     * Count one held stop and return the running total. Reset by {@see add}/{@see met} — the counter
     * measures stops WITHOUT progress, so only a genuinely spinning agent reaches the cap.
     */
    public function recordBlock(): int
    {
        $held = $this->heldStops() + 1;

        // Holding a stop consumes the stuck signal, and every block with it: the agent is back at
        // work, so the claim it made about the list is spent.
        $this->write(
            $this->file->read()->with(held_stops: $held, stuck: false, claim_round: 0),
            array_map(fn (Condition $c): Condition => $c->unblocked(), $this->conditionsOf($this->file->read())),
        );

        return $held;
    }

    public function heldStops(): int
    {
        return $this->file->read()->int('held_stops');
    }

    /**
     * Signal that the agent is BLOCKED on every standing condition: the gate lets the NEXT stop
     * through so the agent can hand back to the user, but the conditions stay in force — the moment
     * it continues, the gate holds again. One-shot, exactly like `plan stuck`.
     */
    public function markStuck(): void
    {
        $this->save(fn (State $state): State => $state->with(stuck: true));
    }

    public function isStuck(): bool
    {
        return $this->file->read()->flag('stuck');
    }

    public function clearStuck(): void
    {
        $this->save(fn (State $state): State => $state->with(stuck: false));
    }

    /**
     * Drop every condition — the gate is gone and stops stand on their own again, and with it go the
     * counts, the blocks and any pending claim, so nothing survives to be read against the next gate.
     */
    public function clear(): void
    {
        $this->file->delete();
    }

    /**
     * Set the whole gate aside — the `paused` flag goes up, so every condition survives verbatim while
     * NOTHING holds a stop. The user's escape hatch when they want to do something else in between
     * without being sent back to the conditions ({@see resume} brings them back). False when there is
     * no gate standing to pause.
     */
    public function pause(): bool
    {
        return $this->setAside(true);
    }

    /**
     * Bring a paused gate back into force — every condition set aside holds again. False when nothing
     * is paused.
     */
    public function resume(): bool
    {
        return $this->setAside(false);
    }

    /**
     * Raise or lower the pause flag — the ONE move behind {@see pause} and {@see resume}, so the two
     * directions can never drift apart. A gate that changes sides is a FRESH gate: the held-stop count
     * and any pending claim do not survive the move. False when the flag already stands that way, or
     * when there are no conditions to set aside at all.
     */
    private function setAside(bool $paused): bool
    {
        $state = $this->file->read();

        if ($this->conditionsOf($state) === [] || $state->flag('paused') === $paused) {
            return false;
        }

        $this->save(fn (State $state): State => $state->with(
            paused: $paused,
            held_stops: 0,
            stuck: false,
            claim_round: 0,
        ));

        return true;
    }

    public function isPaused(): bool
    {
        return $this->file->read()->flag('paused');
    }

    /**
     * The conditions a paused gate is holding — what `stop-condition list` shows while it stands aside.
     *
     * @return array<int, string>  keyed by the id each condition had when it was paused
     */
    public function pausedConditions(): array
    {
        $state = $this->file->read();

        return $state->flag('paused') ? $this->texts($this->conditionsOf($state)) : [];
    }

    /**
     * The conditions in force — none while the gate stands aside.
     *
     * @return array<int, Condition>  keyed by stable id
     */
    private function standing(): array
    {
        $state = $this->file->read();

        return $state->flag('paused') ? [] : $this->conditionsOf($state);
    }

    /**
     * @param  array<int, Condition>  $conditions
     * @return array<int, string>  id → the condition's text
     */
    private function texts(array $conditions): array
    {
        return array_map(static fn (Condition $c): string => $c->text, $conditions);
    }

    /**
     * Adjust the state in place, keeping the conditions as they are — for the counters and flags that
     * move without the list moving. A gate with no conditions has no state worth keeping, so nothing
     * is written back to a file that isn't there.
     *
     * @param  \Closure(State): State  $change
     */
    private function save(\Closure $change): void
    {
        $state = $this->file->read();
        $conditions = $this->conditionsOf($state);

        if ($conditions === []) {
            return;
        }

        $this->write($change($state), $conditions);
    }

    /**
     * @param  array<int, Condition>  $conditions
     */
    private function write(State $state, array $conditions): void
    {
        if ($conditions === []) {
            $this->file->delete(); // No conditions IS no gate — never leave an empty marker behind, and
            // never leave a count from this gate for the next one to inherit.

            return;
        }

        $this->file->write($state->withItems(array_map(fn (Condition $c): string => $c->line(), $conditions)));
    }

    /**
     * The conditions the file holds, paused or not, keyed by their stable ids.
     *
     * @return array<int, Condition>
     */
    private function conditionsOf(State $state): array
    {
        $conditions = [];

        foreach ($state->items() as $item) {
            $condition = Condition::read($item);

            if ($condition !== null) {
                $conditions[$condition->id] = $condition;
            }
        }

        return $conditions;
    }
}
