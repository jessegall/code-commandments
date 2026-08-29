<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
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
            ->form('orchestrate', 'the declaration to paste, and what will NOT be enforced until something changes')
            ->note('A PROFILE is the durable half — how a team works, in `.commandments/orchestrator/'
                . 'profiles/<name>/`, committed and reviewed in a diff. An INSTANCE is the live half and '
                . 'belongs to the session, so a restart loses what was bound to a process and keeps what '
                . 'was written down. A profile names no branch, port or lane: those are one build rather '
                . 'than a way of working. ')
            ->note('The bare form prints and never writes: an orchestration block is a decision about how a team works '
                . 'and belongs in a diff somebody read. The runtime half — `commandments build` — needs none '
                . 'of this and works with nothing declared at all.');
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
            default => $this->propose($input),
        };
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

        @mkdir($dir . '/roles', 0777, true);

        foreach (Profile::DOCUMENTS as $document => $about) {
            File::write($dir . '/' . $document . '.md', "# {$document}

<!-- {$about} -->
");
        }

        File::write($dir . '/roles/integrator.md', $this->roleStub('integrator', 'the sole writer to the shared branch — it merges a committed sha, runs the gates on the branch itself, and answers for what landed'));
        File::write($dir . '/roles/auditor.md', $this->roleStub('auditor', 'read-only, on request only — reports violations most-severe first, and a ruling ignored outranks a new finding'));

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

            return 0;
        }

        return $this->console->say("No profile `{$name}`.");
    }

    private function stop(Workspace $workspace): int
    {
        Instance::inSession($workspace)->stop();

        return $this->console->say('▸ No longer orchestrating. The profile is untouched.');
    }

    private function propose(Input $input): int
    {
        $root = $this->io->projectRoot();
        $branch = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD 2>/dev/null'));
        $declared = Config::load($root)->orchestrationSettings();

        $this->console->say(Text::heading('orchestrate'), '');

        foreach ($declared->branch() as $already) {
            return $this->already($already, $declared->writer()->unwrapOr(''));
        }

        return $this->proposal($root, $branch === '' ? '<your shared branch>' : $branch);
    }

    private function already(string $branch, string $writer): int
    {
        $said = $writer === '' ? 'no writer declared, so no merge is refused' : "written by `{$writer}`";

        return $this->console->say(
            "  Already declared: `{$branch}`, {$said}.",
            '',
            '  `commandments build roles` shows who currently holds a role.',
        );
    }

    private function proposal(string $root, string $branch): int
    {
        $this->console->say(
            '  Paste this into `.commandments/config.php`:',
            '',
            "    \$config->orchestration(fn (\$o) => \$o",
            "        ->branch('{$branch}')",
            "        ->writtenBy('integrator')",
            '        ->workers(most: 3, prefer: 2));',
            '',
        );

        return $this->cannot($root);
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
            '  • It writes nothing. An orchestration block is a decision about how a team works, and',
            '    belongs in a diff somebody read.',
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
}
