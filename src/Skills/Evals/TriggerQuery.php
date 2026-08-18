<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

/**
 * One eval prompt and the skill it must pull — `null` where the prompt is a near-miss no skill in
 * the set should answer.
 */
final readonly class TriggerQuery
{
    public function __construct(
        public string $query,
        public ?string $owner = null,
    ) {}

    /**
     * Did the model consult the skill this query belongs to? A near-miss passes when the skill that
     * shipped it stayed out — the whole point of writing one.
     *
     * @param  list<string>  $consulted  the skill ids the model said it would reach for
     */
    public function isAnsweredBy(array $consulted, string $skill): bool
    {
        return $this->owner === $skill
            ? in_array($skill, $consulted, true)
            : ! in_array($skill, $consulted, true);
    }
}
