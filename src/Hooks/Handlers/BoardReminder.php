<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
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

    /**
     * How much work goes by before the routine is worth another reading.
     *
     * The number matters more than the mechanism. Six tool calls is not a stretch — every stop has more
     * than six in it, so a threshold that small fires at every stop however the pacing is measured, and
     * the checklist becomes the wallpaper it exists to avoid. This is a BODY of work: a stretch long
     * enough that coming to rest is a real event.
     */
    public const int A_STRETCH = 60;

    public function summary(): string
    {
        return 'At the end of a turn, names the work waiting on YOU, and repeats the profile\'s standing routine.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function onStop(HookEvent $event): int
    {
        $board = Board::inSession($event->sessionWorkspace());
        $said = array_filter([$this->pending($event, $board), $this->routine($event)]);

        if ($said === []) {
            return $this->pass(); // Nobody waiting and no routine — which costs nothing to say, and so is not said.
        }

        return $this->quietly($event, implode("\n\n", $said));
    }

    /**
     * The work waiting on the reader, or nothing when a build is not running or nobody is waiting.
     */
    private function pending(HookEvent $event, Board $board): string
    {
        if (! $board->exists()) {
            return ''; // Nothing is claimed; there is no build to report on.
        }

        $waiting = $board->awaiting();

        return $waiting === [] ? '' : $this->waiting($event, $waiting);
    }

    /**
     * The profile's standing routine — what its author decided is done every time the work comes to a
     * rest. A nudge and never a gate: a habit worth repeating is not a rule worth refusing over.
     *
     * Once per STRETCH OF WORK rather than once per stop, because a checklist repeated where nothing has
     * happened is a nudge with nothing new in it, and one of those every time teaches a reader to skim
     * the block that will eventually hold something.
     */
    private function routine(HookEvent $event): string
    {
        $workspace = $event->sessionWorkspace();

        foreach (Instance::inSession($workspace)->profile() as $name) {
            foreach (Profiles::of($workspace)->named($name) as $profile) {
                foreach ($profile->document('routine') as $routine) {
                    // The mark is spent LAST, once there is a routine to say. Marking before knowing
                    // burns the first reading — which is owed unconditionally — on a stop that said
                    // nothing, and a profile started mid-session then waits a stretch for its own.
                    $work = Journal::inSession($workspace)->calls();

                    if (! $this->workMovedOn($event, 'orchestrator-routine', $work, self::A_STRETCH)) {
                        return '';
                    }

                    return "Code Commandments — the `{$name}` routine, every time you come to a stop:\n\n" . trim($routine);
                }
            }
        }

        return '';
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
