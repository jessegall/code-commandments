<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments build` — who is holding what, and what is waiting on you. It needs no configuration (an
 * item is a string and a holder is a string), so it works for subagents in one checkout before any
 * branch, role or worktree exists. The screen is ordered by who is WAITING rather than by what is busy,
 * and every stanza ends with the command that acts on it — one that makes a waiting person compose the
 * verb has failed.
 */
final class BuildCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['build'];
    }

    public function help(): Help
    {
        return Help::of('Who is holding which piece of work, and what is waiting on you.')
            ->form('build', 'the whole board — what needs you first, then what is running')
            ->form('build claim <item> --by=<holder>', 'take an item; refused when somebody already holds it')
            ->form('build report <item> [--ran="<command>"]', 'file a receipt and wait for judgement. With `--ran`, the tool RUNS it and files what came back')
            ->form('build accept <item>', 'release the hold and settle it')
            ->form('build rework <item> --because="…"', 'send it back for another round — the same holder, since its context is the point')
            ->form('build release <item> --reason="…"', 'give up a hold without settling the work')
            ->option('--by=NAME', 'who is taking the item')
            ->option('--ran=CMD', 'the command whose result IS the receipt — the number filed is the one the process returned')
            ->option('--against=REF', 'the branch a receipt is measured against, so a lane number is never read as the branch\'s')
            ->option('--because=TEXT', 'why it is going back — required, and it is what the next round is told')
            ->option('--reason=TEXT', 'why a hold is being given up — required, since an abandoned item is somebody else\'s problem')
            ->note('A hold is a fact about the board, never about a process. A worker\'s process ends every '
                . 'time it reports, so a hold that ended with it would free the item in the window you are '
                . 'deciding what to hand out next. Only work being DONE occupies a slot: a reported or '
                . 'blocked item is waiting on YOU, and charging it a slot would bill you for the queue.');
    }

    public function run(Input $input): int
    {
        $board = Board::inSession(Workspace::ofSession($this->io->projectRoot()));

        return match ($input->firstArgument()->unwrapOr('show')) {
            'claim' => $this->claim($board, $input),
            'report' => $this->report($board, $input),
            'accept' => $this->settle($board, $input, Stage::Accepted, 'accepted'),
            'rework' => $this->rework($board, $input),
            'release' => $this->release($board, $input),
            default => $this->show($board),
        };
    }

    /**
     * The board, ordered by who is waiting. Everything printed is read now.
     */
    private function show(Board $board): int
    {
        $claims = $board->claims();

        if ($claims === []) {
            return $this->console->say('Nothing is claimed. `commandments build claim <item> --by=<who>` takes one.');
        }

        $awaiting = $board->awaiting();
        $running = $board->running();

        if ($awaiting !== []) {
            $this->console->say(Text::heading('waiting on you (' . count($awaiting) . ')'), '');

            foreach ($awaiting as $claim) {
                $this->console->say($claim->render(), '      → ' . $claim->stage->nextAct(), '');
            }
        }

        $this->console->say(Text::heading(sprintf('working (%d of %d)', count($running), $board->limit())), '');

        foreach ($running as $claim) {
            $this->console->say($claim->render());
        }

        return $this->console->say('', ...$this->crowding($board, count($running)));
    }

    /**
     * What to say about how many are running — the soft limit is said once when crossed and never
     * enforced, because the person running the build knows it better than a default does.
     *
     * @return list<string>
     */
    private function crowding(Board $board, int $running): array
    {
        if ($running <= $board->preferred()) {
            return [];
        }

        return [sprintf(
            '  %d running; %d is where this goes best — the third one\'s cost is paid by the other two waiting on you.',
            $running,
            $board->preferred(),
        )];
    }

    private function claim(Board $board, Input $input): int
    {
        $item = $input->argument(1)->unwrapOr('');
        $holder = $input->option('by')->unwrapOr('');

        if ($item === '' || $holder === '') {
            return $this->console->say('Say what and who: `commandments build claim <item> --by=<holder>`.');
        }

        foreach ($board->claim($item, $holder, gmdate('H:i')) as $claim) {
            return $this->console->say("▸ {$holder} holds {$item}.");
        }

        $held = $board->on($item)->unwrap();

        return $this->console->say(
            "{$item} is already held by {$held->hold->holder}, since {$held->hold->since} (round {$held->round}).",
            'Send that worker back instead — its context is the reason it is still alive.',
        );
    }

    /**
     * File a receipt. With `--ran` the tool runs the command itself, so the number recorded is the one a
     * process returned rather than one an agent typed.
     */
    private function report(Board $board, Input $input): int
    {
        $item = $input->argument(1)->unwrapOr('');

        if ($board->on($item)->isNone()) {
            return $this->console->say("Nobody holds {$item} — claim it first.");
        }

        $board->move($item, Stage::Reported);

        foreach ($input->option('ran') as $argv) {
            $receipt = new Verification($this->io->projectRoot())
                ->of($item, $argv, $input->option('against')->unwrapOr(''));

            Receipts::inSession(Workspace::ofSession($this->io->projectRoot()))->file($receipt);

            return $this->console->say("▸ {$item} reported.", $receipt->render());
        }

        return $this->console->say(
            "▸ {$item} reported — with no receipt.",
            '  Nothing measured it, so this is your word for it. `--ran="<command>"` files what a process actually said.',
        );
    }

    private function rework(Board $board, Input $input): int
    {
        $item = $input->argument(1)->unwrapOr('');
        $because = $input->option('because')->unwrapOr('');

        if ($because === '') {
            return $this->console->say('Say why: `build rework <item> --because="…"` — it is what the next round is told.');
        }

        if ($board->on($item)->isNone()) {
            return $this->console->say("Nobody holds {$item}.");
        }

        $board->rework($item);
        $claim = $board->on($item)->unwrap();

        return $this->console->say(
            "▸ {$item} back to {$claim->hold->holder}, round {$claim->round}.",
            '  ' . $because,
        );
    }

    private function release(Board $board, Input $input): int
    {
        if ($input->option('reason')->unwrapOr('') === '') {
            return $this->console->say('Say why: `build release <item> --reason="…"` — an abandoned item becomes somebody else\'s problem.');
        }

        return $this->settle($board, $input, Stage::Accepted, 'released');
    }

    private function settle(Board $board, Input $input, Stage $stage, string $said): int
    {
        $item = $input->argument(1)->unwrapOr('');

        if ($board->on($item)->isNone()) {
            return $this->console->say("Nobody holds {$item}.");
        }

        $board->move($item, $stage);

        return $this->console->say("✓ {$item} {$said}. The hold is free and the worker may exit.");
    }
}
