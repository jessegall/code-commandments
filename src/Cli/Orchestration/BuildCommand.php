<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
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
        private readonly GitFiles $git = new GitFiles,
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
            ->form('build assign <role> --to=<agent-id>', 'give a role to an agent ALREADY ALIVE — its type was fixed at spawn, and the agents worth a role are the ones a respawn would ruin')
            ->form('build roles', 'who holds which role')
            ->option('--to=ID', 'the agent id taking the role, as its listing reports it')
            ->form('build orphan <item>', 'the holder is GONE — the item returns to unclaimed and the record says the work was abandoned rather than judged')
            ->form('build log', 'every measurement filed, and what it measured — the observed record, not anybody\'s account of it')
            ->form('build doctor', 'what state everything is in, computed now — for when something has gone wrong and you do not know what')
            ->option('--by=NAME', 'who is taking the item')
            ->option('--ran=CMD', 'the command whose result IS the receipt — the number filed is the one the process returned')
            ->option('--against=REF', 'the branch a receipt is measured against, so a lane number is never read as the branch\'s')
            ->option('--needs=CMD', 'a precondition — where it fails the check is not run, and the receipt says it COULD NOT MEASURE rather than reporting the environment\'s failure as the work\'s')
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
            'assign' => $this->assign($input),
            'roles' => $this->roles(),
            'orphan' => $this->orphan($board, $input),
            'log' => $this->log($board),
            'doctor' => $this->doctor($board),
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

    /**
     * Give a role to an agent already running. A type is fixed at spawn, so without this the only way to
     * have a reviewer is to spawn one — and respawning a standing reviewer discards the accumulated
     * judgement that is the entire reason it was kept alive.
     */
    private function assign(Input $input): int
    {
        $role = $input->argument(1)->unwrapOr('');
        $id = $input->option('to')->unwrapOr('');

        if ($role === '' || $id === '') {
            return $this->console->say('Say which and who: `commandments build assign <role> --to=<agent-id>`.');
        }

        Roles::inSession(Workspace::ofSession($this->io->projectRoot()))->assign($id, $role);

        return $this->console->say("▸ {$id} is `{$role}`.", '  It keeps everything it already knows.');
    }

    private function roles(): int
    {
        $roles = Roles::inSession(Workspace::ofSession($this->io->projectRoot()))->all();

        if ($roles === []) {
            return $this->console->say(
                'No roles assigned. An agent spawned under its own type needs none;',
                '`commandments build assign <role> --to=<agent-id>` points one at an agent already alive.',
            );
        }

        foreach ($roles as $id => $role) {
            $this->console->say(sprintf('  %-20s %s', $role, $id));
        }

        return 0;
    }

    /**
     * The holder is gone — a worker that vanished rather than finished. The item returns to unclaimed and
     * the record says so, because the alternative verbs would all lie: `rework` names a holder that is not
     * there, and `--replace` files a judgement about the work when this is an obituary about the worker.
     */
    private function orphan(Board $board, Input $input): int
    {
        $item = $input->argument(1)->unwrapOr('');

        if ($board->on($item)->isNone()) {
            return $this->console->say("Nobody holds {$item}.");
        }

        $holder = $board->on($item)->unwrap()->hold->holder;
        $board->move($item, Stage::Abandoned);

        return $this->console->say(
            "▸ {$item} is unclaimed — {$holder} is gone.",
            '  Recorded as abandoned, not judged: nothing here says the work was wrong.',
            "  `commandments build claim {$item} --by=<who>` gives it to somebody.",
        );
    }

    /**
     * Every measurement filed. It shows what a tool READ, so a reader can tell a measured number from a
     * reported one without going to ask.
     */
    private function log(Board $board): int
    {
        $receipts = Receipts::inSession(Workspace::ofSession($this->io->projectRoot()));
        $claims = $board->claims();

        if ($claims === []) {
            return $this->console->say('Nothing has been claimed, so nothing has been measured.');
        }

        $this->console->say(Text::heading('the record'), '');

        foreach ($claims as $claim) {
            $this->console->say($claim->render());

            foreach ($receipts->latestFor($claim->item) as $receipt) {
                $this->console->say($receipt->render());
            }

            if ($receipts->latestFor($claim->item)->isNone()) {
                $this->console->say('  no receipt — nothing measured this, so it is somebody\'s word for it');
            }

            $this->console->say('');
        }

        return 0;
    }

    /**
     * What state everything is in, every line computed in this invocation and every line ending in what
     * to do about it. It exists for the moment somebody does not know what is going on — which is exactly
     * the moment a remembered number would mislead them.
     */
    private function doctor(Board $board): int
    {
        if (! $board->exists()) {
            $this->console->say('No build here. `commandments build claim <item> --by=<who>` starts one.');

            return $this->sayStranded();
        }

        $receipts = Receipts::inSession(Workspace::ofSession($this->io->projectRoot()));
        $running = count($board->running());

        $this->console->say(Text::heading('doctor'), '');

        foreach ($board->claims() as $claim) {
            $measured = $receipts->latestFor($claim->item)
                ->mapOr('no receipt', fn (Receipt $receipt) => $receipt->verdict());

            $this->console->say(sprintf(
                '  %-22s %-10s round %d  %-18s → %s',
                $claim->item,
                $claim->stage->value,
                $claim->round,
                $measured,
                $claim->stage->nextAct(),
            ));
        }

        $this->console->say('', sprintf('  %d of %d slots in use, counted now.', $running, $board->limit()));

        return $this->sayStranded();
    }

    /**
     * Name any board left inside a worktree — most useful when THIS one is empty, since that is the shape
     * a reader would otherwise resolve by finding two answers that disagree.
     */
    private function sayStranded(): int
    {
        foreach ($this->strandedBoards() as $path) {
            $this->console->say(
                '',
                "  ! a board also exists at {$path} — it is not this one.",
                '    Anything filed there is unread: it was written before boards were anchored to the project.',
            );
        }

        return 0;
    }

    /**
     * Boards left inside a worktree, from before they were anchored to the project. Nothing reads them
     * any more, so a reader has to be TOLD they exist — otherwise the only way to find one is a
     * contradiction between two answers, each perfectly consistent with itself.
     *
     * @return list<string>
     */
    private function strandedBoards(): array
    {
        $root = Workspace::ofSession($this->io->projectRoot())->root();
        $stranded = [];

        foreach ($this->git->worktrees($root) as $worktree) {
            foreach (glob($worktree . '/.commandments/sessions/*/.board') ?: [] as $board) {
                $stranded[] = $board;
            }
        }

        return $stranded;
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
            return $this->console->say(
                "Nobody holds {$item}.",
                "  Claim it first: `commandments build claim {$item} --by=<you>`",
            );
        }

        $board->move($item, Stage::Reported);

        foreach ($input->option('ran') as $argv) {
            $receipt = new Verification($this->io->projectRoot())
                ->of($item, $argv, $input->option('against')->unwrapOr(''), $input->option('needs')->toNullable());

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
            return $this->console->say(
                "Nobody holds {$item}.",
                "  Claim it first: `commandments build claim {$item} --by=<who>`",
            );
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
