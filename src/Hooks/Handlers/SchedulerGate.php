<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Cli\Orchestration\Scheduler;
use JesseGall\CodeCommandments\Cli\Orchestration\Watching;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Nothing happens while nobody is scheduling: an orchestrator whose scheduler is not running writes work
 * into a file nothing drains, and every layer above it says fine. The Agent tool is always let through,
 * since a gate that blocks the only thing able to lift it is a deadlock rather than a gate.
 */
final class SchedulerGate extends Hook
{
    /**
     * What may run while no scheduler is watching. The Agent tool starts one, and our own CLI is how the
     * state is read and repaired — refusing those would leave no way out of the refusal.
     */
    private const array ALLOWED = ['Agent', 'Task'];

    public function summary(): string
    {
        return 'Refuses tool use while a session is orchestrating and no scheduler is watching the dispatch list.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreToolUse')];
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        $workspace = $event->sessionWorkspace();

        if (Profiles::inForce($workspace)->isNone()) {
            return $this->pass(); // Not orchestrating: there is nothing to schedule and nobody to hold.
        }

        if (in_array($event->tool(), self::ALLOWED, true) || Watching::inSession($workspace)->isWatching()) {
            return $this->pass();
        }

        return $this->block($this->refusal($event));
    }

    private function refusal(HookEvent $event): string
    {
        $workspace = $event->sessionWorkspace();
        $waiting = count(Pending::inSession($workspace)->all());
        $owed = $waiting === 0
            ? 'Nothing is waiting yet, and that is exactly when to start it — a trigger fires without asking.'
            : "{$waiting} piece(s) of work are already waiting and nothing is draining them.";
        $brief = new Scheduler($event->root, Pending::inSession($workspace)->path())->brief();

        return <<<TEXT
            No SCHEDULER is watching, so nothing you do can reach an agent.

            {$owed}

            Start it now with the Agent tool — it is the one tool this refusal lets through — on a small
            model, with this as its whole prompt:

            {$brief}
            TEXT;
    }
}
