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

    public function test_a_stuck_signal_releases_exactly_one_stop_and_keeps_the_condition(): void
    {
        $this->gate()->add('the full test suite passes');
        $this->gate()->markStuck();

        $this->assertSame([], $this->stop(), 'the blocked agent may hand back to the user');
        $this->assertSame([1 => 'the full test suite passes'], $this->gate()->all());
        $this->assertSame('block', $this->stop()[0]['decision'] ?? null, 'and the gate holds again right after');
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
        $this->assertFalse($this->gate()->isOpen(), 'the gate is gone — the next stop stands');
        $this->assertSame([], $this->stop());
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

    public function test_the_triage_also_fires_during_a_plan_with_no_gate_yet(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertNotSame([], $this->prompt(), 'a plan is work in flight, gate or no gate');
    }

    public function test_the_triage_is_silent_in_an_ordinary_conversation(): void
    {
        $this->assertSame([], $this->prompt(), 'nothing in flight — a normal message is not taxed');
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
