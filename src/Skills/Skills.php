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
     * Every skill, both engines — the backend catalog then the frontend one, the same
     * `[...backend, ...frontend]` shape the rest of the system splits by.
     *
     * @return list<Skill>
     */
    public static function all(): array
    {
        return [
            ...self::backend(),
            ...self::frontend(),
        ];
    }

    /**
     * The skills that teach the PHP/Laravel disciplines (`skills/commandments/backend/`).
     *
     * @return list<Skill>
     */
    public static function backend(): array
    {
        return [
            new Skill('backend/fix-at-the-source', "the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.", Tier::Mandatory),
            new Skill('backend/guard-clauses-and-flow', 'validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.', Tier::Mandatory),
            new Skill('backend/value-objects', 'give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)', Tier::Mandatory),
            new Skill('backend/spatie-data', 'how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.', Tier::Mandatory),
            new Skill('backend/laravel-idioms', 'typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.', Tier::Mandatory),
            new Skill('backend/documentation', 'concise, present-tense docs; rare inline comments; never narrate the past.', Tier::Mandatory),

            new Skill('backend/absence', 'modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).', Tier::KeepInMind),
            new Skill('backend/exceptions', 'throwing or catching: named `::for()` factory exceptions, never swallow a failure.', Tier::KeepInMind),
            new Skill('backend/enums-with-behaviour', 'a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).', Tier::KeepInMind),
            new Skill('backend/role-vocabulary', 'a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.', Tier::KeepInMind),
            new Skill('backend/tell-dont-ask', "behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`\$node->edges()`, not `EdgeDetector::detect(\$node)`). A Strategy over flat scalar fields is the exception.", Tier::KeepInMind),
            new Skill('backend/type-honesty', "a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.", Tier::KeepInMind),
            new Skill('backend/pass-the-object', "demand the resolved type you need, not an id plus its container: a method that takes `(Workflow \$workflow, string \$nodeId)` then unpacks `\$workflow->graph->nodeById(\$nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).", Tier::KeepInMind),
            new Skill('backend/concurrent-state', 'state shared across requests/workers (`::for($id): Concurrent<self>`).', Tier::KeepInMind),
        ];
    }

    /**
     * The skills that teach the Vue disciplines (`skills/commandments/frontend/`).
     *
     * @return list<Skill>
     */
    public static function frontend(): array
    {
        return [
            new Skill('frontend/vue-components', 'extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.', Tier::KeepInMind),
            new Skill('frontend/vue-control-flow', 'dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.', Tier::KeepInMind),
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
