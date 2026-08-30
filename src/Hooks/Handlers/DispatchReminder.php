<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Scheduler;
use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Holds the stop while a moment's work is still undispatched — where a trigger actually becomes an agent,
 * since the hook that saw the moment only wrote it down. It BLOCKS rather than nudges: a nudge is how a
 * dispatch gets skipped (the routine is nudged, and the routine is skipped), and work nobody was made to
 * notice is work that did not happen.
 */
final class DispatchReminder extends Hook
{
    /**
     * How many stops one standing dispatch may hold before it is let go. An orchestrator that cannot
     * dispatch — no Agent tool, a role it will not play — must not be held for ever by a rule meant to
     * stop it forgetting; a loop is a worse failure than a missed review.
     */
    private const int MOST_HELD = 5;

    public function summary(): string
    {
        return 'Holds a stop while a moment has asked for an agent nobody has dispatched yet, and hands over the brief to dispatch it with.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function onStop(HookEvent $event): int
    {
        $pending = Pending::inSession($event->sessionWorkspace());
        $waiting = $pending->all();

        if ($waiting === []) {
            return $this->pass();
        }

        if ($pending->held() > self::MOST_HELD) {
            return $this->pass();
        }

        $listed = [];

        foreach ($waiting as $work) {
            $listed[] = "  {$work->agent} → {$work->procedure}   (`{$work->moment}` on {$work->subject}, {$work->at})";
        }

        $asked = count($waiting) === 1
            ? 'A moment asked for an agent and nobody has started it:'
            : count($waiting) . ' moments asked for agents and nobody has started them:';
        $work = implode("\n", $listed);
        $brief = new Scheduler($event->root, Pending::inSession($event->sessionWorkspace())->path())->brief();

        // ONE agent, however many are waiting. The orchestrator starts the SCHEDULER and the scheduler
        // starts the rest — handing an orchestrator N briefs to place by hand spends the most expensive
        // context in the build on bookkeeping, which is the one job where judgement is no advantage.
        return $this->block(<<<TEXT
            {$asked}

            {$work}

            Start the SCHEDULER now, with the Agent tool, and let it place them. It is a subagent of
            yours, so you can watch it work and everything it starts shares this session. Give it a small
            model — scheduling needs no judgement — and this as its whole prompt:

            {$brief}

            It reads the list itself, starts one agent at a time on YOUR model, and strikes each off.
            This stop is held until the list is empty.
            TEXT);
    }
}
