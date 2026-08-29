<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\StopCondition\StopConditionGate;
use JesseGall\CodeCommandments\Hooks\Handlers\StopConditionReminder;
use JesseGall\CodeCommandments\Hooks\StopHookCap;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The user-set stop gate: while a condition stands, every stop is held and the agent is sent back to
 * verify it. Silent without a gate, one-shot-releasable when stuck, and self-releasing at the cap.
 */
final class StopConditionReminderTest extends TestCase
{
    /**
     * The harness cap these tests run under. Pinned, because the release point is measured against it
     * ({@see StopHookCap}) — left to the ambient environment, a machine that raises the cap would move
     * the very threshold under test and the suite would pass or fail by whose laptop it ran on.
     */
    private const int CAP = 6;

    private string $root;

    protected function setUp(): void
    {
        putenv(StopHookCap::VARIABLE . '=' . self::CAP);

        $this->root = sys_get_temp_dir() . '/cc-untilhook-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        putenv(StopHookCap::VARIABLE);

        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * How many stops this gate holds before releasing itself — one short of the harness cap.
     */
    private function holds(): int
    {
        return StopHookCap::budget(50);
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

    /**
     * `Stop` needs a turn to END, and an agent that chains tool calls never ends one — so a gate resting
     * on it alone held nothing for an hour while twelve conditions stood. The conditions come back on the
     * work itself.
     */
    public function test_the_conditions_resurface_as_work_goes_by_without_a_stop(): void
    {
        $this->gate()->add('the suite is green');

        for ($i = 0; $i < 19; $i++) {
            $this->assertSame([], $this->postToolUse('Read'), 'quiet until the count is reached');
        }

        $context = $this->context($this->postToolUse('Read'));

        $this->assertStringContainsString('1 stop condition(s) still stand', $context, 'it names its own number');
        $this->assertStringContainsString('the suite is green', $context);
    }

    public function test_resurfacing_restarts_the_count(): void
    {
        $this->gate()->add('the suite is green');

        for ($i = 0; $i < 20; $i++) {
            $this->postToolUse('Read');
        }

        $this->assertSame([], $this->postToolUse('Read'), 'the next call is quiet again');
    }

    /**
     * A nudge normally spends the very resource it protects. One delivered while the agent is blocked on
     * a background process costs nothing it was going to spend — so it fires there regardless of count.
     */
    public function test_a_wait_surfaces_the_conditions_at_once(): void
    {
        $this->gate()->add('the suite is green');

        $context = $this->context($this->postToolUse('Bash', 'sleep 300; tail -5 out.txt'));

        $this->assertStringContainsString('waiting on a background process', $context);
        $this->assertStringContainsString('the suite is green', $context);
    }

    /**
     * Counting a wait as work inflates every number built on it — the gate believed forty pieces of work
     * had happened when a dozen were sleeps.
     */
    public function test_waiting_is_not_counted_as_work(): void
    {
        $this->gate()->add('the suite is green');

        $before = $this->gate()->reach()['work'];
        $this->postToolUse('Bash', 'sleep 300');

        $this->assertSame($before, $this->gate()->reach()['work'], 'a sleep moves no counter');
    }

    public function test_a_paused_gate_surfaces_nothing(): void
    {
        $this->gate()->add('the suite is green');
        $this->gate()->pause();

        $this->assertSame([], $this->postToolUse('Bash', 'sleep 300'));
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
        // A user recording dozens of things would otherwise get the whole list back on EVERY stop. The
        // MOST RECENT few are spelled out and the rest are a count plus `stop-condition list` — ids only
        // rise, so the oldest are the ones furthest from whatever is being worked on now, and a sample of
        // those teaches a reader that the list is irrelevant.
        for ($i = 1; $i <= 8; $i++) {
            $this->gate()->add("thing {$i} is done");
        }

        $reason = $this->reason($this->stop());

        $this->assertStringContainsString('8 STOP CONDITIONS', $reason, 'the count leads');
        $this->assertStringContainsString('8. thing 8 is done', $reason, 'the newest is shown');
        $this->assertStringContainsString('6. thing 6 is done', $reason);
        $this->assertStringNotContainsString('thing 1 is done', $reason, 'the oldest are not spelled out');
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

        for ($i = 0; $i < $this->holds(); $i++) {
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

        for ($i = 0; $i <= $this->holds(); $i++) {
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

        for ($i = 0; $i <= $this->holds(); $i++) {
            $released = $this->reason($this->stop());
        }

        $this->assertStringContainsString('SET ASIDE', $released);
        $this->assertStringContainsString('stop-condition resume', $released);
    }

    public function test_meeting_a_condition_resets_the_cap_countdown(): void
    {
        $this->gate()->add('tests pass');
        $this->gate()->add('readme updated');

        for ($i = 0; $i < $this->holds() - 1; $i++) {
            $this->stop(); // Short of the release point, so the countdown is running, not spent.
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
        // #406: a deferral was being satisfied with a note that holds no stop and dies with the session,
        // so the task was silently lost. The gate is the half that brings it back.
        $this->gate()->add('the changelog has an entry');

        $context = $this->prompt()[0]->context->unwrapOr('');

        $this->assertStringContainsString('don\'t forget to', $context, 'the wording is named as a deferral');
        $this->assertStringContainsString('stop-condition', $context, 'and the gate is what brings it back');
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
