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
