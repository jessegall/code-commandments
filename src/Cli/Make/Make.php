<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Make;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Support\Name;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments make <Name>` — scaffold a commandment of the project's OWN: the three classes a rule
 * is made of (a {@see Skill} that teaches it, a {@see \JesseGall\CodeCommandments\Sins\Sin} that
 * names it, a {@see \JesseGall\CodeCommandments\Detector} that finds it) into `.commandments/custom/`,
 * registered in the config through the AST — and then the REST of the process printed, because a
 * scaffolded detector is the start of the work, not the end of it.
 */
final class Make implements Command
{
    public function names(): array
    {
        return ['make'];
    }

    public function help(): Help
    {
        return Help::of('Scaffold a commandment of your own — a skill, a sin and a detector in `.commandments/custom/`, registered in your config, with the rest of the process printed for you.')
            ->form('make <Name>', 'scaffold a backend (PHP) commandment and register it')
            ->form('make <Name> --engine=frontend', 'scaffold a frontend (Vue) one instead')
            ->form('make <Name> --skill=NAME', 'point the sin at an EXISTING skill (shipped or your own) instead of writing a new one')
            ->option('--engine=backend|frontend', 'which parse engine the detector reads (default: backend)')
            ->option('--skill=NAME', 'the skill that teaches the fix — a lenient name/slug match against the existing skills, or a new slug to create one')
            ->option('--force', 'overwrite files that already exist')
            ->note('The generated classes live in `.commandments/custom/`, beside your config. That folder is not '
                . 'PSR-4 mapped and does not need to be: its files are required directly, so dropping a class in is '
                . 'what makes it loadable. It is kept OUT of the .commandments/ gitignore — your rules are source '
                . 'code, so commit them.')
            ->note('A scaffolded detector does not work yet, and is not meant to: its `find()` returns nothing until '
                . 'you write the rule. Load the `commandments-writing-detectors` skill first — it lists the engine '
                . 'predicates that already exist (hand-rolling one that does is the most common mistake), and it '
                . 'teaches the probe-then-calibrate discipline that proves a detector actually fires on what you meant.');
    }

    public function run(Input $input): int
    {
        $name = $input->firstArgument();

        if ($name === null || Name::studly($name) === '') {
            return HelpScreen::usage($this, 'name the commandment, e.g. `commandments make NullableElementReturn`.');
        }

        $engine = Engine::parse($input->option('engine'));

        if ($engine === null) {
            return HelpScreen::usage($this, "unknown --engine={$input->option('engine')} — it is `backend` or `frontend`.");
        }

        $root = getcwd();
        $blueprint = $this->blueprint($name, $engine, $input->option('skill'), $root);
        $force = $input->hasFlag('force');

        if (($clash = $this->existing($blueprint, $force)) !== []) {
            fwrite(STDERR, "Already there — pass --force to overwrite:\n  " . implode("\n  ", $clash) . "\n");

            return 2;
        }

        $this->write($blueprint);
        $registered = ConfigFile::inProject($root)->registerDetector($blueprint->detectorClass());

        $this->report($blueprint, $registered, $root);

        return 0;
    }

    /**
     * Resolve the blueprint, including WHICH skill the sin points at: a `--skill` that leniently
     * matches an existing skill (shipped or the project's own) reuses that one — a project adding a
     * rule to a discipline it already teaches should not get a second copy of the discipline. Any
     * other value becomes the slug of a new project skill; with no `--skill` at all the slug is
     * derived from the sin's own name.
     */
    private function blueprint(string $name, Engine $engine, ?string $skill, string $root): Blueprint
    {
        $dir = Workspace::custom($root);
        $existing = $skill === null ? null : $this->skillMatching($skill, $root);

        if ($existing !== null) {
            return Blueprint::of($name, $engine, $existing->slug, $existing::class, $dir);
        }

        $slug = $skill ?? Name::kebab($name);

        // A slug with no engine prefix gets this detector's, so a custom skill files itself
        // alongside the shipped ones instead of sitting loose at the top of the report.
        return Blueprint::of(
            $name,
            $engine,
            str_contains($slug, '/') ? $slug : "{$engine->value}/" . Name::kebab($slug),
            null,
            $dir,
        );
    }

    /**
     * The existing skill a `--skill=NAME` names — the project's own first, then the shipped ones,
     * matched leniently ({@see Skill::matches}), or null when it names none.
     */
    private function skillMatching(string $query, string $root): ?Skill
    {
        foreach ([...Custom::skills($root), ...Skills::all()] as $skill) {
            if ($skill->matches($query)) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * The blueprint's files that are already on disk — nothing is written when any of them is,
     * unless the author asked for the overwrite.
     *
     * @return list<string>
     */
    private function existing(Blueprint $blueprint, bool $force): array
    {
        if ($force) {
            return [];
        }

        return array_values(array_filter(array_keys($blueprint->files()), is_file(...)));
    }

    private function write(Blueprint $blueprint): void
    {
        @mkdir($blueprint->dir, 0775, true);

        if ($blueprint->skill !== null) {
            file_put_contents("{$blueprint->dir}/{$blueprint->skill}.php", Stubs::skill($blueprint));
        }

        file_put_contents("{$blueprint->dir}/{$blueprint->sin}.php", Stubs::sin($blueprint));
        file_put_contents("{$blueprint->dir}/{$blueprint->detector()}.php", Stubs::detector($blueprint));
    }

    /**
     * What was written, and — the point of the command — what to do NEXT. The steps are the
     * `commandments-writing-detectors` discipline in order, with this commandment's own names and
     * paths substituted in, so the reader never has to translate a generic instruction.
     */
    private function report(Blueprint $blueprint, bool $registered, string $root): void
    {
        $relative = static fn (string $path): string => str_replace($root . '/', '', $path);
        $probe = $blueprint->engine->probeRoot() . '/Probe' . $blueprint->sin . '.' . $blueprint->engine->probeExtension();

        $this->out("\033[32m✓ Scaffolded the `{$blueprint->id}` commandment.\033[0m\n");

        foreach ($blueprint->files() as $path => $class) {
            $this->out("  " . $relative($path) . "\033[2m  — {$class}\033[0m\n");
        }

        $this->out($registered
            ? "  \033[2m.commandments/config.php  — registered ->detector({$blueprint->detector()}::class)\033[0m\n"
            : "  \033[2m.commandments/config.php  — already registered\033[0m\n");

        $this->out("\n\033[1mNext — a scaffold is not a detector yet:\033[0m\n");

        foreach ($this->steps($blueprint, $probe) as $index => $step) {
            $this->out("  \033[1;36m" . ($index + 1) . ".\033[0m {$step}\n");
        }

        $this->out("\n\033[2mThe skill's SKILL.md is generated from the class on every sync — edit the class, never the markdown.\033[0m\n");
    }

    /**
     * The process, in order, with this commandment's names filled in.
     *
     * @return list<string>
     */
    private function steps(Blueprint $blueprint, string $probe): array
    {
        $steps = [];

        $steps[] = "\033[1mLoad the skill\033[0m — Skill tool → \033[36mcommandments-writing-detectors\033[0m. It lists the engine\n"
            . "     predicates that ALREADY exist; hand-rolling one that does is the mistake this command exists to prevent.";

        if ($blueprint->skill !== null) {
            $steps[] = "\033[1mWrite the teaching\033[0m — fill the TODOs in \033[36m{$blueprint->skill}\033[0m. It is what a finding\n"
                . "     sends the reader to, so write it before the rule: if you can't state what good looks like,\n"
                . "     the detector doesn't know what it's looking for either.";
        }

        $steps[] = "\033[1mName the sin\033[0m — fill the description/rule in \033[36m{$blueprint->sin}\033[0m. The description is the\n"
            . "     symptom, the rule is the positive directive. Both are projected into the docs.";

        $steps[] = "\033[1mWrite the rule\033[0m — the `where()` chain in \033[36m{$blueprint->detector()}\033[0m. One check per line,\n"
            . "     classified by what the AST or the resolved type IS — never by a name or a suffix.";

        $steps[] = "\033[1mProve it fires\033[0m — write a throwaway probe at \033[36m{$probe}\033[0m holding one example of\n"
            . "     EVERY form you mean to catch plus a near-miss you must NOT, then run\n"
            . "     \033[36mvendor/bin/commandments judge {$blueprint->engine->probeRoot()} --sin={$blueprint->id} --no-checklist\033[0m\n"
            . "     and confirm exactly the intended lines are flagged. Delete the probe after.";

        $steps[] = "\033[1mCalibrate on real code\033[0m — run the same judge over your actual source and READ the hits.\n"
            . "     Judge each against the skill, never against what the code happens to do: volume proves\n"
            . "     nothing, only a genuine false positive does. Tighten with a principled `reject`, never a name list.";

        $steps[] = "\033[1mPublish the skill\033[0m — \033[36mvendor/bin/commandments sync\033[0m renders\n"
            . "     \033[36m{$blueprint->skillId()}\033[0m so the agent can load what your finding points at.";

        return $steps;
    }

    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
