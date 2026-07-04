<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

use JesseGall\CodeCommandments\Skills\Skill;

/**
 * One architectural sin found by a {@see Detector}, owning the teaching {@see Skill}
 * (CLASS-referenced for refactor-safety) and a one-line {@see $description}. Registered
 * like {@see Skill}s; detectors *reference* them, never declare inline.
 */
abstract class Sin
{
    /** @var class-string<Skill> */
    private readonly string $skill;

    /**
     * @param  class-string<Skill>  $skill  the teaching skill that fixes this sin
     */
    public function __construct(
        public readonly string $name,
        string $skill,
        public readonly string $description,
        public readonly string $rule,
        public readonly ?string $suggestion = null,
    ) {
        $this->skill = $skill;
    }

    /**
     * The `--sin=` id (and `judge` filter key). Matched leniently — see {@see matches}.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * The teaching skill that fixes this sin.
     */
    public function skill(): Skill
    {
        return new $this->skill;
    }

    /**
     * The skill slug a finding points the agent at, e.g. `backend/value-objects`.
     */
    public function slug(): string
    {
        return $this->skill()->slug;
    }

    /**
     * The one-line "what the sin is" — the symptom the detector flags (the `## When it
     * fires` row). The bad → good code example is NOT stored here — it is sourced from
     * the fixture (the `#[Sinful]` bad code and its `#[Righteous]` twin) via
     * {@see \JesseGall\CodeCommandments\Testing\FixtureExamples}, so it's real and tested.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * The loud positive directive this sin enforces — the `## Rules` bullet and the
     * `## Checklist` item the generated skill projects. (Stated as the rule, not the
     * symptom: "Throw a NAMED exception", not "you threw a bare SPL".)
     */
    public function rule(): string
    {
        return $this->rule;
    }

    /**
     * An optional concrete hint beyond the rule — the reusable pattern/construct to
     * reach for (`a no-op invokable: ClosureFactory::noOp()`), shown under the rule.
     * Names the construct, so a future scaffold can generate exactly it.
     */
    public function suggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * The reusable helper(s) this sin's fix needs and the package can generate — the
     * construct {@see suggestion} names. Most sins return none (their fix is
     * domain-specific); a sin with a GENERIC helper overrides this. Written into the
     * consumer by the `scaffold` command.
     *
     * @return list<Scaffold>
     */
    public function scaffolds(): array
    {
        return [];
    }

    /**
     * Does this sin answer to a query by its OWN name? Lenient: both sides are reduced to lowercase
     * alphanumerics, so `array-bag`, `ArrayBag` and `arraybag` all select the `array-bag` sin. Kept to
     * the name so skill-vs-sin resolution ({@see \JesseGall\CodeCommandments\Cli\Config\Configure}) stays exact.
     */
    public function matches(string $query): bool
    {
        return str_contains(self::normalise($this->name), self::normalise($query));
    }

    /**
     * Does this sin fall in the SCOPE a `--sin=<query>` selects — its own name OR its skill SLUG? So a
     * group keyword like `--sin=page` scopes every sin under `backend/page-objects`, not only the one
     * whose name happens to contain "page". Used by `judge` filtering, where casting wide is the point.
     */
    public function scopes(string $query): bool
    {
        $needle = self::normalise($query);

        return str_contains(self::normalise($this->name), $needle)
            || str_contains(self::normalise($this->slug()), $needle);
    }

    /**
     * Reduce a value to lowercase alphanumerics — the lenient form both sides of a `--sin`/`--skill`
     * match are compared in.
     */
    public static function normalise(string $value): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));
    }
}
