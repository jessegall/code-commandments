<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Dispatcher;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Triggers;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\WorkerFinished;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Carries `SubagentStop` into the build's own vocabulary, so a project ties into "a worker finished"
 * rather than reverse-engineering it. It is the transport the layer was missing: every other moment
 * fires from a CLI command somebody had to remember to run, which is the same failure as the filing it
 * was meant to trigger.
 */
final class WorkerFinishedTrigger extends Hook
{
    /**
     * The moment this answers, as a profile names it.
     */
    private const string TRIGGER = 'worker-finished';

    public function summary(): string
    {
        return 'Raises the build\'s `WorkerFinished` moment when a worker stops, so a profile\'s triggers can act at the moment the information exists.';
    }

    public function bindings(): array
    {
        return [new HookBinding('SubagentStop')];
    }

    /**
     * A moment already TRUE by the time anything sees it — the worker has stopped. So a trigger may note
     * and may not refuse, and {@see Triggers} demotes a refusal rather than dropping it.
     */
    protected function onSubagentStop(HookEvent $event): int
    {
        $verdict = Triggers::inSession($event->sessionWorkspace())
            ->dispatch(new WorkerFinished($event->root, $event->agent()));

        // The project's own handlers first, then whatever the PROFILE bound to this moment. Both are
        // "something happens when a worker finishes" and they are different mechanisms — one is a class a
        // project wrote, the other an agent it named — so a trigger that served only the first left every
        // `on worker-finished …` binding registered, printed and inert.
        $said = new Dispatcher($event->sessionWorkspace(), $event->root)
            ->fire(self::TRIGGER, $event->agent()->type);

        foreach ($verdict->message() as $noted) {
            $said[] = $noted;
        }

        return $said === [] ? $this->pass() : $this->inject($event, implode("\n", $said));
    }
}
