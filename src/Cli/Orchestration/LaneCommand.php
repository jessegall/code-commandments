<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments lane open <name>` — a place for a worker to work, in one command. It adds the worktree
 * and then runs the profile's own `lane.sh`, because what makes a worktree USABLE is per-project: a
 * vendor copy, node_modules, a database, a port block. The package cannot know those and does not guess;
 * the project writes the script and the tool runs it every time, which is the part that was going wrong
 * by hand.
 */
final class LaneCommand implements Command
{

    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
        private readonly GitFiles $git = new GitFiles,
    ) {}

    public function names(): array
    {
        return ['lane'];
    }

    public function help(): Help
    {
        return Help::of('Open a place for a worker to work — the worktree, and whatever your project needs in it.')
            ->form('lane open <name>', 'add the worktree and run the profile\'s `lane.sh` in it')
            ->form('lane list', 'every lane, and which version of this package each one runs')
            ->option('--from=REF', 'the branch to cut from (default: the current one)')
            ->option('--at=PATH', 'where to put it (default: `.lanes/<name>`, or what the profile says)')
            ->note('The setup lives in `lane.sh` inside the profile, not in this command. A worktree checks '
                . 'out tracked files and nothing else — no vendor, no node_modules, no database — and what '
                . 'it takes to fix that is the project\'s business. Writing it down means every lane gets '
                . 'the same treatment, including the ones opened at 2am.');
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::ofSession($this->io->projectRoot());

        return match ($input->firstArgument()->unwrapOr('list')) {
            'open', 'add' => $this->open($workspace, $input),
            default => $this->list($workspace),
        };
    }

    private function open(Workspace $workspace, Input $input): int
    {
        $name = $input->argument(1)->unwrapOr('');

        if ($name === '') {
            return $this->console->say('Name it: `commandments lane open <name>`.');
        }

        $root = $workspace->root();
        $at = $input->option('at')->unwrapOr(Checkout::homeFor($workspace, $root) . '/' . $name);
        $from = $input->option('from')->unwrapOr(trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD 2>/dev/null')));

        if (is_dir($at)) {
            return $this->console->say("`{$name}` already exists at {$at}.");
        }

        $added = 0;
        passthru(
            'git -C ' . escapeshellarg($root) . ' worktree add -b ' . escapeshellarg($name)
            . ' ' . escapeshellarg($at) . ' ' . escapeshellarg($from) . ' 2>&1',
            $added,
        );

        if ($added !== 0) {
            return $this->console->say('', "Could not add the worktree — nothing was set up.");
        }

        return $this->prepare($workspace, $name, $at);
    }

    /**
     * Run the profile's own setup. Without one the lane is a bare checkout, which is the state that
     * silently tests the wrong thing — so that is said rather than left to be discovered.
     */
    private function prepare(Workspace $workspace, string $name, string $at): int
    {
        // The lane's WORLD, before anything runs in it. A worker sent here inherits the project's
        // instructions, skills and hooks otherwise — including one that holds its stop, which it can
        // then never satisfy. Named for the lane, since the lane is what the worker is.
        $world = World::forWorker($workspace, $workspace->root(), $name);

        if (! $world->prepare()) {
            $this->console->say('', "! could not prepare the lane's world at {$world->path()}.");
        }

        foreach (Profiles::inForce($workspace) as $running) {
            foreach ($running->setupScript() as $script) {
                $this->console->say('', "▸ {$name} at {$at}", "  running {$script}", '');

                $ran = new Checkout($at)->prepareWith($script);

                return $ran === 0
                    ? $this->console->say('', "✓ {$name} is ready.", "  world: {$world->path()} — hand it over as CLAUDE_CONFIG_DIR")
                    : $this->console->say('', "! the setup exited {$ran}. The worktree exists and is NOT prepared.");
            }
        }

        return $this->console->say(
            '',
            "▸ {$name} at {$at} — a bare checkout.",
            '  No `lane.sh` in the profile, so nothing was installed: no vendor, no node_modules, no',
            '  database. A lane in that state runs its gates against nothing and reports green.',
            '',
            '  Write one at `.commandments/orchestrator/profiles/<profile>/' . Profile::SETUP . '`.',
        );
    }

    /**
     * Every lane, and the version of this package each one runs — a lane keeps the vendor it was seeded
     * with, so one opened last week judges the branch by last week's rules and says nothing about it.
     */
    private function list(Workspace $workspace): int
    {
        $root = $workspace->root();
        $lanes = Checkout::lanesOf($root, $this->git);

        if ($lanes === []) {
            return $this->console->say('No lanes. `commandments lane open <name>` makes one.');
        }

        $mine = new Checkout($root)->version();

        foreach ($lanes as $lane) {
            $this->console->say($lane->row($mine));
        }

        return $this->console->say(
            '',
            "  the project runs {$mine}; a lane marked ! runs something else.",
            '  `commandments upgrade` brings them all forward.',
        );
    }

}
