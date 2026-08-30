<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments orchestrate` — what this project would declare for the refusals to apply, read from the
 * shape it already has, printed rather than written. Its more useful half is WHAT IT CANNOT DO: a rule
 * left inert because nothing distinguishes one agent from another is worse than an absent one, since it
 * reads as protection — so it is said while somebody is deciding to turn it on, rather than discovered by
 * a merge going through that should not have.
 */
final class OrchestrateCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['orchestrate'];
    }

    public function help(): Help
    {
        return Help::of('What this project would declare to turn the orchestration refusals on — read from the shape it already has, and what it cannot do yet.')
            ->form('orchestrate new <name>', 'write a profile — a way of working, in markdown, kept in git')
            ->form('orchestrate use <name>', 'work under it for this session')
            ->form('orchestrate list', 'the profiles this project has written')
            ->form('orchestrate show [name]', 'read one out — what an orchestrator loads instead of copying a brief by hand')
            ->form('orchestrate stop', 'stop working under a profile; the profile is untouched')
            ->form('orchestrate settings', "what the profile in force turns ON, and whether it has ever actually FIRED")
            ->form('orchestrate moments', 'every moment a profile can bind to, and WHAT EACH ONE CARRIES')
            ->form('orchestrate test <moment>', 'dispatch it once, now, with a rehearsal subject, and print what the agent was handed — the only way to tell a healthy binding from a working one')
            ->form('orchestrate on <trigger> <agent> <procedure>', 'when <trigger> fires, <agent> carries out <procedure> — both must exist in the profile')
            ->form('orchestrate off <trigger> [<agent>] [<procedure>]', 'unbind; an omitted agent or procedure means ANY, so `off commit` drops the whole trigger')
            ->form('orchestrate template list', 'the documents and roles this package ships as a starting point')
            ->form('orchestrate template show <name>', 'read one out before taking it')
            ->form('orchestrate template use <name>', 'write it into the profile in force — never over a file you already have')
            ->form('orchestrate plan', 'the whole tree with depth, and where you are standing in it')
            ->form('orchestrate plan open "<title>"', 'start this session\'s plan — the work you came here to do')
            ->form('orchestrate plan add <name> "<why>"', 'a SIDEQUEST under wherever you are standing, and stand in it. A detour is cheap to declare and impossible to reconstruct later')
            ->form('orchestrate plan up "<the reason>"', 'close this level and surface one. The reason goes UP into the parent; the folder goes')
            ->form('orchestrate plan go [<name>|..|<a>/<b>]', 'stand somewhere that already exists — creating and closing nothing. Bare returns to the plan; `..` surfaces one WITHOUT closing it')
            ->form('orchestrate plan where', 'the path from the plan to here — what you were doing before the detour')
            ->form('orchestrate plan stale [--for=N]', 'live branches nobody has touched for N minutes (default 60)')
            ->form('orchestrate assistant <name> <section> "<text>"', 'APPEND one line to a role — `caught`, `behaviour`, `restrictions`, `brief`. Stamped with the day and the sha, which is the metadata you would forget to type')
            ->form('orchestrate profile <document> "<text>"', 'append to a profile-level document — ' . implode(', ', array_map(fn (string $d) => "`{$d}`", array_keys(Profile::DOCUMENTS))))
            ->option('--set', 'replace the document rather than adding to it — the rare case')
            ->option('--for', 'with `plan stale`: how many minutes untouched counts as stale (default 60)')
            ->form('orchestrate', 'the declaration to paste, and what will NOT be enforced until something changes')
            ->form('orchestrate --write', 'splice that declaration into .commandments/config.php, refusing to overwrite one already declared')
            ->option('--write', 'write the proposal into .commandments/config.php instead of printing it to paste')
            ->note('A PROFILE is the durable half — how a team works, in `.commandments/orchestrator/'
                . 'profiles/<name>/`, committed and reviewed in a diff. An INSTANCE is the live half and '
                . 'belongs to the session, so a restart loses what was bound to a process and keeps what '
                . 'was written down. A profile names no branch, port or lane: those are one build rather '
                . 'than a way of working. ')
            ->note('The bare form prints; `--write` splices the block in through the AST, keeping your config\'s own '
                . 'formatting, and refuses rather than overwrite a block already declared — an orchestration block '
                . 'is a decision about how a team works and belongs in a diff somebody read. The runtime half — '
                . '`commandments build` — needs none of this and works with nothing declared at all.');
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::ofSession($this->io->projectRoot());

        return match ($input->firstArgument()->unwrapOr('propose')) {
            'new' => $this->scaffold($workspace, $input->argument(1)->unwrapOr('')),
            'use' => $this->use($workspace, $input->argument(1)->unwrapOr('')),
            'list' => $this->list($workspace),
            'show' => $this->show($workspace, $input->argument(1)->unwrapOr('')),
            'stop' => $this->stop($workspace),
            'on', 'enable' => $this->switch($workspace, $input, on: true),
            'off', 'disable' => $this->switch($workspace, $input, on: false),
            'settings' => $this->settings($workspace),
            'moments' => $this->moments(),
            'test' => $this->rehearse($workspace, $input),
            'plan' => $this->plan($workspace, $input),
            'template', 'templates' => $this->template($workspace, $input),
            'assistant', 'role' => $this->write(
                $workspace,
                'roles/' . $input->argument(1)->unwrapOr(''),
                $input->argument(2)->unwrapOr(''),
                $this->rest($input, from: 3),
                $input->hasFlag('set'),
            ),
            'profile' => $this->write(
                $workspace,
                $input->argument(1)->unwrapOr(''),
                '',
                $this->rest($input, from: 2),
                $input->hasFlag('set'),
            ),
            default => $this->propose($input),
        };
    }

    /**
     * The templates this package ships. A scaffold asks a question and leaves a blank; a template shows
     * an answer somebody already found worth keeping, which is the difference between a file a project
     * fills in and one it stares at.
     */
    private function template(Workspace $workspace, Input $input): int
    {
        $templates = Templates::shipped();

        return match ($input->argument(1)->unwrapOr('list')) {
            'show' => $this->showTemplate($templates, $input->argument(2)->unwrapOr('')),
            'use' => $this->useTemplate($workspace, $templates, $input->argument(2)->unwrapOr('')),
            'list' => $this->listTemplates($templates),
            default => $this->console->refuse(
                'No `template ' . $input->argument(1)->unwrapOr('') . '`.',
                '  It has: list, show, use.',
            ),
        };
    }

    private function listTemplates(Templates $templates): int
    {
        $lines = [];

        foreach ($templates->all() as $name) {
            $lines[] = sprintf('  %-22s %s', $name, $templates->about($name));
        }

        if ($lines === []) {
            return $this->console->say('This package ships no templates.');
        }

        return $this->console->say(
            ...$lines,
            ...['', '  `commandments orchestrate template use <name>` writes one into the profile in force.'],
        );
    }

    private function showTemplate(Templates $templates, string $name): int
    {
        foreach ($templates->read($name) as $body) {
            return $this->console->say($body);
        }

        return $this->console->refuse("No template `{$name}`.", '  `commandments orchestrate template list` shows them.');
    }

    /**
     * Write a template into the profile in force. It REFUSES over a file that is already there: a
     * template is a starting point, and overwriting somebody's own words with a default would be the
     * worst thing this command could do.
     */
    private function useTemplate(Workspace $workspace, Templates $templates, string $name): int
    {
        $running = Instance::inSession($workspace)->profile();

        if ($running->isNone()) {
            return $this->console->refuse('Not orchestrating. `commandments orchestrate use <profile>` first.');
        }

        foreach ($templates->read($name) as $body) {
            foreach (Profiles::of($workspace)->named($running->unwrapOr('')) as $profile) {
                $to = $templates->homeIn($profile, $name);

                if (is_file($to)) {
                    return $this->console->refuse(
                        "`{$name}` is already written in `{$profile->name}`.",
                        '  A template never overwrites your own words — read it with `template show` and take what you want.',
                    );
                }

                if (! File::write($to, $body)) {
                    return $this->console->refuse("Could not write {$to}.");
                }

                return $this->console->say("▸ {$name} written into `{$profile->name}`.", '  ' . $to);
            }
        }

        return $this->console->refuse("No template `{$name}`.", '  `commandments orchestrate template list` shows them.');
    }

    /**
     * The orchestrator's plan — a main plan, and a sidequest nested under whatever was being done when
     * it appeared. Namespaced under `orchestrate` because `commandments plan` already means
     * plan-EXECUTION: the word is right for both, and the owner is what tells them apart.
     */
    private function plan(Workspace $workspace, Input $input): int
    {
        $plan = Plan::inSession($workspace);
        $instance = Instance::inSession($workspace);

        $this->trueUpCursor($plan, $instance);

        return match ($input->argument(1)->unwrapOr('')) {
            'open' => $this->openPlan($plan, $this->rest($input, from: 2)),
            'add' => $this->addLevel($plan, $instance, $input->argument(2)->unwrapOr(''), $this->rest($input, from: 3)),
            'up' => $this->closeLevel($plan, $instance, $this->rest($input, from: 2)),
            'go' => $this->goTo($plan, $instance, $input->argument(2)->unwrapOr('')),
            'where' => $this->whereInPlan($plan, $instance),
            'stale' => $this->stalePlan($plan, (int) $input->option('for')->unwrapOr('60')),
            '' => $this->planTree($plan, $instance),
            default => $this->noSuchPlanVerb($input->argument(1)->unwrapOr('')),
        };
    }

    /**
     * A cursor can name a level that is not there — one closed from elsewhere, or one whose creation
     * failed. Standing on a ghost would nest the next sidequest under nothing, so the cursor walks back
     * to the deepest level that actually exists rather than being believed.
     */
    private function trueUpCursor(Plan $plan, Instance $instance): void
    {
        $at = $instance->at();

        while ($at !== [] && ! $plan->has($at)) {
            $at = array_slice($at, 0, -1);
        }

        if ($at !== $instance->at()) {
            $instance->standAt($at);
        }
    }

    private function openPlan(Plan $plan, string $title): int
    {
        if ($title === '') {
            return $this->console->refuse('Say what the plan is: `commandments orchestrate plan open "<title>"`.');
        }

        if (! $plan->open($title)) {
            return $this->console->refuse('There is already a plan here — add a sidequest under it instead.');
        }

        return $this->console->say("▸ Plan opened: {$title}");
    }

    /**
     * A sidequest under WHEREVER the cursor stands, never a path the caller had to spell — that is what
     * makes it cheap enough to say mid-flight, which is the only way a detour gets recorded at all.
     */
    private function addLevel(Plan $plan, Instance $instance, string $name, string $why): int
    {
        if ($name === '' || ! $plan->exists()) {
            return $this->console->refuse($plan->exists()
                ? 'Name it: `commandments orchestrate plan add <name> "<why>"`.'
                : 'No plan yet: `commandments orchestrate plan open "<title>"` first.');
        }

        $at = $instance->at();

        // `add` stands you IN the new level, so a second `add` of the same name nests under the first —
        // and `add` is the command run while NOT looking at the tree. Nesting a level inside one of its
        // own name is never what somebody meant.
        if (in_array($name, $at, true)) {
            return $this->console->refuse(
                "You are already standing in `{$name}`.",
                '  ' . $this->breadcrumb($plan, $at),
                "  `commandments orchestrate plan go ..` first, or name the sidequest something else.",
            );
        }

        if (! $plan->add($at, $name, $why)) {
            return $this->console->refuse("`{$name}` is already a sidequest here.");
        }

        $instance->standAt([...$at, $name]);

        return $this->console->say("▸ {$name} — a sidequest of " . $plan->title($at), '  ' . $this->breadcrumb($plan, [...$at, $name]));
    }

    /**
     * Close this level and surface one. The REASON goes up into the parent, because a conclusion can be
     * re-derived where a reason is what lets a later reader see whether the premise still holds.
     */
    private function closeLevel(Plan $plan, Instance $instance, string $reason): int
    {
        $at = $instance->at();

        if ($at === []) {
            return $this->console->refuse('Standing at the plan itself — there is nothing to surface to.');
        }

        if ($reason === '') {
            return $this->console->refuse('Say what came of it: `commandments orchestrate plan up "<the reason>"` — it is what goes up.');
        }

        if (! $plan->close($at, $reason)) {
            return $this->console->refuse('Nothing to close here — the level the cursor names is not there.');
        }

        $up = array_slice($at, 0, -1);
        $instance->standAt($up);

        return $this->console->say('✓ ' . $plan->title($at === [] ? [] : $at) . ' closed.', '  Now at: ' . $this->breadcrumb($plan, $up));
    }

    /**
     * A verb this version does not have. It used to fall through to the tree, which printed a plausible
     * screen and answered 0 — so a caller on an older binary saw its command apparently succeed and do
     * nothing, and only a NEIGHBOURING command's output revealed the truth. An unknown verb is the one
     * thing a version-skewed caller can be told directly, so it says so and refuses.
     */
    private function noSuchPlanVerb(string $verb): int
    {
        return $this->console->refuse(
            "No `plan {$verb}` in this version.",
            '  It has: open, add, up, go, where, stale.',
            '  If you expected one of these, the binary you ran may be older than you think —',
            '  a lane keeps its own `vendor/`, so check the one you actually invoked.',
        );
    }

    /**
     * Stand somewhere that already exists, creating and closing NOTHING. Without it the only way to move
     * is `up`, which closes — so looking elsewhere destroyed where you were, and two branches could never
     * be open at once. An orchestrator with three workers is distracted in PARALLEL, and the tree is the
     * thing that should hold that.
     *
     * `go` alone returns to the plan, `..` surfaces one level without closing it, and anything else is a
     * path: a child of where you stand, or a `/`-separated path from the plan itself.
     */
    private function goTo(Plan $plan, Instance $instance, string $where): int
    {
        $at = $instance->at();

        $to = match (true) {
            $where === '' => [],
            $where === '..' => array_slice($at, 0, -1),
            $plan->has([...$at, $where]) => [...$at, $where],
            default => explode('/', trim($where, '/')),
        };

        if (! $plan->has($to)) {
            return $this->console->refuse("No level at `{$where}`.", '  `commandments orchestrate plan` shows what is open.');
        }

        $instance->standAt($to);

        return $this->console->say('▸ ' . $this->breadcrumb($plan, $to));
    }

    private function whereInPlan(Plan $plan, Instance $instance): int
    {
        if (! $plan->exists()) {
            return $this->console->say('No plan yet.');
        }

        $at = $instance->at();
        $lines = [];

        for ($depth = 0; $depth <= count($at); $depth++) {
            $step = array_slice($at, 0, $depth);
            $why = $plan->why($step);

            $lines[] = str_repeat('  ', $depth) . ($depth === 0 ? '' : '› ') . $plan->title($step)
                . ($why === '' ? '' : ' — ' . $why);
        }

        return $this->console->say(...$lines);
    }

    /**
     * The whole shape, with depth — what a reader wants once, after a compaction, before anything else.
     */
    private function planTree(Plan $plan, Instance $instance): int
    {
        if (! $plan->exists()) {
            return $this->console->say('No plan yet. `commandments orchestrate plan open "<title>"` starts one.');
        }

        $at = $instance->at();

        foreach ($plan->levels() as $level) {
            $here = $level === $at ? ' ← you are here' : '';

            $this->console->say(str_repeat('  ', count($level)) . ($level === [] ? '' : '└ ') . $plan->title($level) . $here);
        }

        return 0;
    }

    /**
     * A live branch nobody has touched for $minutes — the plan-shaped twin of naming the work waiting on
     * you, and the line that was missing when a plan sat open all evening unmentioned.
     */
    private function stalePlan(Plan $plan, int $minutes): int
    {
        $cutoff = time() - ($minutes * 60);
        $stale = [];

        foreach ($plan->levels() as $level) {
            foreach ($plan->touched($level) as $at) {
                if ($at < $cutoff) {
                    $stale[] = '  ' . $this->breadcrumb($plan, $level) . ' — ' . intdiv(time() - $at, 60) . 'm';
                }
            }
        }

        return $stale === []
            ? $this->console->say("Nothing untouched for {$minutes}m.")
            : $this->console->say("Untouched for {$minutes}m or more:", ...$stale);
    }

    /**
     * @param  list<string>  $path
     */
    private function breadcrumb(Plan $plan, array $path): string
    {
        $trail = [$plan->title([])];

        for ($depth = 1; $depth <= count($path); $depth++) {
            $trail[] = $plan->title(array_slice($path, 0, $depth));
        }

        return implode(' › ', $trail);
    }

    /**
     * Write a profile's documents, each with what it is for. They are scaffolded rather than generated,
     * because every one of them is a judgement somebody has to make — the tool can say what question each
     * answers and nothing more.
     */
    private function scaffold(Workspace $workspace, string $name): int
    {
        if ($name === '') {
            return $this->console->say('Name it: `commandments orchestrate new <name>`.');
        }

        $profiles = Profiles::of($workspace);
        $dir = $profiles->folder() . '/' . $name;

        if (is_dir($dir)) {
            return $this->console->say("`{$name}` already exists at {$dir}.");
        }

        $profile = new Profile($name, $dir);

        @mkdir($profile->roleFolder(), 0777, true);

        foreach (Profile::DOCUMENTS as $document => $about) {
            File::write($profile->pathTo($document), "# {$document}

<!-- {$about} -->
");
        }

        File::write($profile->pathTo(Profile::SETUP), $this->setupStub());

        foreach (Profile::ROLES as $role => $is) {
            File::write($profile->pathToRole($role), $this->roleStub($role, $is));
        }

        // Every shipped reminder, written in. The wording is the part a project wants to change, and a
        // folder that starts empty is one nobody discovers — so the words are there to edit from the
        // first day, and deleting one is how it gets silenced.
        foreach (Templates::shipped()->all() as $template) {
            if (str_starts_with($template, 'reminders/')) {
                foreach (Templates::shipped()->read($template) as $body) {
                    File::write(Templates::shipped()->homeIn($profile, $template), $body);
                }
            }
        }

        $written = array_map(
            static fn (string $document, string $about): string => "    {$document}.md — {$about}",
            array_keys(Profile::DOCUMENTS),
            Profile::DOCUMENTS,
        );

        $lines = [
            "▸ Wrote `{$name}` to {$dir}",
            '',
            '  Every file is yours to write — the tool asks the question, you answer it:',
            ...$written,
            '    roles/<role>.md — who a role is, its brief, what it may never do, what it has caught',
            '',
            "  `commandments orchestrate use {$name}` puts it in force for this session.",
        ];

        return $this->console->say(...$lines);
    }

    /**
     * A starting `lane.sh`, carrying the two facts that have cost real time: a worktree checks out tracked
     * files and nothing else, and a vendor directory must be COPIED rather than linked. The steps
     * themselves are the project's own — what makes a worktree usable differs per project, and the
     * package writing them would be inventing constants from one build.
     */
    private function setupStub(): string
    {
        return <<<'SH'
            #!/bin/sh
            # Stand a lane up. Run by `commandments lane open <name>` with:
            #   $1  the lane's path      $2  the lane's name
            #
            # A worktree checks out TRACKED FILES ONLY — no vendor, no node_modules, no database, no .env.
            # A lane missing them does not fail loudly: it runs its gates against nothing and reports green.
            set -e

            ROOT="$(git rev-parse --path-format=absolute --git-common-dir)/.."

            # Copy vendor, never symlink it: composer resolves its base directory from its own real path,
            # so a linked vendor silently loads and tests the MAIN checkout instead of this one.
            # cp -c uses copy-on-write where the filesystem has it, which makes this close to free.
            # cp -c -R "$ROOT/vendor" ./vendor 2>/dev/null || cp -R "$ROOT/vendor" ./vendor

            # npm install --silent
            # cp "$ROOT/.env" .env

            echo "lane $2 prepared at $1"
            SH;
    }

    /**
     * A role's document, with the one machine-read line named — the type it spawns as, which is what lets
     * a refusal survive a restart instead of dying with a per-session binding to an agent id.
     */
    private function roleStub(string $role, string $is): string
    {
        return <<<MD
            # {$role}

            type: {$role}

            {$is}.

            ## Its brief

            <!-- what it is told when dispatched -->

            ## It may never

            <!-- including what no tool can catch -->

            ## What it has caught

            <!-- its track record: not permissions, but whether to trust a verdict -->
            MD;
    }

    private function use(Workspace $workspace, string $name): int
    {
        if ($name === '') {
            return $this->console->say('Say which: `commandments orchestrate use <profile>`.');
        }

        foreach (Profiles::of($workspace)->named($name) as $profile) {
            Instance::inSession($workspace)->start($profile->name, gmdate('H:i'));

            $roles = $profile->roles();
            $named = $roles === [] ? 'no roles declared yet' : implode(', ', $roles);

            return $this->console->say("▸ Orchestrating under `{$profile->name}` — {$named}.");
        }

        return $this->console->say(
            "No profile `{$name}`.",
            '`commandments orchestrate list` shows them; `orchestrate new ' . $name . '` writes one.',
        );
    }

    private function list(Workspace $workspace): int
    {
        $profiles = Profiles::of($workspace)->all();

        if ($profiles === []) {
            return $this->console->say('No profiles yet. `commandments orchestrate new <name>` writes one.');
        }

        $running = Instance::inSession($workspace)->profile()->unwrapOr('');

        foreach ($profiles as $profile) {
            $mark = $profile->name === $running ? '▸' : ' ';
            $roles = $profile->roles();

            $this->console->say(sprintf('%s %-20s %s', $mark, $profile->name, $roles === [] ? 'no roles' : implode(', ', $roles)));
        }

        return 0;
    }

    /**
     * A profile read out — what an orchestrator loads when it starts, rather than copying a brief by hand.
     */
    private function show(Workspace $workspace, string $name): int
    {
        $instance = Instance::inSession($workspace);
        $name = $name === '' ? $instance->profile()->unwrapOr('') : $name;

        if ($name === '') {
            return $this->console->say('Not orchestrating. `commandments orchestrate use <profile>` starts.');
        }

        foreach (Profiles::of($workspace)->named($name) as $profile) {
            foreach (array_keys(Profile::DOCUMENTS) as $document) {
                foreach ($profile->document($document) as $text) {
                    $this->console->say(Text::heading($document), '', Text::reflow($text, 2), '');
                }
            }

            foreach ($profile->roles() as $role) {
                foreach ($profile->role($role) as $text) {
                    $this->console->say(Text::heading('role · ' . $role), '', Text::reflow($text, 2), '');
                }
            }

            return $this->sayWhatIsMissing($profile);
        }

        return $this->console->say("No profile `{$name}`.");
    }

    /**
     * Name the documents the scaffold has that this profile does not. A file that is absent renders as
     * NOTHING, so a profile built before a document existed had no way to learn it was one short — and
     * the only thing that caught it was a person remembering.
     *
     * It says they exist and writes nothing: the profile is the project's, and every one of these is a
     * judgement somebody has to make rather than a blank the tool can fill.
     */
    private function sayWhatIsMissing(Profile $profile): int
    {
        $missing = [];

        foreach (Profile::DOCUMENTS as $document => $answers) {
            if ($profile->document($document)->isNone()) {
                $missing[] = "  {$document} — {$answers}";
            }
        }

        if ($missing === []) {
            return 0;
        }

        return $this->console->say(
            Text::heading('not written yet'),
            '',
            ...$missing,
            ...['', '  `commandments orchestrate profile <document> "<text>"` starts one.'],
        );
    }

    /**
     * Add to a profile's prose. Append is the default because that is how these files are really used: a
     * role's record GROWS through a build, and the skill's own instruction — write it down when it
     * happens, not at the end — only holds if adding a line is one cheap command.
     */
    private function write(Workspace $workspace, string $document, string $section, string $text, bool $replace): int
    {
        $running = Instance::inSession($workspace)->profile();

        if ($running->isNone()) {
            return $this->console->refuse('Not orchestrating. `commandments orchestrate use <profile>` first.');
        }

        if (trim($document, '/') === '' || $text === '') {
            return $this->console->refuse('Say where and what: `commandments orchestrate assistant <name> <section> "<text>"`.');
        }

        foreach (Profiles::of($workspace)->named($running->unwrapOr('')) as $profile) {
            foreach ($this->unknown($profile, $document, $section) as $refusal) {
                return $this->console->refuse(...$refusal);
            }

            $entry = $section === '' ? $text : "**{$section}** — {$text}";

            if ($replace) {
                $profile->set($document, $entry);

                return $this->console->say("▸ Wrote {$document}.md in `{$profile->name}`.");
            }

            $profile->append($document, $entry, $this->stamp());

            return $this->console->say("▸ Added to {$document}.md in `{$profile->name}`.");
        }

        return $this->console->refuse('The profile in force no longer exists.');
    }

    /**
     * Why this write is refused, or nothing at all. A role and a section are both TARGETS, so each is
     * checked against what the profile actually holds — which is what keeps a typo from scaffolding a
     * sixth role, or filing an entry under a heading of its own invention.
     *
     * @return list<list<string>>
     */
    private function unknown(Profile $profile, string $document, string $section): array
    {
        $role = str_starts_with($document, 'roles/') ? substr($document, strlen('roles/')) : '';

        if ($role !== '' && ! in_array($role, $profile->roles(), true)) {
            return [["No role `{$role}` in `{$profile->name}`.", '  It has: ' . implode(', ', $profile->roles())]];
        }

        if ($role === '' && ! array_key_exists($document, Profile::DOCUMENTS)) {
            return [["No document `{$document}` in a profile.", '  It has: ' . implode(', ', array_keys(Profile::DOCUMENTS))]];
        }

        if ($section !== '' && ! in_array($section, Profile::SECTIONS, true)) {
            return [["No section `{$section}` in a role's record.", '  It has: ' . implode(', ', Profile::SECTIONS)]];
        }

        return [];
    }

    /**
     * When it happened and against what. Stamped rather than typed, because a record is worth more for
     * saying when and against which tree — and that is exactly what a person writing mid-build forgets.
     */
    private function stamp(): string
    {
        $sha = trim((string) @shell_exec('git -C ' . escapeshellarg($this->io->projectRoot()) . ' rev-parse --short HEAD 2>/dev/null'));

        return $sha === '' ? '(' . gmdate('Y-m-d') . ')' : '(' . gmdate('Y-m-d') . ', ' . $sha . ')';
    }

    /**
     * The words after argument $from, joined — a sentence the user typed unquoted.
     */
    private function rest(Input $input, int $from): string
    {
        return trim(implode(' ', array_slice($input->arguments(), $from)));
    }

    private function stop(Workspace $workspace): int
    {
        Instance::inSession($workspace)->stop();

        return $this->console->say('▸ No longer orchestrating. The profile is untouched.');
    }

    private function propose(Input $input): int
    {
        $root = $this->io->projectRoot();
        $branch = $this->io->git()->currentBranch($root);
        $declared = Config::load($root)->orchestrationSettings();
        $write = $input->hasFlag('write');

        $this->console->say(Text::heading('orchestrate'), '');

        foreach ($declared->branch() as $already) {
            $this->already($already, $declared->writer()->unwrapOr(''));

            return $write ? $this->refuseToOverwrite() : 0;
        }

        return $write ? $this->writeDeclaration($root, $branch) : $this->proposal($root, $branch);
    }

    private function already(string $branch, string $writer): void
    {
        $said = $writer === '' ? 'no writer declared, so no merge is refused' : "written by `{$writer}`";

        $this->console->say(
            "  Already declared: `{$branch}`, {$said}.",
            '',
            '  `commandments build roles` shows who currently holds a role.',
        );
    }

    /**
     * The block printed to paste. A branch is the one value that cannot be guessed, so outside a checkout
     * it stands in the printed block as the words saying what to put there — which `--write` will not do,
     * since a placeholder in a config reads as a rule that is on.
     */
    private function proposal(string $root, string $branch): int
    {
        $this->console->say(
            '  Paste this into `.commandments/config.php`, or re-run with `--write` to have it spliced in:',
            '',
            self::declaration($branch === '' ? '<your shared branch>' : $branch),
            '',
        );

        return $this->cannot($root);
    }

    /**
     * `--write` — the same proposal spliced into `.commandments/config.php` through the AST, the way
     * `layers --write` does it, because a block a tool can write and asks a person to paste is a cost
     * moved onto whoever adopts it.
     */
    private function writeDeclaration(string $root, string $branch): int
    {
        if ($branch === '') {
            return $this->console->refuse(
                '  Nothing written — there is no branch here to declare (a detached HEAD, or no checkout at all),',
                '  and the branch the work converges on is the one thing the block cannot be written without.',
            );
        }

        $config = ConfigFile::inProject($root);
        $config->scaffoldIfMissing();

        if ($config->declaresOrchestration()) {
            return $this->refuseToOverwrite();
        }

        if (! ConfigScribe::inProject($root)->ensureOrchestration(self::declaration($branch))) {
            return $this->console->refuse(
                "  Nothing written — {$config->path} declares neither `paths()` nor `disable()`, so there is no",
                '  statement of its own to write the block beside. Paste the block above in by hand.',
            );
        }

        $this->console->say(
            "  ✓ written to `.commandments/config.php` — branch `{$branch}`, written by `integrator`.",
            '',
            '  Read the diff before you commit it: every value in it is a judgement, and the tool only',
            '  guessed the branch it found you standing on.',
            '',
        );

        return $this->cannot($root);
    }

    /**
     * A declared block is never overwritten — the same answer `layers` gives a declared stack. Non-zero,
     * because anything chaining behind `orchestrate --write` reads a zero as a grant.
     */
    private function refuseToOverwrite(): int
    {
        return $this->console->refuse(
            '',
            '  `--write` will not overwrite it. Edit `.commandments/config.php` — what a declared block says',
            '  was decided by somebody, and nothing here can tell which of its values were deliberate.',
        );
    }

    /**
     * The block as source, at the indentation the config's own statements stand at — the ONE renderer,
     * so what is printed to paste and what `--write` splices in can never drift apart.
     */
    public static function declaration(string $branch): string
    {
        return <<<PHP
                \$config->orchestration(fn (\$o) => \$o
                    ->branch('{$branch}')
                    ->writtenBy('integrator')
                    ->workers(most: 3, prefer: 2));
            PHP;
    }

    /**
     * What will not be enforced, and why. This is the half worth printing: a rule nobody can satisfy is
     * worse than one that does not exist, because it reads as protection.
     */
    private function cannot(string $root): int
    {
        $defined = $this->agentTypes($root);

        $this->console->say(Text::heading('what this will NOT do yet'), '');

        if ($defined === []) {
            $this->console->say(
                '  • No agent definitions in `.claude/agents/`, so every agent reports the same type and',
                '    `writtenBy` cannot tell the writer from anybody else. It will say so rather than refuse.',
                '',
                '    Two ways out, and the second needs no respawn:',
                '      - give each role its own `.claude/agents/<role>.md` with a matching `name:`, and spawn it as that type',
                '      - `commandments build assign <role> --to=<agent-id>` points a role at an agent ALREADY ALIVE,',
                '        which is the only option for one whose value is the history it carries',
                '',
            );
        }

        if ($defined !== []) {
            $this->console->say('  • Agent types defined here: ' . implode(', ', $defined), '');
        }

        return $this->console->say(
            '  • It decides nothing for you. `--write` splices the block in, but every value in it is a',
            '    judgement — read the diff, since an orchestration block is how a team works.',
            '  • It does not bootstrap worktrees, allocate ports, or tune a reaper. Those were left out',
            '    deliberately: each would be a constant guessed from one project.',
        );
    }

    /**
     * The agent types this project has defined, which is what a role must BE for a rule to key on it.
     *
     * @return list<string>
     */
    private function agentTypes(string $root): array
    {
        $names = [];

        foreach (glob($root . '/.claude/agents/*.md') ?: [] as $file) {
            $names[] = basename($file, '.md');
        }

        return $names;
    }

    /**
     * Turn a feature on or off for the profile in force. It is written into the PROFILE rather than the
     * project config because a way of working carries its own switches: taking a profile takes what it
     * turns on with it, and two projects sharing one profile do not each have to remember.
     */
    private function switch(Workspace $workspace, Input $input, bool $on): int
    {
        $trigger = $input->argument(1)->unwrapOr('');
        $agent = $input->argument(2)->unwrapOr('');
        $procedure = $input->argument(3)->unwrapOr('');

        if ($trigger === '' || ($on && ($agent === '' || $procedure === ''))) {
            return HelpScreen::usage($this, $on
                ? 'Say all three: `commandments orchestrate on <trigger> <agent> <procedure>`.'
                : 'Name the trigger: `commandments orchestrate off <trigger> [<agent>] [<procedure>]`.');
        }

        foreach (Profiles::inForce($workspace) as $profile) {
            if ($on && $profile->role($agent)->isNone()) {
                return $this->console->refuse(
                    "`{$profile->name}` has no `{$agent}` role, and an agent that is not written cannot be dispatched.",
                    "  Take one: `commandments orchestrate template use roles/{$agent}`",
                );
            }

            if ($on && $profile->procedure($procedure)->isNone()) {
                return $this->console->refuse(
                    "`{$profile->name}` has no `{$procedure}` procedure — a procedure is WHAT the agent does.",
                    '  Write one at ' . $profile->pathToProcedure($procedure),
                    "  Or take a shipped one: `commandments orchestrate template use procedures/{$procedure}`",
                );
            }

            $written = $on
                ? $profile->bind($trigger, new Duty($agent, $procedure))
                : $profile->unbind($trigger, $agent, $procedure);

            if (! $written) {
                return $this->console->refuse("Could not write {$profile->settingsFile()}.");
            }

            return $this->console->say(
                $on
                    ? "▸ on {$trigger}, `{$agent}` runs `{$procedure}` for `{$profile->name}`."
                    : "▸ {$trigger} unbound for `{$profile->name}`.",
                '  ' . $profile->settingsFile(),
            );
        }

        return $this->console->refuse(
            'No profile is in force, and a switch belongs to a way of working rather than to a session.',
            '  Start one first: `commandments orchestrate use <profile>`',
        );
    }

    /**
     * What the profile in force turns on. Read from the FILE rather than from a list of what the package
     * supports, so a feature a project added shows up beside the shipped ones.
     */
    private function settings(Workspace $workspace): int
    {
        foreach (Profiles::inForce($workspace) as $profile) {
            $declared = $profile->allSettings();

            if ($declared === []) {
                return $this->console->say("`{$profile->name}` turns nothing on.", '  `commandments orchestrate on <feature>`');
            }

            $waiting = Pending::inSession($workspace)->all();
            $said = [];

            foreach (array_keys($declared) as $trigger) {
                foreach ($profile->boundTo((string) $trigger) as $binding) {
                    $said[] = sprintf('  on %-14s %-28s %s', $trigger, $binding->render(), $this->health($waiting, (string) $trigger));
                }
            }

            return $this->console->say("`{$profile->name}` runs:", ...$said);
        }

        return $this->console->refuse('No profile is in force.', '  `commandments orchestrate use <profile>`');
    }

    /**
     * Whether a binding is DOING anything, as opposed to whether it is written. A healthy-looking line
     * printed above a dead transport all evening: the binding registered, this command showed it, the
     * profile was right, and nothing ever ran. So it answers from the work actually waiting, and points
     * at the one command that settles the question either way.
     *
     * @param  list<Dispatched>  $waiting
     */
    private function health(array $waiting, string $trigger): string
    {
        foreach ($waiting as $work) {
            if ($work->moment === $trigger) {
                return "asked for an agent at {$work->at}, STILL UNDISPATCHED — your stop is held for it";
            }
        }

        foreach (Moment::named($trigger) as $moment) {
            return 'nothing waiting; raised by ' . $moment->raisedBy() . ' — `orchestrate test ' . $trigger . '` proves it now';
        }

        return 'NOTHING RAISES IT — `orchestrate moments` lists the moments that exist';
    }

    /**
     * Every moment a profile can bind to. Nobody should have to read our source to learn that
     * `worker-finished` exists, or what an agent bound to it will be handed.
     */
    private function moments(): int
    {
        $said = [];

        foreach (Moment::cases() as $moment) {
            $said[] = sprintf('  %-16s carries %s', $moment->value, $moment->carries());
            $said[] = sprintf('  %-16s raised by %s', '', $moment->raisedBy());
            $said[] = '';
        }

        return $this->console->say('Moments a profile can bind an agent to:', ...$said);
    }

    /**
     * Dispatch a moment ONCE, now, with a rehearsal subject, and print exactly what the agent was handed.
     * It is the probe promoted to a command: reading a binding proves nothing, and every layer above the
     * transport reported success while nothing ran. This is the difference between believing it fires and
     * having watched it.
     */
    private function rehearse(Workspace $workspace, Input $input): int
    {
        $named = $input->argument(1)->unwrapOr('');

        if ($named === '') {
            return HelpScreen::usage($this, 'name the moment to rehearse: `commandments orchestrate test <moment>`. `orchestrate moments` lists them.');
        }

        foreach (Moment::named($named) as $moment) {
            return $this->dispatchRehearsal($workspace, $moment);
        }

        return $this->console->refuse(
            "No moment is called `{$named}`.",
            '  `commandments orchestrate moments` — the ones that exist, and what each carries.',
        );
    }

    private function dispatchRehearsal(Workspace $workspace, Moment $moment): int
    {
        $said = new Dispatcher($workspace, $this->io->projectRoot())->fire(
            $moment->value,
            $moment->rehearsal(),
            'nowhere — this is a REHEARSAL of the `' . $moment->value . '` moment, recorded by hand to '
                . 'prove the chain works. Say what you were handed and stop; do not go looking for work '
                . 'nobody did.',
        );

        if ($said === []) {
            return $this->console->refuse(
                "Nothing is bound to `{$moment->value}`, so nothing was recorded.",
                "  `commandments orchestrate on {$moment->value} <agent> <procedure>`",
            );
        }

        $waiting = [];

        foreach (Pending::inSession($workspace)->all() as $work) {
            $waiting[] = '  ' . $work->render();
        }

        return $this->console->say(
            "Rehearsed `{$moment->value}`. What it said:",
            ...$said,
            ...['', 'Waiting to be dispatched — your next stop is held until each has been:'],
            ...$waiting,
            ...['', '  `commandments queue brief <agent>` — the whole prompt to hand over.'],
        );
    }

    /**
     * `key=value` arguments as a map — how a feature is given its settings without inventing a flag for
     * every feature that will ever exist.
     *
     * @return array<string, mixed>
     */
    private function pairs(Input $input): array
    {
        $with = [];

        foreach ($input->arguments() as $at => $argument) {
            if ($at < 2 || ! str_contains($argument, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $argument, 2);
            $with[$key] = $value;
        }

        return $with;
    }
}
