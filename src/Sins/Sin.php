<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

use JesseGall\CodeCommandments\Skills\Skill;

/**
 * One architectural sin — the thing a {@see \JesseGall\CodeCommandments\Detector}
 * finds, named and described. It owns the two facts that used to be a bare `skill()`
 * string on the detector: which teaching {@see Skill} fixes it (referenced by CLASS,
 * not a slug string — so it's refactor-safe and the slug lives in one place), and a
 * one-line {@see $description} of the sin itself.
 *
 * Each sin is its OWN class under `Sins/{Backend,Frontend}/`, registered there the
 * way each teaching skill is its own class under `Skills/` (see
 * {@see \JesseGall\CodeCommandments\Skills\Catalog}). A detector never declares a sin
 * inline — it *references* one ({@see Detector::sin} returns `new ArrayBag()`), and
 * the docs (the generated `SKILL.md` "when it fires" rows) are projected from the
 * registered sins, so they can't drift from the code.
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
     * Does this sin answer to the `--sin=<query>` the user typed? Lenient: both sides
     * are reduced to lowercase alphanumerics, so `array-bag`, `ArrayBag` and `arraybag`
     * all select the `array-bag` sin.
     */
    public function matches(string $query): bool
    {
        $normalise = static fn (string $value): string => strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));

        return str_contains($normalise($this->name), $normalise($query));
    }
}
