<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * The catalog of teaching skills shipped with the package — the source of truth
 * the consumer briefing (`ClaudeSection`) and the skill publisher iterate over.
 * To add or re-tier a skill, edit this list; the CLAUDE.md section regenerates.
 */
final class Skills
{
    /**
     * @return list<Skill>
     */
    public static function all(): array
    {
        return [
            new Skill('fix-at-the-source', "the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.", Tier::Mandatory),
            new Skill('guard-clauses-and-flow', 'validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.', Tier::Mandatory),
            new Skill('value-objects', 'give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)', Tier::Mandatory),
            new Skill('spatie-data', 'how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.', Tier::Mandatory),
            new Skill('laravel-idioms', 'typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.', Tier::Mandatory),
            new Skill('documentation', 'concise, present-tense docs; rare inline comments; never narrate the past.', Tier::Mandatory),

            new Skill('absence', 'modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).', Tier::KeepInMind),
            new Skill('exceptions', 'throwing or catching: named `::for()` factory exceptions, never swallow a failure.', Tier::KeepInMind),
            new Skill('enums-with-behaviour', 'a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).', Tier::KeepInMind),
            new Skill('role-vocabulary', 'a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.', Tier::KeepInMind),
            new Skill('concurrent-state', 'state shared across requests/workers (`::for($id): Concurrent<self>`).', Tier::KeepInMind),
        ];
    }

    /**
     * @return list<Skill>
     */
    public static function inTier(Tier $tier): array
    {
        return array_values(array_filter(self::all(), static fn (Skill $skill): bool => $skill->tier === $tier));
    }
}
