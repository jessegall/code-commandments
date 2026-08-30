<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Dispatcher;
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

        $dispatcher = new Dispatcher($event->sessionWorkspace(), $event->root);
        $binary = Binary::in($event->root);
        $said = [];

        foreach ($waiting as $work) {
            $said[] = "── {$work->agent} → {$work->procedure}  (asked for by `{$work->moment}` on {$work->subject} at {$work->at})";
            $said[] = '';
            $said[] = $dispatcher->briefFor($work);
            $said[] = '';
            $said[] = "Dispatch it with the Agent tool, then: {$binary} queue dispatched {$work->agent}";
            $said[] = '';
        }

        return $this->block(implode("\n", [
            count($waiting) === 1
                ? 'A moment asked for an agent and it has not been dispatched. Start it NOW, with the Agent tool, before you stop:'
                : count($waiting) . ' moments asked for agents that have not been dispatched. Start them NOW, with the Agent tool, before you stop:',
            '',
            ...$said,
            'Each brief is the WHOLE prompt for that agent — hand it over as it stands rather than '
                . 'summarising it, since everything it does not say, the agent will work out for itself '
                . 'and get wrong. Mark each one dispatched once the call is made; this stop is held until '
                . 'none are left.',
        ]));
    }
}
