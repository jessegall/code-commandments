<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

use JesseGall\CodeCommandments\Skills\Catalog;
use JesseGall\CodeCommandments\Skills\Skill;

/**
 * Runs every skill's trigger set past an {@see Oracle} and scores what came back. The scoring knows
 * nothing about models or processes — hand it a fake oracle and the whole measurement is a unit test.
 */
final readonly class TriggerRun
{
    /**
     * @param  int  $samples  how many times each query is asked, since a model's answer varies
     */
    public function __construct(
        private Oracle $oracle,
        private string $packageRoot,
        private int $samples = 3,
    ) {}

    /**
     * Score $skills against their own sets. Every query is judged against EVERY measured skill, not
     * just its owner: a prompt about modelling a missing value in TypeScript is a positive for one
     * skill and a negative for the two PHP ones that read almost the same, and that is exactly the
     * confusion a per-skill run would never see.
     *
     * @param  list<Skill>  $skills
     */
    public function score(array $skills): Scorecard
    {
        $sets = [];
        $metadata = [];

        foreach (Catalog::all() as $skill) {
            $metadata[$skill->id()] = $skill->trigger();
        }

        foreach ($skills as $skill) {
            $set = TriggerSet::of($skill, $this->packageRoot);

            if ($set !== null) {
                $sets[$skill->id()] = $set;
            }
        }

        $scorecard = new Scorecard;

        foreach ($sets as $set) {
            foreach ($set->queries() as $query) {
                $consulted = $this->consensus($query->query, $metadata);

                foreach (array_keys($sets) as $measured) {
                    $scorecard->record($query, $measured, in_array($measured, $consulted, true));
                }
            }
        }

        return $scorecard;
    }

    /**
     * The skills a MAJORITY of samples reached for. One sample of a sampled process is an anecdote;
     * the majority is the behaviour a real user would meet most of the time.
     *
     * @param  array<string, string>  $metadata
     * @return list<string>
     */
    private function consensus(string $query, array $metadata): array
    {
        $tally = [];

        for ($i = 0; $i < $this->samples; $i++) {
            foreach ($this->oracle->consulted($query, $metadata) as $id) {
                $tally[$id] = ($tally[$id] ?? 0) + 1;
            }
        }

        $majority = intdiv($this->samples, 2) + 1;

        return array_values(array_keys(array_filter($tally, static fn (int $seen) => $seen >= $majority)));
    }
}
