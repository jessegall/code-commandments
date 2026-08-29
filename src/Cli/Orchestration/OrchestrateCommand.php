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
            ->form('orchestrate', 'the declaration to paste, and what will NOT be enforced until something changes')
            ->note('It prints and never writes: an orchestration block is a decision about how a team works '
                . 'and belongs in a diff somebody read. The runtime half — `commandments build` — needs none '
                . 'of this and works with nothing declared at all.');
    }

    public function run(Input $input): int
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
