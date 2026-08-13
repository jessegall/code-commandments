<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Repentable;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Workspace;
use ReflectionClass;

/**
 * Explains ONE rule: what it flags, WHY it is a sin, how it is fixed, a worked example, and the
 * commands that act on it. A finding names its detector and nothing else, so the answer to "what is
 * this and why do I care" lived in the source — which is a fine answer for whoever wrote the rule
 * and a poor one for everyone else.
 */
final class Info implements Command
{
    private const int WIDTH = 88;

    public function names(): array
    {
        return ['info', 'explain'];
    }

    public function help(): Help
    {
        return Help::of('Explain one sin — what it flags, why it is a sin, how to fix it, and a worked example.')
            ->form('info <sin|detector>', 'explain a rule by sin id or detector name (lenient match)')
            ->form('info --sin=NAME', 'the same, named explicitly')
            ->form('info --detector=NAME', 'the same, by detector class name')
            ->option('--sin=NAME', 'the sin to explain')
            ->option('--detector=NAME', 'the detector to explain')
            ->option('--full', 'print the skill\'s whole principle, not just the opening')
            ->note('The name is matched leniently, so `array-bag`, `ArrayBag` and `ArrayBagDetector` all '
                . 'resolve to the same rule. The worked example is read from the published skill, so it is '
                . 'the same code the skill teaches. Run `commandments judge --list` for every rule there is.')
            ->note("A rule this project wrote into .commandments/custom/ is explained beside the shipped ones "
                . 'and named as the project\'s own — those are the rules a reader is least likely to recognise.');
    }

    public function run(Input $input): int
    {
        $query = $input->option('sin')
            ->or($input->option('detector'))
            ->or($input->firstArgument());

        return $query->mapOrElse(
            fn () => HelpScreen::usage($this, 'name a sin or detector to explain'),
            fn (string $name) => $this->explain($name, $input->hasFlag('full')),
        );
    }

    private function explain(string $query, bool $full): int
    {
        $detectors = SinLookup::detectors($query);

        if ($detectors === []) {
            return $this->unknown($query);
        }

        foreach ($detectors as $detector) {
            $this->describe($detector, $full);
        }

        return 0;
    }

    private function unknown(string $query): int
    {
        fwrite(STDERR, "No rule matches \"{$query}\".\n\nSome of the rules there are:\n");

        foreach (SinLookup::suggestions() as $name) {
            fwrite(STDERR, "  {$name}\n");
        }

        fwrite(STDERR, "\nRun `commandments judge --list` for all of them.\n");

        return 2;
    }

    private function describe(Detector $detector, bool $full): void
    {
        $sin = $detector->sin();
        $skill = new ($sin->skillClass())();
        $name = new ReflectionClass($detector)->getShortName();
        $custom = Custom::owns($detector);

        $this->heading($sin->name(), $sin->slug());
        $this->paragraph($sin->description());

        if ($custom) {
            $this->paragraph("This is THIS project's own rule, from .commandments/custom/ — it is taught, fixed and corrected there.");
        }

        $this->section('Why it is a sin');
        $this->paragraph($full ? $skill->intro() . "\n\n" . $skill->principle() : $skill->intro());

        $this->section('How to fix it');
        $this->paragraph($sin->rule());

        if (($suggestion = $sin->suggestion()) !== null) {
            $this->paragraph($suggestion);
        }

        $this->example($skill, $sin->name());

        $this->section('Commands');
        $this->row('Learn', "load the skill \033[1m{$skill->id()}\033[0m");

        if ($detector instanceof Repentable) {
            $this->row('Auto-fix', "commandments repent --sin={$sin->name()}");
        }

        if ($sin->scaffolds() !== []) {
            $this->row('Scaffold', "commandments scaffold --sin={$sin->name()}");
        }

        if ($detector instanceof Exemptable && $detector->exemptions() !== []) {
            $this->row('Exempts', "commandments exemptions {$sin->name()}");
        }

        $this->row('Find it', "commandments judge --sin={$sin->name()}");
        $this->row('Turn off', "commandments disable {$sin->name()}");

        // A report files against the PACKAGE, which cannot answer for a rule it does not ship — so a
        // project's own rule points at the folder where it can actually be corrected.
        $this->row('Wrong?', $custom
            ? "fix it in .commandments/custom/ — {$name} is yours, not ours"
            : "commandments report --detector={$name} --reason=\"…\" --ref=PATH:LINE");

        echo "\n";
    }

    /**
     * The skill's worked example for this sin, quoted from the PUBLISHED document — so what `info`
     * shows and what the skill teaches can never be two different examples.
     */
    private function example(Skill $skill, string $sin): void
    {
        $body = $this->publishedSkill($skill);

        if ($body === null) {
            return;
        }

        $section = SkillSection::forSin($body, $sin);

        if ($section === '') {
            return;
        }

        $this->section('Example');

        foreach (explode("\n", $section) as $line) {
            echo "  {$line}\n";
        }
    }

    /**
     * The rendered `SKILL.md` — from the project's own library where a consumer has synced one, and
     * from the package's sources otherwise (which is how it reads inside this repo).
     */
    private function publishedSkill(Skill $skill): ?string
    {
        $candidates = [
            Workspace::at(ConsumerRoot::from(getcwd() ?: '.') ?? '.')->library() . "/{$skill->id()}/SKILL.md",
            dirname(__DIR__, 2) . "/skills/commandments/{$skill->slug}/SKILL.md",
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return null;
    }

    private function heading(string $name, string $slug): void
    {
        echo "\n\033[1m{$name}\033[0m  \033[2m{$slug}\033[0m\n";
    }

    private function section(string $title): void
    {
        echo "\n\033[33m{$title}\033[0m\n\n";
    }

    private function paragraph(string $text): void
    {
        echo '  ' . implode("\n  ", explode("\n", wordwrap(trim($text), self::WIDTH))) . "\n";
    }

    private function row(string $label, string $value): void
    {
        echo "  \033[36m" . str_pad($label, 10) . "\033[0m {$value}\n";
    }
}
