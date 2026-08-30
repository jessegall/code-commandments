<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
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
     */
    protected function onStop(HookEvent $event): int
    {
        if (Instance::inSession($event->sessionWorkspace())->profile()->isNone()) {
            return $this->pass(); // Not orchestrating, so there is no role to have forgotten.
        }

        $written = new TouchedSources($event->workspace(), $event->root, Config::load($event->root), self::WATCHER)
            ->claim(self::A_BODY_OF_WORK * 2);

        if (count($written) < self::A_BODY_OF_WORK) {
            return $this->pass();
        }

        return $this->quietly($event, $this->reminder($event, $written));
    }

    /**
     * @param  list<string>  $written
     */
    private function reminder(HookEvent $event, array $written): string
    {
        $binary = Binary::in($event->root);
        $count = count($written);
        $named = implode(', ', array_map(static fn (string $file): string => basename($file), array_slice($written, 0, self::NAMED)));
        $running = count(Board::inSession($event->sessionWorkspace())->running());
        $workers = $running === 0 ? 'Nobody is holding any work' : "{$running} worker(s) are running";

        return <<<TEXT
            Code Commandments — you are ORCHESTRATING, and you have written {$count} source files yourself
            since this was last said ({$named}…). {$workers}.

            That is a body of work with a shape, and a piece of work with a shape can be given away. Your
            context is the resource the whole role spends: read reports, receipts and the record, and let
            a worker read the code. When it runs out you do not stop orchestrating — you keep going with a
            confident, stale picture of a build that has moved on, and that failure is invisible from the
            inside.

            THIS IS NOT A RULE AGAINST TOUCHING CODE. If the user asked YOU to do something, do it
            yourself — a direct request is not a delegation opportunity, and answering "I'll dispatch a
            worker" to a question is a way of not answering it. A fix in passing, a one-line correction,
            anything where explaining the work costs more than doing it: yours. The default for everything
            LARGE is a worker.

            `{$binary} build` is the board.
            TEXT;
    }
}
