<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

use JesseGall\CodeCommandments\Skills\Skill;

/**
 * One skill's trigger eval set — the prompts that SHOULD pull it, and the near-misses that must not.
 * Read from `evals/triggers.json` beside the skill's source, because the queries are as much a part
 * of a rule as its fixtures: they are what proves the description does its one job.
 */
final readonly class TriggerSet
{
    private const string FILE = 'evals/triggers.json';

    /**
     * @param  list<string>  $triggers  prompts whose work this skill is the right one to consult for
     * @param  list<string>  $nearMisses  prompts that look like this skill's subject and are not it
     */
    public function __construct(
        public string $id,
        public array $triggers,
        public array $nearMisses = [],
    ) {}

    /**
     * The set a skill ships, or null where it has not written one yet — a skill without queries is
     * an unmeasured description, not a broken one.
     */
    public static function of(Skill $skill, string $packageRoot): ?self
    {
        $path = "{$packageRoot}/skills/commandments/{$skill->slug}/" . self::FILE;

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw MalformedTriggerSet::at($path);
        }

        return new self(
            $skill->id(),
            array_values(array_filter($decoded['triggers'] ?? [], is_string(...))),
            array_values(array_filter($decoded['not'] ?? [], is_string(...))),
        );
    }

    /**
     * Every query in the set, each knowing the id it must pull — none, for a near-miss this skill
     * has to stay out of.
     *
     * @return list<TriggerQuery>
     */
    public function queries(): array
    {
        $queries = array_map(fn (string $query) => new TriggerQuery($query, $this->id), $this->triggers);

        foreach ($this->nearMisses as $query) {
            $queries[] = new TriggerQuery($query);
        }

        return $queries;
    }
}
