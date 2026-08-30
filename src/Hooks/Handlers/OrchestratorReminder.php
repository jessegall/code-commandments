<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Holes;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Cli\Orchestration\Reminders;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\TouchedSources;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * An orchestrator forgets that it is one: the role lives in context, and context is what goes. This
 * MEASURES the forgetting rather than announcing it on a timer — it counts the source files written by
 * the orchestrator's own hand, and speaks only when those are a body of work somebody could have held.
 */
final class OrchestratorReminder extends Hook
{
    /**
     * Its own mark on the touched-source walk. Each watcher keeps one, because claiming MOVES it — two
     * hooks sharing a cursor means whichever looks first swallows the evidence.
     */
    private const string WATCHER = 'orchestrator';

    /**
     * How many source files written by the orchestrator's own hand make a body of work. One or two is a
     * fix in passing, which is the cheapest way to answer a small thing and not worth a worker. Beyond
     * that it is a piece of work with a shape, and a piece of work with a shape can be given away.
     */
    private const int A_BODY_OF_WORK = 6;

    /**
     * How many it names before the list becomes something to skim rather than read.
     */
    private const int NAMED = 4;

    public function summary(): string
    {
        return 'Reminds a session orchestrating under a profile when it has been writing the code itself.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    /**
     * Quiet, always. It is addressed to the agent about its own habits, and a user watching a build does
     * not need to be told what its orchestrator is thinking about.
     *
     * The words come through {@see Reminders} like every other hook's, rather than off the profile
     * directly: reading a reminder is one act with one rule, and a second way of doing it is a second
     * place for that rule to be got wrong.
     */
    protected function onStop(HookEvent $event): int
    {
        $workspace = $event->sessionWorkspace();

        if (Profiles::inForce($workspace)->isNone()) {
            return $this->pass(); // Not orchestrating, so there is no role to have forgotten.
        }

        $written = new TouchedSources($event->workspace(), $event->root, Config::load($event->root), self::WATCHER)
            ->claim(self::A_BODY_OF_WORK * 2);

        if (count($written) < self::A_BODY_OF_WORK) {
            return $this->pass();
        }

        foreach (Reminders::inSession($workspace)->say(self::WATCHER, $this->values($event, $written)) as $said) {
            return $this->quietly($event, 'Code Commandments — ' . $said);
        }

        return $this->pass();
    }

    /**
     * What the profile's own words are filled in with. Every one is computed HERE, at fire time — a
     * nudge that arrives wearing the voice of the system and states a stale number is worse than one
     * that states nothing, because it does not read as missing.
     *
     * @param  list<string>  $written
     */
    private function values(HookEvent $event, array $written): Holes
    {
        $binary = Binary::in($event->root);
        $count = count($written);
        $named = implode(', ', array_map(static fn (string $file): string => basename($file), array_slice($written, 0, self::NAMED)));
        $running = count(Board::inSession($event->sessionWorkspace())->running());
        $workers = $running === 0 ? 'Nobody is holding any work' : "{$running} worker(s) are running";

        return Holes::none()
            ->with('count', $count)
            ->with('files', $named)
            ->with('workers', $workers)
            ->with('binary', $binary);
    }
}
