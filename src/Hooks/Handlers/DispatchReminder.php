<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Scheduler;
use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * Holds the stop while a moment's work is still undispatched — where a trigger actually becomes an agent,
 * since the hook that saw the moment only wrote it down. It BLOCKS rather than nudges: a nudge is how a
 * dispatch gets skipped (the routine is nudged, and the routine is skipped), and work nobody was made to
 * notice is work that did not happen.
 */
final class DispatchReminder extends Hook
{
    public function summary(): string
    {
        return 'Asks for a scheduler on every tool use while a dispatch is waiting, and holds a stop occasionally rather than every time.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop'), new HookBinding('PostToolUse')];
    }

    /**
     * Asked on every tool use while anything is waiting — quietly, since it is addressed to the agent
     * about its own bookkeeping. Repetition is the point: it is cheap, and it means the orchestrator
     * hears it long before a stop arrives.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        $waiting = Pending::inSession($event->sessionWorkspace())->all();

        if ($waiting === []) {
            return $this->pass();
        }

        return $this->quietly($event, sprintf(
            'Code Commandments — %d dispatch(es) are waiting and no scheduler has placed them. Start one '
                . 'with the Agent tool; `%s queue` lists what is owed.',
            count($waiting),
            Binary::in($event->root),
        ));
    }

    protected function onStop(HookEvent $event): int
    {
        $pending = Pending::inSession($event->sessionWorkspace());
        $waiting = $pending->all();

        if ($waiting === []) {
            return $this->pass();
        }

        $pending->held();

        $listed = [];

        foreach ($waiting as $work) {
            $listed[] = "  {$work->agent} → {$work->procedure}   (`{$work->moment}` on {$work->subject}, {$work->at})";
        }

        $asked = count($waiting) === 1
            ? 'A moment asked for an agent and nobody has started it:'
            : count($waiting) . ' moments asked for agents and nobody has started them:';
        $work = implode("\n", $listed);
        $brief = new Scheduler($event->root)->brief();

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
