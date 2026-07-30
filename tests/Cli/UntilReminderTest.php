<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Until\UntilGate;
use JesseGall\CodeCommandments\Hooks\Handlers\UntilReminder;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The user-set stop gate: while a condition stands, every stop is held and the agent is sent back to
 * verify it. Silent without a gate, one-shot-releasable when stuck, and self-releasing at the cap.
 */
final class UntilReminderTest extends TestCase
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
        new UntilReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function prompt(): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'UserPromptSubmit']);
        new UntilReminder($io)->run([]);

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

        new UntilReminder($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
    }

    private function reason(array $emitted): string
    {
        return (string) ($emitted[0]['reason'] ?? '');
    }

    private function gate(): UntilGate
    {
        return UntilGate::inSession(Workspace::at($this->root));
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

        $this->assertSame('block', $emitted[0]['decision'] ?? null);
        $reason = $this->reason($emitted);
        $this->assertStringContainsString('1. the full test suite passes', $reason);
        $this->assertStringContainsString('2. the README is updated', $reason);
        $this->assertStringContainsString('VERIFY', $reason, 'the agent is told to verify, not assume');
        $this->assertStringContainsString('until met', $reason);
        $this->assertStringContainsString('until stuck', $reason);
    }

    public function test_a_long_gate_is_excerpted_instead_of_reprinted_in_full(): void
    {
        // A user parking dozens of tasks would otherwise get the whole list back on EVERY stop. The
        // oldest few are due next, so those are spelled out and the rest are a count plus `until list`.
        for ($i = 1; $i <= 8; $i++) {
            $this->gate()->add("thing {$i} is done");
        }

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('8 STOP CONDITIONS', $reason, 'the count leads');
        $this->assertStringContainsString('1. thing 1 is done', $reason);
        $this->assertStringContainsString('3. thing 3 is done', $reason);
        $this->assertStringNotContainsString('thing 4 is done', $reason, 'the tail is not spelled out');
        $this->assertStringContainsString('and 5 more', $reason);
        $this->assertStringContainsString('until list', $reason, 'with where to read the rest');
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
        $this->assertSame('block', $this->stop()[0]['decision'] ?? null, 'and the gate holds again right after');
    }

    public function test_a_tool_use_under_the_gate_counts_as_work(): void
    {
        $this->gate()->add('the suite is green');

        $this->postToolUse('Read');

        $this->assertSame(1, $this->gate()->workSinceHold());
    }

    public function test_talking_to_the_gate_and_the_to_do_list_are_not_work(): void
    {
        // The two moves an agent can make without touching the problem: reading the gate back to itself
        // and reordering its to-do list. Counting either would let a `list` + `stuck` pass as an attempt.
        $this->gate()->add('the suite is green');

        $this->postToolUse('Bash', 'vendor/bin/commandments until list');
        $this->postToolUse('TodoWrite');

        $this->assertSame(0, $this->gate()->workSinceHold());
    }

    public function test_work_is_not_counted_when_no_gate_stands(): void
    {
        $this->postToolUse('Read');

        $this->assertSame(0, $this->gate()->workSinceHold(), 'an ordinary session pays nothing for the measure');
    }

    public function test_a_held_stop_restarts_the_work_count(): void
    {
        // Work is measured from the LAST hold, so "nothing worked since you were sent back" stays a
        // statement about this turn — not about the whole session.
        $this->gate()->add('the suite is green');
        $this->postToolUse('Read');

        $this->stop();

        $this->assertSame(0, $this->gate()->workSinceHold());
    }

    public function test_it_releases_itself_after_the_cap_so_a_wedged_session_can_stop(): void
    {
        $this->gate()->add('the impossible thing happens');

        for ($i = 0; $i < 10; $i++) {
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
        // part of that: the conditions are paused, kept verbatim, and `until resume` puts them back.
        $this->gate()->add('the impossible thing happens');
        $this->gate()->add('the other impossible thing happens');

        for ($i = 0; $i <= 10; $i++) {
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

        for ($i = 0; $i <= 10; $i++) {
            $released = $this->reason($this->stop());
        }

        $this->assertStringContainsString('SET ASIDE', $released);
        $this->assertStringContainsString('until resume', $released);
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

    public function test_work_voids_a_half_answered_stuck_claim(): void
    {
        // The user's rule: the moment the agent accepts the challenge and gets back to work, the claim it
        // was part-way through is gone. Talking to the gate is not work, so the challenge itself survives.
        $this->gate()->add('the suite is green');
        $this->gate()->advanceClaim('I need a decision');

        $this->postToolUse('Bash', 'vendor/bin/commandments until list');

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

        $this->assertSame(0, $this->gate()->blocks());
        $this->assertStringContainsString('Do not stop', $this->reason($this->stop()));
    }

    public function test_an_active_plan_takes_precedence_and_the_gate_waits_for_it(): void
    {
        $this->gate()->add('the changelog has an entry');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertSame([], $this->stop(), 'the plan owns the stop — one hook pushes the agent back in');
        $this->assertSame(0, $this->gate()->blocks(), 'and a long grind never burns the release cap');

        PlanMarker::inSession(Workspace::at($this->root))->clear(); // `plan done`

        $this->assertStringContainsString('the changelog has an entry', $this->reason($this->stop()));
    }

    public function test_a_prompt_mid_work_puts_the_park_or_do_it_now_triage_in_front_of_the_agent(): void
    {
        $this->gate()->add('the changelog has an entry');

        $context = (string) ($this->prompt()[0]['hookSpecificOutput']['additionalContext'] ?? '');

        $this->assertStringContainsString('STEERING', $context, 'work in hand is done now');
        $this->assertStringContainsString('PARK', $context, 'a separate/deferred task is parked');
        $this->assertStringContainsString('until "', $context, 'with the command to park it');
    }

    public function test_the_triage_says_a_to_do_item_is_not_parking(): void
    {
        // #406: "add it to the to-do list" was being satisfied with a tracker entry alone, which holds
        // no stop and dies with the session — so the deferred task was silently lost.
        $this->gate()->add('the changelog has an entry');

        $context = (string) ($this->prompt()[0]['hookSpecificOutput']['additionalContext'] ?? '');

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
        // last, and `until stuck` is only for a list where NOTHING can move.
        $this->gate()->add('the migration runs');
        $this->gate()->add('the changelog has an entry');

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('DRAIN THE LIST FIRST', $reason);
        $this->assertStringContainsString('leave the blocked one for last', $reason);
        $this->assertStringContainsString('Only when NOTHING left on the list can move', $reason);
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
        new UntilReminder($io)->run([]);

        $this->assertSame([], $io->emitted);
        $this->assertSame(0, $this->gate()->blocks(), 'and it does not burn a block');
    }
}
