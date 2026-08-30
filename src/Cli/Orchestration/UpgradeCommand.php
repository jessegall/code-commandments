<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * `commandments upgrade` — move this package and bring every checkout of the project with it, because by
 * hand that is three steps and the third is neither optional nor discoverable: a lane keeps the vendor it
 * was seeded with, so a root update leaves it judging by old rules while answering about the new ones
 * confidently and wrongly. `lane list` could already diagnose that drift and nothing repaired it. It does
 * less than asked in exactly one place — a lane whose worker is still working is named and left alone.
 */
final class UpgradeCommand implements Command
{
    /**
     * What a lane's row says once it has been brought forward. Anything else in that column means the
     * lane is still on its old vendor, whatever the reason — which is the one distinction the caller
     * needs, since a skipped lane and a failed one are equally out of date.
     */
    private const string REFRESHED = 'refreshed';

    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
        private readonly GitFiles $git = new GitFiles,
        private readonly Composer $composer = new Composer,
    ) {}

    public function names(): array
    {
        return ['upgrade'];
    }

    public function help(): Help
    {
        return Help::of('Move this package to what your project can install, and bring every lane with it.')
            ->form('upgrade', 'update the package, sync, and re-prepare every lane no worker is standing in')
            ->form('upgrade --check', 'what is installed, what is available, and which lanes are behind — changes nothing')
            ->form('upgrade --no-sync', 'update and re-prepare the lanes, but do not republish the skills')
            ->option('--check', 'read only: name the installed version AND the available one. It exits non-zero when it could not find out what is available, because not knowing is not the same as being current')
            ->option('--no-sync', 'skip the sync. Nobody has wanted this yet — forgetting it leaves the skills stale while the binary is new, which is the same drift as a lane, one directory up')
            ->note('A lane keeps its own copied `vendor/`, so an update in the project leaves every lane '
                . 'running the version it was opened with. That is the step this exists for: nothing else '
                . 'repairs it, and a lane that is behind still answers, which is what makes it expensive.')
            ->note('A lane a worker is WORKING in is skipped and named. The board knows which those are, '
                . 'and swapping a vendor under a running builder turns its next gate failure into a '
                . 'mystery. Run it again once they report.')
            ->note('The per-lane preparation is the profile\'s own `' . Profile::SETUP . '` — the project '
                . 'decides what standing a lane up means (a vendor copy, node_modules, a database, a port '
                . 'block), and this re-runs it. Without one there is nothing to re-run, and that is said '
                . 'rather than left to be discovered.');
    }

    public function run(Input $input): int
    {
        // The MAIN worktree, never wherever the process is standing. A lane is its own git toplevel and
        // carries its own `CLAUDE_PROJECT_DIR`, so both the shell and the environment give the LANE's
        // answer inside a lane — and upgrading that instead of the project is the exact confusion this
        // command exists to end. {@see Workspace::ofSession} is the one place that resolution is settled.
        $workspace = Workspace::ofSession($this->io->projectRoot());

        return $input->hasFlag('check')
            ? $this->check($workspace)
            : $this->move($workspace, $input);
    }

    /**
     * What is installed, what is available, and what is behind — measured, and nothing else said. BOTH
     * numbers are named because "described as shipped" and "installable" are different sentences that came
     * apart twice on a real build, and one word should settle which one you are looking at.
     */
    private function check(Workspace $workspace): int
    {
        $root = $workspace->root();
        $project = new Checkout($root);

        $this->console->say(Text::heading('code-commandments'), '');
        $this->console->say(sprintf('  %-11s %-18s %s', 'installed', $project->version(), $root));

        if ($project->isThePackage()) {
            return $this->console->refuse('', ...$this->itsOwnCheckout());
        }

        if (! $project->hasThePackage()) {
            return $this->console->refuse(
                '',
                '  ' . Checkout::PACKAGE . ' is not installed here, so there is nothing to compare it with.',
                '  `composer require --dev ' . Checkout::PACKAGE . '` installs it.',
            );
        }

        $release = $this->composer->latestFor($root);

        $this->console->say(sprintf('  %-11s %-18s %s', 'available', $release->render(), 'composer, asked in this project'));

        $this->lanes($workspace, $project->version(), fn (Checkout $lane) => $this->wouldDo($lane, $workspace));

        // A read that could not read is not a green. Anything chaining behind this has to be able to tell
        // "up to date" from "nobody found out", and the exit code is the only half of the answer a script
        // ever sees.
        foreach ($release->reason() as $why) {
            return $this->console->refuse(
                '',
                '  ' . Release::COULD_NOT_MEASURE . ' what is available, so whether this project is behind is UNKNOWN.',
                '  ' . $why,
            );
        }

        return $this->console->say('', ...$this->verdict($project, $release));
    }

    /**
     * What the two numbers mean together, said in one line.
     *
     * @return list<string>
     */
    private function verdict(Checkout $project, Release $release): array
    {
        return $release->isAheadOf($project->version())
            ? ['  ! behind — `commandments upgrade` moves the project and every lane with it.']
            : ['  ✓ the project runs what it can install.'];
    }

    /**
     * Move it: the package, then the skills, then every lane. Each step is taken only when the one before
     * it worked — a sync onto a failed update republishes the old curriculum and reports success.
     */
    private function move(Workspace $workspace, Input $input): int
    {
        $root = $workspace->root();
        $project = new Checkout($root);

        if ($project->isThePackage()) {
            return $this->console->refuse(...$this->itsOwnCheckout());
        }

        if (! $project->hasThePackage()) {
            return $this->console->refuse(
                Checkout::PACKAGE . " is not installed in {$root} — there is nothing here to move.",
                '`composer require --dev ' . Checkout::PACKAGE . '` installs it, and `commandments install` wires it.',
            );
        }

        $this->console->say(Text::heading('code-commandments'), '', "  was  {$project->version()}", '');

        $updated = $this->composer->update($root);

        if ($updated !== 0) {
            return $this->console->refuse(
                '',
                "  composer exited {$updated}. Nothing else ran: the skills were not republished, and no lane was touched.",
            );
        }

        $this->console->say('', "  now  {$project->version()}", '');

        $synced = $this->syncUnless($input->hasFlag('no-sync'), $root);

        if ($synced !== 0) {
            return $this->console->refuse(
                '',
                "  `sync` exited {$synced}, so this project's skills are the OLD ones against a new binary.",
                '  No lane was touched — fix that first, then run this again.',
            );
        }

        $behind = $this->refreshLanes($workspace, $project->version());

        $this->console->say('', ...($input->hasFlag('no-sync') ? $this->skillsWereLeftAlone() : $this->guarantee()));

        if ($behind === []) {
            return $this->console->say('');
        }

        return $this->console->refuse(
            '',
            '  ' . count($behind) . ' lane(s) still run their old vendor: ' . implode(', ', $behind) . '.',
            '  Run `commandments upgrade` again once their workers report — a lane that is behind still',
            '  answers questions, which is what makes it expensive.',
        );
    }

    /**
     * Republish the skills, unless the caller asked not to. Skipping is a 0 because nothing failed: the
     * caller said no, and reporting their choice as an error would stop the lanes being brought forward.
     */
    private function syncUnless(bool $skipped, string $root): int
    {
        return $skipped ? 0 : $this->composer->sync($root);
    }

    /**
     * Re-prepare every lane no worker is standing in, and print the table. Answers the names of the lanes
     * left behind, whatever the reason.
     *
     * @return list<string>
     */
    private function refreshLanes(Workspace $workspace, string $against): array
    {
        $behind = [];

        $this->lanes($workspace, $against, function (Checkout $lane) use ($workspace, &$behind): string {
            $note = $this->refresh($lane, $workspace);

            if ($note !== self::REFRESHED) {
                $behind[] = $lane->name();
            }

            return $note;
        });

        return $behind;
    }

    /**
     * Bring one lane forward, or say why it was left. The board is re-read per lane on purpose: a worker
     * that claimed while an earlier lane was being prepared is respected, where one read at the top would
     * have been a fact about a minute ago.
     */
    private function refresh(Checkout $lane, Workspace $workspace): string
    {
        foreach ($this->board($workspace)->workingIn($lane->name()) as $claim) {
            return "held by {$claim->hold->holder} ({$claim->item}) — SKIPPED";
        }

        foreach ($this->setupScript($workspace) as $script) {
            $ran = $lane->prepareWith($script);

            return $ran === 0 ? self::REFRESHED : '`' . basename($script) . "` exited {$ran} — NOT refreshed";
        }

        return 'no `' . Profile::SETUP . '` in the profile — NOT refreshed';
    }

    /**
     * What `--check` would do to a lane, without doing any of it.
     */
    private function wouldDo(Checkout $lane, Workspace $workspace): string
    {
        foreach ($this->board($workspace)->workingIn($lane->name()) as $claim) {
            return "held by {$claim->hold->holder} ({$claim->item}) — would be skipped";
        }

        return $this->setupScript($workspace)->isSome()
            ? 'would be re-prepared'
            : 'no `' . Profile::SETUP . '` in the profile — nothing would re-prepare it';
    }

    /**
     * Every lane, in the `lane list` format, with whatever $note has to say about each — the same
     * {@see Checkout::row} `lane list` prints, because the moment that table is most wanted is the moment
     * something has just moved.
     *
     * @param  callable(Checkout): string  $note
     */
    private function lanes(Workspace $workspace, string $against, callable $note): void
    {
        $lanes = Checkout::lanesOf($workspace->root(), $this->git);

        $this->console->say(Text::heading('lanes'), '');

        if ($lanes === []) {
            $this->console->say('  None. `commandments lane open <name>` makes one.');

            return;
        }

        foreach ($lanes as $lane) {
            $this->console->say('  ' . $lane->row($against, $note($lane)));
        }

        $this->console->say('', "  a lane marked ! runs something other than {$against}.", ...$this->basis($workspace));
    }

    /**
     * WHERE the "who is working" answer came from. The board is session state, so one read in another
     * session is empty — and an empty board means "nothing is held" only if it is the right board. Naming
     * it is the difference between a fact and an assumption a reader inherits.
     *
     * @return list<string>
     */
    private function basis(Workspace $workspace): array
    {
        $session = $workspace->sessionKey();

        return $this->board($workspace)->exists()
            ? ["  holds read from the board of session `{$session}`."]
            : ["  session `{$session}` has no board, so NOTHING was known to be held. If workers are running", '  under another session, run this from theirs.'];
    }

    /**
     * What to say in the one repository this cannot move: its own. There is no version to install here —
     * the checkout IS the package — and saying "not installed" instead would send a reader to run a
     * `composer require` that must never succeed.
     *
     * @return list<string>
     */
    private function itsOwnCheckout(): array
    {
        return [
            '  This checkout IS ' . Checkout::PACKAGE . ', so there is no version of it to install here.',
            '  Its own lanes are brought forward by whatever `' . Profile::SETUP . '` copies into them.',
        ];
    }

    /**
     * What this run promises about the files you wrote yourself. Stated rather than assumed: `sync` once
     * deleted a project's own gitignore exception, and an `upgrade` that wraps it inherits that whole
     * class of mistake — so the guarantee is printed where somebody would notice it being false.
     *
     * @return list<string>
     */
    private function guarantee(): array
    {
        return [
            '  Nothing you wrote was replaced: `sync` writes only between its own markers and refuses a',
            '  file whose markers it cannot read, keeps the lines you added to `.commandments/.gitignore`,',
            '  and never touches `.commandments/custom/` or a hook it did not stamp.',
        ];
    }

    /**
     * @return list<string>
     */
    private function skillsWereLeftAlone(): array
    {
        return ['  --no-sync: the skills in this project are still the ones the OLD version published.'];
    }

    private function board(Workspace $workspace): Board
    {
        return Board::inSession($workspace);
    }

    /**
     * The project's own per-lane setup, from the profile this session is running under.
     *
     * @return Option<string>
     */
    private function setupScript(Workspace $workspace): Option
    {
        return Profiles::inForce($workspace)
            ->andThen(static fn (Profile $profile): Option => $profile->setupScript());
    }
}
