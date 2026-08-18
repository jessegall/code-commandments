<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\StopCondition\StopConditionGate;
use JesseGall\CodeCommandments\Hooks\Handlers\StopConditionReminder;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The user-set stop gate: while a condition stands, every stop is held and the agent is sent back to
 * verify it. Silent without a gate, one-shot-releasable when stuck, and self-releasing at the cap.
 */
final class StopConditionReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-untilhook-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stop(): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'Stop']);
        new StopConditionReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function prompt(): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'UserPromptSubmit']);
        new StopConditionReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * One tool use as the harness reports it — the moment the gate counts work from.
     *
     * @return list<array<string, mixed>>
     */
    private function postToolUse(string $tool, string $command = ''): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), [
            'hook_event_name' => 'PostToolUse',
            'tool_name' => $tool,
            'tool_input' => ['command' => $command],
        ]);

        new StopConditionReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * A `TodoWrite` as the harness reports it — the list the user is watching, in the order it was written.
     *
     * @param  list<array<string, string>>  $todos
     * @return list<array<string, mixed>>
     */
    private function todoWrite(array $todos): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), [
            'hook_event_name' => 'PostToolUse',
            'tool_name' => 'TodoWrite',
            'tool_input' => ['todos' => $todos],
        ]);

        new StopConditionReminder($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return $emitted[0]->context->unwrapOr('');
    }

    private function reason(array $emitted): string
    {
        return $emitted[0]->blockReason->unwrapOr('');
    }

    private function gate(): StopConditionGate
    {
        return StopConditionGate::inSession(Workspace::at($this->root));
    }

    public function test_it_is_silent_when_no_condition_is_set(): void
    {
        $this->assertSame([], $this->stop());
    }

    public function test_it_blocks_the_stop_and_names_every_standing_condition(): void
    {
        $this->gate()->add('the full test suite passes');
        $this->gate()->add('the README is updated');

        $emitted = $this->stop();

        $this->assertTrue($emitted[0]->blockReason->isSome());
        $reason = $this->reason($emitted);
        $this->assertStringContainsString('1. the full test suite passes', $reason);
        $this->assertStringContainsString('2. the README is updated', $reason);
        $this->assertStringContainsString('VERIFY', $reason, 'the agent is told to verify, not assume');
        $this->assertStringContainsString('stop-condition met', $reason);
        $this->assertStringContainsString('stop-condition stuck', $reason);
    }

    public function test_a_long_gate_is_excerpted_instead_of_reprinted_in_full(): void
    {
        // A user parking dozens of tasks would otherwise get the whole list back on EVERY stop. The
        // oldest few are due next, so those are spelled out and the rest are a count plus `stop-condition list`.
        for ($i = 1; $i <= 8; $i++) {
            $this->gate()->add("thing {$i} is done");
        }

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('8 STOP CONDITIONS', $reason, 'the count leads');
        $this->assertStringContainsString('1. thing 1 is done', $reason);
        $this->assertStringContainsString('3. thing 3 is done', $reason);
        $this->assertStringNotContainsString('thing 4 is done', $reason, 'the tail is not spelled out');
        $this->assertStringContainsString('and 5 more', $reason);
        $this->assertStringContainsString('stop-condition list', $reason, 'with where to read the rest');
    }

    public function test_the_excerpt_keeps_the_stable_ids_the_met_command_takes(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->gate()->add("thing {$i} is done");
        }

        $this->gate()->met(1);
        $this->gate()->met(2);

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('3. thing 3 is done', $reason, 'not renumbered to 1.');
        $this->assertStringContainsString('5. thing 5 is done', $reason);
    }

    public function test_a_stuck_signal_releases_exactly_one_stop_and_keeps_the_condition(): void
    {
        $this->gate()->add('the full test suite passes');
        $this->gate()->markStuck();

        $this->assertSame([], $this->stop(), 'the blocked agent may hand back to the user');
        $this->assertSame([1 => 'the full test suite passes'], $this->gate()->all());
        $this->assertTrue($this->stop()[0]->blockReason->isSome(), 'and the gate holds again right after');
    }

    public function test_a_held_stop_drops_what_the_agent_said_was_blocked(): void
    {
        // A claim is about the list as it stands NOW. Being sent back in spends it, so an agent cannot
        // mark everything blocked once and coast on that for the rest of the session.
        $this->gate()->add('the suite is green');
        $this->gate()->markBlocked(1, 'the user must pick an API');

        $this->stop();

        $this->assertSame([], $this->gate()->blocked());
    }

    public function test_it_releases_itself_after_the_cap_so_a_wedged_session_can_stop(): void
    {
        $this->gate()->add('the impossible thing happens');

        for ($i = 0; $i < 25; $i++) {
            $this->assertStringContainsString('Do not stop', $this->reason($this->stop()));
        }

        $released = $this->reason($this->stop());

        $this->assertStringContainsString('RELEASED', $released);
        $this->assertStringContainsString('the impossible thing happens', $released);
        $this->assertFalse($this->gate()->isOpen(), 'nothing holds the next stop');
        $this->assertSame([], $this->stop());
    }

    public function test_the_cap_sets_the_conditions_aside_instead_of_deleting_them(): void
    {
        // The cap exists so a spinning session can always stop. Destroying what the user ASKED FOR is no
        // part of that: the conditions are paused, kept verbatim, and `stop-condition resume` puts them back.
        $this->gate()->add('the impossible thing happens');
        $this->gate()->add('the other impossible thing happens');

        for ($i = 0; $i <= 25; $i++) {
            $this->stop();
        }

        $this->assertFalse($this->gate()->isOpen());
        $this->assertSame([
            1 => 'the impossible thing happens',
            2 => 'the other impossible thing happens',
        ], $this->gate()->pausedConditions(), 'kept verbatim, with their ids');

        $this->gate()->resume();

        $this->assertTrue($this->gate()->isOpen(), 'and the user can put them back in force');
    }

    public function test_it_says_the_released_conditions_can_be_resumed(): void
    {
        $this->gate()->add('the impossible thing happens');

        for ($i = 0; $i <= 25; $i++) {
            $released = $this->reason($this->stop());
        }

        $this->assertStringContainsString('SET ASIDE', $released);
        $this->assertStringContainsString('stop-condition resume', $released);
    }

    public function test_it_calls_out_a_to_do_list_that_has_gone_stale_under_the_gate(): void
    {
        // The user watches the to-do list in their terminal. Twenty pieces of work with no update to it
        // means they are reading a list that describes a different hour of the session.
        $this->gate()->add('the suite is green');

        for ($i = 0; $i < 19; $i++) {
            $this->assertSame([], $this->postToolUse('Read'), 'a focused stretch of work is not interrupted');
        }

        $emitted = $this->postToolUse('Read');

        $this->assertStringContainsString('TodoWrite', $this->context($emitted));
        $this->assertStringContainsString('USER IS WATCHING', $this->context($emitted));
    }

    public function test_updating_the_to_do_list_restarts_the_drift(): void
    {
        $this->gate()->add('the suite is green');

        for ($i = 0; $i < 19; $i++) {
            $this->postToolUse('Read');
        }

        $this->postToolUse('TodoWrite');

        $this->assertSame([], $this->postToolUse('Read'), 'the list is current — nothing to say');
    }

    public function test_it_asks_for_the_current_item_at_the_top_of_the_visible_list(): void
    {
        // The user reads the first line of the list to see where the agent is. An in-progress item buried
        // behind finished and pending ones makes them scan for it.
        $this->gate()->add('the suite is green');

        $emitted = $this->todoWrite([
            ['content' => 'Drop the spatie dependency', 'status' => 'pending'],
            ['content' => 'Match children by id', 'status' => 'completed'],
            ['content' => 'Fix the rename dialog', 'activeForm' => 'Fixing the rename dialog', 'status' => 'in_progress'],
        ]);

        $context = $this->context($emitted);
        $this->assertStringContainsString('Fixing the rename dialog', $context, 'it names the item, as the user reads it');
        $this->assertStringContainsString('#3', $context, 'and where it is buried');
        $this->assertStringContainsString('TodoWrite', $context);
    }

    public function test_it_is_silent_when_the_list_already_leads_with_the_current_item(): void
    {
        $this->gate()->add('the suite is green');

        $this->assertSame([], $this->todoWrite([
            ['content' => 'Fix the rename dialog', 'status' => 'in_progress'],
            ['content' => 'Drop the spatie dependency', 'status' => 'pending'],
        ]));
    }

    public function test_a_list_with_nothing_in_progress_is_not_nudged(): void
    {
        // Nothing is being buried — a list of pending work makes no claim about the current moment.
        $this->gate()->add('the suite is green');

        $this->assertSame([], $this->todoWrite([
            ['content' => 'Match children by id', 'status' => 'completed'],
            ['content' => 'Drop the spatie dependency', 'status' => 'pending'],
        ]));
    }

    public function test_the_ordering_nudge_never_fires_without_a_gate(): void
    {
        $this->assertSame([], $this->todoWrite([
            ['content' => 'Drop the spatie dependency', 'status' => 'pending'],
            ['content' => 'Fix the rename dialog', 'status' => 'in_progress'],
        ]));
    }

    public function test_work_voids_a_half_answered_stuck_claim(): void
    {
        // The user's rule: the moment the agent accepts the challenge and gets back to work, the claim it
        // was part-way through is gone. Talking to the gate is not work, so the challenge itself survives.
        $this->gate()->add('the suite is green');
        $this->gate()->advanceClaim('I need a decision');

        $this->postToolUse('Bash', 'vendor/bin/commandments stop-condition list');

        $this->assertSame(1, $this->gate()->claimRound(), 'gate chatter leaves the claim standing');

        $this->postToolUse('Edit');

        $this->assertSame(0, $this->gate()->claimRound(), 'real work voids it');
    }

    public function test_the_stale_list_nudge_never_fires_without_a_gate(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->assertSame([], $this->postToolUse('Read'));
        }
    }

    public function test_meeting_a_condition_resets_the_cap_countdown(): void
    {
        $this->gate()->add('tests pass');
        $this->gate()->add('readme updated');

        for ($i = 0; $i < 8; $i++) {
            $this->stop();
        }

        $this->gate()->met(1); // Real progress — the agent is working, not spinning.

        $this->assertSame(0, $this->gate()->heldStops());
        $this->assertStringContainsString('Do not stop', $this->reason($this->stop()));
    }

    public function test_an_active_plan_takes_precedence_and_the_gate_waits_for_it(): void
    {
        $this->gate()->add('the changelog has an entry');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertSame([], $this->stop(), 'the plan owns the stop — one hook pushes the agent back in');
        $this->assertSame(0, $this->gate()->heldStops(), 'and a long grind never burns the release cap');

        PlanMarker::inSession(Workspace::at($this->root))->clear(); // `plan done`

        $this->assertStringContainsString('the changelog has an entry', $this->reason($this->stop()));
    }

    public function test_a_prompt_mid_work_puts_the_park_or_do_it_now_triage_in_front_of_the_agent(): void
    {
        $this->gate()->add('the changelog has an entry');

        $context = $this->prompt()[0]->context->unwrapOr('');

        $this->assertStringContainsString('STEERING', $context, 'work in hand is done now');
        $this->assertStringContainsString('PARK', $context, 'a separate/deferred task is parked');
        $this->assertStringContainsString('stop-condition "', $context, 'with the command to park it');
    }

    public function test_the_triage_says_a_to_do_item_is_not_parking(): void
    {
        // #406: "add it to the to-do list" was being satisfied with a tracker entry alone, which holds
        // no stop and dies with the session — so the deferred task was silently lost.
        $this->gate()->add('the changelog has an entry');

        $context = $this->prompt()[0]->context->unwrapOr('');

        $this->assertStringContainsString('add it to the to-do list', $context, 'the wording is named as a deferral');
        $this->assertStringContainsString('A TO-DO ITEM IS NOT PARKING', $context);
        $this->assertStringContainsString('TodoWrite', $context, 'and the visible half is still asked for');
    }

    public function test_the_triage_also_fires_during_a_plan_with_no_gate_yet(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertNotSame([], $this->prompt(), 'a plan is work in flight, gate or no gate');
    }

    public function test_the_triage_is_silent_in_an_ordinary_conversation(): void
    {
        $this->assertSame([], $this->prompt(), 'nothing in flight — a normal message is not taxed');
    }

    public function test_the_held_stop_tells_the_agent_to_drain_what_it_can_before_asking(): void
    {
        // A condition waiting on the user must not stall the ones that aren't: the blocked one goes
        // last, and `stop-condition stuck` is only for a list where NOTHING can move.
        $this->gate()->add('the migration runs');
        $this->gate()->add('the changelog has an entry');

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('DRAIN THE LIST FIRST', $reason);
        $this->assertStringContainsString('leave the blocked one for last', $reason);
        $this->assertStringContainsString('stop-condition blocked <n>', $reason, 'and the per-condition claim is spelled out');
        $this->assertStringContainsString('NOT FOR A BLOCKED ITEM', $reason, 'the LOCAL reading is named and refused (#422)');
    }

    public function test_a_paused_gate_holds_nothing_and_says_nothing(): void
    {
        $this->gate()->add('tests pass');
        $this->gate()->pause();

        $this->assertSame([], $this->stop(), 'a paused gate never holds a stop');
        $this->assertSame([], $this->prompt(), 'and never nudges the agent to park an interjection');
    }

    public function test_a_paused_gate_silences_the_triage_even_during_a_plan(): void
    {
        // The user paused the gate to do something else in between: the whole `until` machinery is
        // off, so a plan in flight must not revive the park-it nudge.
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $this->gate()->add('tests pass');
        $this->gate()->pause();

        $this->assertSame([], $this->prompt());
    }

    public function test_a_resumed_gate_holds_the_stop_again(): void
    {
        $this->gate()->add('tests pass');
        $this->gate()->pause();
        $this->gate()->resume();

        $this->assertStringContainsString('tests pass', $this->reason($this->stop()));
    }

    public function test_a_stop_parked_on_background_work_is_not_held(): void
    {
        $this->gate()->add('tests pass');

        $io = new CapturingHookIO(
            new FakeGit($this->root),
            ['hook_event_name' => 'Stop', 'background_tasks' => [['status' => 'running']]],
        );
        new StopConditionReminder($io)->run([]);

        $this->assertSame([], $io->emitted);
        $this->assertSame(0, $this->gate()->heldStops(), 'and it does not burn a block');
    }
}
