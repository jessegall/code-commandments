<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Scheduler;
use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
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
     * How much work goes by before it says so again. A queue does not change what the orchestrator can
     * do about it, so saying it twice adds nothing and saying it ten times teaches the reader to skim.
     */
    private const int A_STRETCH = 25;

    public function summary(): string
    {
        return 'Asks for a scheduler while a dispatch is waiting — on every tool use, and again at a stop. It never holds one.';
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

        // ONE LINE, to the one who can only make one decision. The orchestrator cannot act on a
        // subject, a moment or an agent name — whatever the list holds, its whole move is to start a
        // scheduler — while the party that CAN act on the detail reads it itself with `queue next`. So
        // the detail was being delivered to whoever could not use it and withheld from whoever could.
        //
        // And ONCE PER STRETCH, not per stop: nine lines repeated a dozen times were answered a dozen
        // times with "Declining", and by the fourth the reader had stopped looking. The one time it
        // changes is the time that gets skimmed too.
        if (! $this->workMovedOn($event, 'dispatch-waiting', Journal::inSession($event->sessionWorkspace())->calls(), self::A_STRETCH)) {
            return $this->pass();
        }

        return $this->inject($event, sprintf(
            'Code Commandments — %d dispatch(es) waiting. `%s scheduler` prepares one and prints the '
                . 'prompt to start it with; `%s queue` is the list, and `%s queue drop` abandons it if '
                . 'you have decided against it.',
            count($waiting),
            $binary = Binary::in($event->root),
            $binary,
            $binary,
        ));
    }
}
