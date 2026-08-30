<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * A single unsettled background task silenced EVERY Stop hook — the stop gate, the routine, the journal
 * reminder, all of them. One session ran 415 pieces of work and held not one stop, because something was
 * always pending.
 *
 * The silence was right for exactly one hook and wrong for the rest, which is the same blanket that the
 * subagent rule already had to have undone.
 */
final class QuietAtAStopTest extends TestCase
{
    private function speaking(RecordingHookIO $io): Hook
    {
        return new class($io) extends Hook
        {
            public function summary(): string
            {
                return 'speaks at a stop';
            }

            public function bindings(): array
            {
                return [new HookBinding('Stop')];
            }

            protected function onStop(HookEvent $event): int
            {
                return $this->quietly($event, 'I spoke');
            }
        };
    }

    private function keepGoing(RecordingHookIO $io): Hook
    {
        return new class($io) extends Hook
        {
            public function summary(): string
            {
                return 'tells the agent to carry on';
            }

            public function bindings(): array
            {
                return [new HookBinding('Stop')];
            }

            protected function speaksWhileWorkPends(): bool
            {
                return false;
            }

            protected function onStop(HookEvent $event): int
            {
                return $this->quietly($event, 'keep going');
            }
        };
    }

    /**
     * @param  callable(RecordingHookIO): Hook  $build
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function said(callable $build, array $payload): array
    {
        $io = new RecordingHookIO([...$payload, 'hook_event_name' => 'Stop'], new FakeGit('/tmp'));

        $build($io)->run([]);

        return array_map(fn ($response) => $response->context->unwrapOr(''), $io->emitted);
    }

    public function test_a_hook_speaks_at_a_stop_while_a_background_task_runs(): void
    {
        $said = $this->said($this->speaking(...), ['background_tasks' => [['status' => 'running']]]);

        $this->assertSame(['I spoke'], $said, 'pending work is not a reason to silence a stop');
    }

    /**
     * The one hook the silence was right for: telling an agent to carry on is redundant advice to an
     * agent already waiting on work it started.
     */
    public function test_a_keep_going_nudge_stays_quiet_while_work_pends(): void
    {
        $this->assertSame([], $this->said($this->keepGoing(...), ['background_tasks' => [['status' => 'running']]]));
    }

    public function test_a_keep_going_nudge_speaks_once_the_work_settles(): void
    {
        $said = $this->said($this->keepGoing(...), ['background_tasks' => [['status' => 'completed']]]);

        $this->assertSame(['keep going'], $said);
    }

    /**
     * Plan mode silences everything, and that is unchanged: nothing has been approved yet.
     */
    public function test_plan_mode_still_silences_every_stop_hook(): void
    {
        $this->assertSame([], $this->said($this->speaking(...), ['permission_mode' => 'plan']));
    }
}
