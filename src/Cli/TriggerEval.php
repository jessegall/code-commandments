<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Evals\ClaudeOracle;
use JesseGall\CodeCommandments\Skills\Evals\Scorecard;
use JesseGall\CodeCommandments\Skills\Evals\TriggerRun;
use JesseGall\CodeCommandments\Skills\Evals\TriggerSet;
use JesseGall\CodeCommandments\Skills\Skill;

/**
 * Measures the ONE job a skill's description has: pulling the skill in when its subject comes up, and
 * staying out when a neighbouring skill's does. Every other part of a rule is proven by a fixture;
 * the description is the only part that has been taken on trust.
 */
final class TriggerEval implements Command
{
    private const float FLOOR = 1.0;

    public function names(): array
    {
        return ['trigger-eval'];
    }

    public function help(): Help
    {
        return Help::of('Measure whether skill descriptions pull their own skill in — and stay out of their neighbours\'.')
            ->form('trigger-eval', 'score every skill that ships a trigger set')
            ->form('trigger-eval --skill=NAME', 'score one skill (lenient name match), still judged against every other')
            ->option('--skill=NAME', 'only score this skill')
            ->option('--samples=N', 'how many times to ask each query (default 3; a majority decides)')
            ->option('--model=ID', 'the model to ask — default is whatever `claude -p` uses')
            ->note('Each skill\'s queries live in `skills/commandments/<slug>/evals/triggers.json`: `triggers` '
                . 'are prompts it must answer, `not` are near-misses it must leave alone. Every query is judged '
                . 'against EVERY measured skill, so a prompt that belongs to one is automatically a negative for '
                . 'the rest — which is how a collision between two similar descriptions shows up at all.')
            ->note('It shells out to `claude -p` once per query per sample, so it is slow and it is billed. Run it '
                . 'deliberately, never as part of `composer sins`. Exit code 1 when a description misses one of its '
                . 'own prompts or answers another skill\'s.');
    }

    public function run(Input $input): int
    {
        $root = dirname(__DIR__, 2);
        $selected = $this->select($input->option('skill')->unwrapOr(''), $root);

        if ($selected === []) {
            fwrite(STDERR, "No skill with a trigger set matched. Write one at skills/commandments/<slug>/evals/triggers.json.\n");

            return 2;
        }

        $samples = max(1, (int) $input->option('samples')->unwrapOr('3'));
        $oracle = new ClaudeOracle($input->option('model')->unwrapOr(null));

        $this->line('Asking `claude -p` ' . $samples . '× per query across ' . count($selected) . ' skills — this takes a while.');

        $scorecard = new TriggerRun($oracle, $root, $samples)->score($selected);

        $this->report($scorecard);

        return $scorecard->isClean(self::FLOOR) ? 0 : 1;
    }

    /**
     * The skills to measure — those that ship a set, narrowed by `--skill` when given.
     *
     * @return list<Skill>
     */
    private function select(string $query, string $root): array
    {
        $selected = [];

        foreach (Skills::all() as $skill) {
            if ($query !== '' && ! $skill->matches($query)) {
                continue;
            }

            if (TriggerSet::of($skill, $root) !== null) {
                $selected[] = $skill;
            }
        }

        return $selected;
    }

    private function report(Scorecard $scorecard): void
    {
        $this->line('');

        foreach ($scorecard->rows() as $skill => $row) {
            if ($row['owned'] === 0 && $row['stolen'] === 0) {
                continue;
            }

            $recall = (int) round($scorecard->recall($skill) * 100);
            $clean = $scorecard->recall($skill) >= self::FLOOR && $row['stolen'] === 0;

            $this->line(($clean ? "\033[32m✓\033[0m " : "\033[31m✗\033[0m ")
                . str_pad($skill, 44) . " pulled {$row['pulled']}/{$row['owned']} ({$recall}%)"
                . ($row['stolen'] > 0 ? "  \033[33manswered {$row['stolen']} that were not its own\033[0m" : ''));

            foreach (array_slice($row['misses'], 0, 3) as $miss) {
                $this->line("    \033[2m↳ " . substr($miss, 0, 96) . "\033[0m");
            }
        }

        $this->line('');
        $this->line($scorecard->isClean(self::FLOOR)
            ? "\033[32mEvery measured description pulled its own prompts and left the others alone.\033[0m"
            : "\033[31mA description is not doing its job — rewrite it to name WHEN to reach for the skill, and what it is NOT.\033[0m");
    }

    private function line(string $text): void
    {
        echo $text . "\n";
    }
}
