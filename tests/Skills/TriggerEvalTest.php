<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Evals\Oracle;
use JesseGall\CodeCommandments\Skills\Evals\Scorecard;
use JesseGall\CodeCommandments\Skills\Evals\TriggerQuery;
use JesseGall\CodeCommandments\Skills\Evals\TriggerRun;
use JesseGall\CodeCommandments\Skills\Evals\TriggerSet;
use JesseGall\CodeCommandments\Skills\Skill;
use PHPUnit\Framework\TestCase;

/**
 * The trigger harness. Every other part of a rule is proven by a fixture; the description has been
 * taken on trust, and this is what stops that. The scoring is exercised against a scripted oracle —
 * a measurement you can only run by paying a model is a measurement nobody runs.
 */
final class TriggerEvalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_a_description_that_answers_its_own_prompts_scores_clean(): void
    {
        $skills = $this->withSets();
        $perfect = $this->oracle(static fn (string $query, array $owners): array => [$owners[$query] ?? '']);

        $scorecard = new TriggerRun($perfect, $this->root, samples: 1)->score($skills);

        $this->assertTrue($scorecard->isClean(), 'an oracle that always picks the owner is the clean case');
        $this->assertSame(1.0, $scorecard->recall($skills[0]->id()));
    }

    public function test_a_skill_that_answers_a_neighbours_prompt_is_a_collision(): void
    {
        $skills = $this->withSets();
        $greedy = $skills[0]->id();

        // One skill answering everything is the failure the whole run exists to catch: a description
        // so broad it wins prompts that belong to other disciplines.
        $scorecard = new TriggerRun($this->oracle(static fn () => [$greedy]), $this->root, samples: 1)->score($skills);

        $this->assertFalse($scorecard->isClean());
        $this->assertGreaterThan(0, $scorecard->collisions($greedy));
    }

    public function test_a_silent_description_fails_rather_than_passing_quietly(): void
    {
        $skills = $this->withSets();

        $scorecard = new TriggerRun($this->oracle(static fn () => []), $this->root, samples: 1)->score($skills);

        $this->assertFalse($scorecard->isClean(), 'a skill nothing ever pulls is the worst case, not the safest');
        $this->assertSame(0.0, $scorecard->recall($skills[0]->id()));
    }

    public function test_a_majority_of_samples_decides_rather_than_one_lucky_answer(): void
    {
        $skills = $this->withSets();
        $owner = $skills[0]->id();
        $call = 0;

        // Answers correctly one time in three. A model is sampled, so one good answer is an anecdote.
        $flaky = $this->oracle(static function () use ($owner, &$call): array {
            return ++$call % 3 === 0 ? [$owner] : [];
        });

        $this->assertSame(0.0, new TriggerRun($flaky, $this->root, samples: 3)->score($skills)->recall($owner));
    }

    public function test_a_near_miss_passes_by_the_skill_staying_out_of_it(): void
    {
        $query = new TriggerQuery('a prompt that belongs to nobody');

        $this->assertTrue($query->isAnsweredBy([], 'commandments-backend-absence'));
        $this->assertFalse($query->isAnsweredBy(['commandments-backend-absence'], 'commandments-backend-absence'));
    }

    public function test_a_skill_with_no_prompts_is_reported_unmeasured_not_perfect(): void
    {
        $this->assertSame(0.0, new Scorecard()->recall('commandments-nothing-here'));
    }

    public function test_the_seeded_sets_are_readable_and_carry_near_misses(): void
    {
        $sets = array_filter(array_map(fn (Skill $skill) => TriggerSet::of($skill, $this->root), Skills::all()));

        $this->assertNotSame([], $sets, 'no skill ships a trigger set');

        foreach ($sets as $set) {
            $this->assertNotSame([], $set->triggers, "{$set->id} has no prompts that must pull it");
            $this->assertNotSame([], $set->nearMisses, "{$set->id} has no near-miss — those are the ones worth writing");
            $this->assertNotSame([], $set->queries());
        }
    }

    /**
     * The skills that ship a set — the ones a real run would measure.
     *
     * @return list<Skill>
     */
    private function withSets(): array
    {
        $skills = array_values(array_filter(
            Skills::all(),
            fn (Skill $skill): bool => TriggerSet::of($skill, $this->root) !== null,
        ));

        $this->assertNotSame([], $skills);

        return $skills;
    }

    /**
     * An oracle scripted by a closure, handed a map of query => the id that owns it so a test can
     * answer "correctly" without restating the seeded prompts.
     */
    private function oracle(callable $answer): Oracle
    {
        $owners = [];

        foreach (Skills::all() as $skill) {
            foreach (TriggerSet::of($skill, $this->root)?->triggers ?? [] as $query) {
                $owners[$query] = $skill->id();
            }
        }

        return new class($answer, $owners) implements Oracle
        {
            /**
             * @param  array<string, string>  $owners
             */
            public function __construct(private $answer, private readonly array $owners) {}

            public function consulted(string $query, array $skills): array
            {
                return array_values(array_filter(($this->answer)($query, $this->owners)));
            }
        };
    }
}
