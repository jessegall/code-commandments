<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * Says who is waiting on the orchestrator, at the moment it believes it has finished. A worker that has
 * reported is not idle and is not working — it is a person's decision that has not been made, and the
 * cost of not saying so is a finished piece of work sitting unhanded while everyone believes it is still
 * going. Ten minutes of that was noticed by the user before the orchestrator.
 */
final class BoardReminder extends Hook
{
    /**
     * How many waiting items are named before the list becomes one nobody reads.
     */
    private const int NAMED = 4;

    public function summary(): string
    {
        return 'At the end of a turn, names the work waiting on YOU — a worker that has reported is a decision nobody has made.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function onStop(HookEvent $event): int
    {
        $board = Board::inSession($event->sessionWorkspace());

        if (! $board->exists()) {
            return $this->pass(); // Nothing is claimed; there is no build to report on.
        }

        $waiting = $board->awaiting();

        if ($waiting === []) {
            return $this->pass(); // Nobody is waiting, which costs nothing to say and so is not said.
        }

        return $this->quietly($event, $this->waiting($event, $waiting));
    }

    /**
     * @param  list<Claim>  $waiting
     */
    private function waiting(HookEvent $event, array $waiting): string
    {
        $binary = Binary::in($event->root);
        $lines = array_map(
            fn (Claim $claim) => sprintf('  • %s — %s since %s, %s', $claim->item, $claim->stage->value, $claim->hold->since, $claim->stage->nextAct()),
            array_slice($waiting, 0, self::NAMED),
        );
        $work = implode("\n", $lines);
        $count = count($waiting);

        return <<<TEXT
            Code Commandments — {$count} piece(s) of work are waiting on YOU, not on a worker:

            {$work}

            Each is a decision nobody has made. `{$binary} build` is the whole board.
            TEXT;
    }
}
