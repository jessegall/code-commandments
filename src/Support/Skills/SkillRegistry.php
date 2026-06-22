<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Skills;

/**
 * The catalogue of Claude Code skills the package ships — one per
 * architectural subject, the on-demand "how to do it right" layer that pairs
 * with the prophets (enforce) and the scripture (terse always-on rule). The
 * literal twin of {@see \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldRegistry}.
 *
 * Each Skill names the prophet family it teaches. That list is the single
 * source of truth for the prophet → skill pointer ({@see Skill}, surfaced via
 * `BaseCommandment::skill()`) AND for the drift check that keeps the catalogue
 * from diverging from the registered prophets (SkillRegistryTest), so a new
 * prophet family can't ship without a skill (or vice versa) unnoticed.
 *
 * v1 is BACKEND-ONLY (9 skills); the frontend/Vue track is deferred to v2.
 */
final class SkillRegistry
{
    /**
     * @return list<Skill>
     */
    public static function all(): array
    {
        return [
            new Skill(
                slug: 'option',
                introducedIn: '2.5.0',
                purpose: 'When to reach for Option<T> and the core API; the smells (getOr(null), ?? null, Option-in-union, unwrap-with-guard); Option vs null vs Null Object vs throw.',
                prophets: [
                    'NoOptionToNull',
                    'NoNullCoalesceToNull',
                    'NoOptionInUnion',
                    'NoOptionOveruse',
                    'UnwrapOptionWithGuard',
                    'PreferOptionChainOverGuard',
                    'PreferOptionOverNull',
                    'PreferOptionFactory',
                    'PreferAndThen',
                    'NoRedundantOrElseWrap',
                ],
            ),
            new Skill(
                slug: 'invariants',
                introducedIn: '2.5.0',
                purpose: 'Distinguish genuine absence (model with Option) from an invariant violation (fail loud): closed-set match/default, every-caller-de-nulls means make it total, swallowed not-found, find/has/get + ...OrFail companions.',
                prophets: [
                    'ThrowOnUnhandledCase',
                    'PreferTotalOverNullable',
                    'NoSwallowedNotFound',
                ],
            ),
            new Skill(
                slug: 'registry',
                introducedIn: '2.5.0',
                purpose: 'The registry contract (register / find->Option / has / get->throws / all); extend the scaffolded Registry base instead of overriding all() and bypassing the store; when to name it *Registry and the finder exemptions; role-vs-behaviour coherence (a *Registry/*Data/*Resolver should not host a second engine).',
                prophets: [
                    'EagerRegistry',
                    'RegistryReturnContract',
                    'RegistryNamingHonesty',
                    'RegistryPattern',
                    'RegistryBaseBypass',
                    'OutOfPurpose',
                ],
            ),
            new Skill(
                slug: 'set',
                introducedIn: '2.28.0',
                purpose: 'The set contract (add / has -> bool / all / values), an unkeyed iterate-only collection; Set vs Registry (membership + iteration vs keyed value lookup); name it *Set and extend the scaffolded Set base; a keyed get(string) on a set means you wanted a registry.',
                prophets: [
                    'SetNamingHonesty',
                    'SetReturnContract',
                ],
            ),
            new Skill(
                slug: 'null-object',
                introducedIn: '2.5.0',
                purpose: 'When a Null Object / empty instance (empty collection, NullCallable) beats an Option or a null default; Null Object vs Option decision and the no-op-behaviour patterns.',
                prophets: [
                    'PreferNullObjectDefaults',
                    'PreferEmptyOverNull',
                ],
            ),
            new Skill(
                slug: 'enums',
                introducedIn: '2.5.0',
                purpose: 'Turn closed-set strings into enums; put behaviour on the case (dispatch on the type, not an inline match at the call site); case groups; null-safe comparison via the CompareSelf trait.',
                prophets: [
                    'StringsThatShouldBeEnums',
                    'PreferEnumForClosedSetField',
                    'BehaviouralEnumDispatch',
                    'PreferEnumCaseGroups',
                    'PreferTypeMethodOverInlineDispatch',
                    'SuggestCompareSelfTrait',
                    'EnumCaseMustBeDocumented',
                ],
            ),
            new Skill(
                slug: 'named-exceptions',
                introducedIn: '2.5.0',
                purpose: 'Static-factory exception classes (Thing::notFound($id)) with the message owned by the exception; no inline message strings at throw sites; named branch factories for guard/early-return throws.',
                prophets: [
                    'PreferNamedExceptions',
                    'PreferNamedBranchFactory',
                ],
            ),
            new Skill(
                slug: 'resolvers',
                introducedIn: '2.5.0',
                purpose: 'The composable Resolver chain (Resolver::using(strategy, ...entries)) with the Predicate kernel; Transforms and Strategies; add domain methods via ResolverDecorator, not kernel passthroughs.',
                prophets: [
                    'ResolverPattern',
                    'ResolverNamingHonesty',
                ],
            ),
            new Skill(
                slug: 'coalesce-factories',
                introducedIn: '2.5.0',
                purpose: 'Hoist scattered ?? / construction fallbacks onto a ::coalesce() factory on the value object (and T_*::coalesce() / typed value construction); when to coalesce vs leave a plain default.',
                prophets: [
                    'PreferCoalesceFactory',
                    'PreferCoalesceFor',
                    'PreferTypeCoalesce',
                    'PreferCoercionHelper',
                ],
            ),
            new Skill(
                slug: 'immutable-data',
                introducedIn: '2.5.0',
                purpose: 'Readonly data properties on Spatie Data classes; construct via ::from() (FromArrayOnly), never hand-hydrate field-by-field; data transformers and typed data collections.',
                prophets: [
                    'ReadonlyDataProperties',
                    'NoManualHydration',
                    'DataClassFromArrayOnly',
                    'PreferDataTransformers',
                    'PreferDataCollectionOf',
                ],
            ),
            new Skill(
                slug: 'value-flow',
                introducedIn: '2.19.0',
                purpose: 'Reason about where a value comes from and goes ACROSS artifacts, not one node in isolation: a hardcoded set that is ALSO declared as config data (make it a config-driven registry), a config()/translation key the declaration tree does not contain, an env-backed config value strict-compared to a number, a string match that mirrors an enum, a model attribute that drifts from its migration column type, request input reaching raw SQL/exec, a secret reaching a log, values that always travel together (a data clump), a dependency only forwarded to one collaborator, a private producer whose result is always discarded.',
                prophets: [
                    'PreferConfigDrivenRegistry',
                    'ConfigKeyContract',
                    'TranslationKeyCongruence',
                    'MixedConfigValueUsedTyped',
                'HardcodedLiteralShouldBeConfig',
                    'StringMatchMirrorsEnum',
                    'MigrationModelDrift',
                    'TaintedInputToSink',
                    'SecretToLogOrResponse',
                    'DataClumpToValueObject',
                    'PassThroughDependency',
                    'DeadProducer',
                ],
            ),
            new Skill(
                slug: 'model-behaviour',
                introducedIn: '2.34.0',
                purpose: 'Put state transitions ON the record as intention-revealing behaviour methods instead of poking attributes then calling save() at the call site (the anemic-model / tell-don\'t-ask smell): a counter bumped inline, an enum status assigned then saved, several fields set together before a save — extract markShipped() / incrementSequenceNumber() so the change and its invariants live in one place.',
                prophets: [
                    'EncapsulateModelMutation',
                ],
            ),
            new Skill(
                slug: 'reporting',
                introducedIn: '2.32.0',
                purpose: 'When a finding is genuinely WRONG (false positive / wrong rule), report it instead of silently absolving or working around it: `report --prophet=NAME --at=path:line --reason="why"` files a GitHub issue AND quiets the finding until the issue is answered; `reports --check` (runs at session start) lifts it when resolved; then re-judge / `composer update`. A command-flow skill, not a prophet family.',
                workflow: true,
            ),
            new Skill(
                slug: 'handoff',
                introducedIn: '2.33.0',
                purpose: 'Generate a comprehensive HANDOFF.md so a fresh context resumes cold: run `sh .claude/hooks/handoff.sh` (auto-fills the git/gate snapshot), then complete the narrative — goal, done/remaining, next step, decisions, resume notes. A command-flow skill; not force-loaded (the command is self-evident).',
                workflow: true,
                autoload: false,
            ),
            new Skill(
                slug: 'resume-from-handoff',
                introducedIn: '2.39.0',
                purpose: 'Resume cold from a HANDOFF.md (or a *-progress plan memory): read the whole doc, re-verify the snapshot against the live repo (branch, git status, gate) before trusting it, reconstruct goal/state/next-step, then continue from the Next step — re-arming the plan loop if one was active. The consumer twin of the handoff skill.',
                workflow: true,
                autoload: false,
            ),
            new Skill(
                slug: 'profile',
                introducedIn: '2.54.0',
                purpose: 'Switch the code-commandments work profile: `commandments profile grind` (heads-down — no checks between phases, reckon + tests before push), `phased` (face-by-face), `sins-only`, or `disabled` (dormant — no hooks, agent unaware). The skill runs the switch and adopts the new contract. A command-flow skill; not force-loaded.',
                workflow: true,
                autoload: false,
            ),
        ];
    }

    /**
     * Map a prophet's short class name (e.g. `NoOptionToNullProphet` or
     * `NoOptionToNull`) to the slug of the skill that teaches it, or null when
     * no skill backs it. Drives `BaseCommandment::skill()` so a finding points
     * at its deep-dive playbook — derived from the catalogue so the two never
     * drift.
     */
    public static function slugForProphet(string $prophetShortName): ?string
    {
        $needle = self::normalizeProphet($prophetShortName);

        foreach (self::all() as $skill) {
            foreach ($skill->prophets as $prophet) {
                if (self::normalizeProphet($prophet) === $needle) {
                    return $skill->slug;
                }
            }
        }

        return null;
    }

    private static function normalizeProphet(string $name): string
    {
        $short = $name;

        if (str_contains($short, '\\')) {
            $short = substr($short, (int) strrpos($short, '\\') + 1);
        }

        if (str_ends_with($short, 'Prophet')) {
            $short = substr($short, 0, -strlen('Prophet'));
        }

        return $short;
    }
}
