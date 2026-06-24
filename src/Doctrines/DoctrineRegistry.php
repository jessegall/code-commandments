<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Doctrines;

use JesseGall\CodeCommandments\Prophets\Backend\NoCoalesceOnNonNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoSwallowedNotFoundProphet;
use JesseGall\CodeCommandments\Prophets\Backend\DataClassFromArrayOnlyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\DataClumpToValueObjectProphet;
use JesseGall\CodeCommandments\Prophets\Backend\EagerRegistryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\FormRequestTypedGettersProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoArrayBagProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoDirectRequestInputProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoInlineValidationProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRequestDataPassthroughProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoValidatedMethodProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OneRulePerFilterProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoAuthUserInDataClassesProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferClassifierCompositionProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceForProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalescingFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferConfigDrivenRegistryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferInterfaceOverTypeListProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeTypedAccessorProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNullObjectDefaultsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTotalOverNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeCoalesceProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryBaseBypassProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryPatternProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryPurityProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ResolverNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ResolverPatternProphet;
use JesseGall\CodeCommandments\Prophets\Backend\SetNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\SetReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;
use JesseGall\CodeCommandments\Prophets\Backend\AnchorEnumComparisonProphet;
use JesseGall\CodeCommandments\Prophets\Backend\BehaviouralEnumDispatchProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ComputedPropertyMustHookProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ConfigKeyContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ConstructorDependencyInjectionProphet;
use JesseGall\CodeCommandments\Prophets\Backend\DemeterEndpointReachProphet;
use JesseGall\CodeCommandments\Prophets\Backend\EncapsulateModelMutationProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ExplicitDataFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\FeatureEnvyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\HardcodedLiteralShouldBeConfigProphet;
use JesseGall\CodeCommandments\Prophets\Backend\MigrationModelDriftProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoArrayStringIndexingProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoCompactProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoConditionalArraySpreadProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoContainerResolutionProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoExternalDataFromProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoFacadesInServicesProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoManualHydrationProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionInUnionProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionOveruseProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawLiteralProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRedundantOrElseWrapProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRepeatedHydrationProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OutOfPurposeProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PassThroughDependencyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferAndThenProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoercionHelperProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCollectionPipelineProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferDataCollectionOfProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferDataTransformersProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferDefaultFallbackProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferDefaultOverNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEnumCaseGroupsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEnumForClosedSetFieldProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferFirstClassCallableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferInjectionOverSingletonProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNamedBranchFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeEnumProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNullCoalescingProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionChainOverGuardProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeMethodOverInlineDispatchProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferYieldOverAccumulatorProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ReadonlyDataPropertiesProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RepeatedFallbackProphet;
use JesseGall\CodeCommandments\Prophets\Backend\StringMatchMirrorsEnumProphet;
use JesseGall\CodeCommandments\Prophets\Backend\StringsThatShouldBeEnumsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\SuggestCompareSelfTraitProphet;
use JesseGall\CodeCommandments\Prophets\Backend\TaintedInputToSinkProphet;
use JesseGall\CodeCommandments\Prophets\Backend\TooManyParametersProphet;
use JesseGall\CodeCommandments\Prophets\Backend\TranslationKeyCongruenceProphet;
use JesseGall\CodeCommandments\Prophets\Backend\UnwrapOptionWithGuardProphet;
use JesseGall\CodeCommandments\Prophets\Backend\WideUnionTypeProphet;

/**
 * The catalogue of doctrines and the inverted index that locates a prophet's
 * doctrine + band. The single source of truth for cascade ordering (engine-
 * internal — consumers select prophets, never reorder). Doctrines are re-homed
 * here one at a time during the v3 rebuild; a prophet not in any doctrine is a
 * singleton (never suppresses, never suppressed).
 */
final class DoctrineRegistry
{
    /** @var array<string, array{doctrine: string, band: int}>|null */
    private static ?array $index = null;

    /**
     * @return list<Doctrine>
     */
    public static function all(): array
    {
        return [
            // TOTALITY — drive absence-handling to a TOTAL source. Coarse → fine:
            // boundary → invariant causes → source totality → dead coalesce →
            // coalesce-factory → hygiene nitpick. The T_*::coalesce nitpick only
            // ever fires on a coalesce that survived everything above it.
            new Doctrine('totality', [
                [PreferNativeTypedAccessorProphet::class],
                [ThrowOnUnhandledCaseProphet::class, RegistryReturnContractProphet::class, NoSwallowedNotFoundProphet::class],
                [PreferTotalOverNullableProphet::class, PreferOptionOverNullProphet::class, PreferEmptyOverNullProphet::class, PreferNullObjectDefaultsProphet::class, WideUnionTypeProphet::class, PreferDefaultOverNullableProphet::class, PreferDefaultFallbackProphet::class],
                [NoCoalesceOnNonNullableProphet::class, NoNullCoalesceToNullProphet::class, NoOptionToNullProphet::class, NoOptionInUnionProphet::class, NoOptionOveruseProphet::class, NoRedundantOrElseWrapProphet::class, PreferAndThenProphet::class, PreferOptionChainOverGuardProphet::class, UnwrapOptionWithGuardProphet::class, PreferNullCoalescingProphet::class],
                [PreferCoalesceFactoryProphet::class, PreferCoalescingFactoryProphet::class, PreferOptionFactoryProphet::class, RepeatedFallbackProphet::class],
                [PreferTypeCoalesceProphet::class, PreferCoalesceForProphet::class, PreferCoercionHelperProphet::class],
            ]),

            // ROLES — a class that PLAYS a structural role (Registry / Set / Resolver)
            // should declare it, then honour its contract. Coarse → fine: extract the
            // shared base → name the role honestly → honour the return/purity contract
            // → the finer refinements (eager, config-driven). Shape detection is shared
            // with the engine via RegistryShape/SetShape (the corpus-grounded rewrite).
            new Doctrine('roles', [
                [RegistryPatternProphet::class],
                [RegistryNamingHonestyProphet::class, SetNamingHonestyProphet::class, ResolverNamingHonestyProphet::class, ResolverPatternProphet::class],
                [RegistryReturnContractProphet::class, SetReturnContractProphet::class, RegistryPurityProphet::class, RegistryBaseBypassProphet::class],
                [EagerRegistryProphet::class, PreferConfigDrivenRegistryProphet::class, OutOfPurposeProphet::class, PreferNamedBranchFactoryProphet::class],
            ]),

            // CLASSIFICATION — decide "which kind is this?" by the TYPE, not a hardcoded
            // name list. Coarse → fine: replace the type-name list with a marker
            // interface / Classifier → then compose classifiers rather than re-listing.
            new Doctrine('classification', [
                [PreferInterfaceOverTypeListProphet::class],
                [PreferClassifierCompositionProphet::class],
            ]),

            // VALUE OBJECTS — model structured data as a typed value object, not loose
            // primitives or an untyped array. Coarse → fine: INTRODUCE the type (a data
            // clump that travels together, or an `array<string,mixed>` bag, becomes a
            // value object) → then the resulting data class honours its contract
            // (array-constructible) and stays pure (no auth user / framework state
            // smuggled in). Introduce the value object first; its hygiene rules only
            // matter once it exists.
            new Doctrine('value-objects', [
                [DataClumpToValueObjectProphet::class, NoArrayBagProphet::class, TooManyParametersProphet::class],
                [DataClassFromArrayOnlyProphet::class, NoAuthUserInDataClassesProphet::class, NoArrayStringIndexingProphet::class],
                [NoRawLiteralProphet::class],
            ]),

            // BOUNDARY — data crossing into the app should be VALIDATED and TYPED at
            // the edge, never threaded raw. Coarse → fine: stop taking raw/untyped
            // request input and validate it at the boundary → then read the validated
            // input through typed getters (not `->validated()`, not a raw passthrough).
            // (The independent HTTP conventions — NoJsonResponse / ControllerPrivate-
            // Methods / KebabCaseRoutes — stay singletons; they don't chain with input
            // typing. PreferNativeTypedAccessor lives in `totality` as its boundary
            // head, where it cascades the boundary → coalesce story.)
            new Doctrine('boundary', [
                [TaintedInputToSinkProphet::class, NoRawRequestProphet::class, NoDirectRequestInputProphet::class, NoInlineValidationProphet::class],
                [FormRequestTypedGettersProphet::class, NoValidatedMethodProphet::class, NoRequestDataPassthroughProphet::class],
            ]),

            // ENUM-ADOPTION — a closed set of values must be modelled as a native enum,
            // then USED as the enum. Coarse → fine by WHERE the set is still stringly
            // expressed: a hand-rolled enum class → a native enum; a string FIELD over a
            // closed set → introduce an enum; string LITERALS at use sites → enum cases;
            // a match whose strings EXACTLY mirror an existing enum → dispatch on it.
            // Retype the type/field and the downstream literals/matches become
            // enum-valued automatically.
            new Doctrine('enum-adoption', [
                [PreferNativeEnumProphet::class],
                [PreferEnumForClosedSetFieldProphet::class],
                [StringsThatShouldBeEnumsProphet::class],
                [StringMatchMirrorsEnumProphet::class],
            ]),

            // ENUM-DISPATCH-LOCALITY — behaviour/knowledge keyed off an enum's cases
            // belongs ON the type, not re-inlined at call sites. Coarse → fine by how
            // heavy the inlined dispatch is: a WIDE behavioural match → strategy objects
            // + a registration map; a per-case match mapping cases to VALUES → a method
            // on the enum; a named SUBSET of cases inlined as an array → a named accessor.
            new Doctrine('enum-dispatch-locality', [
                [BehaviouralEnumDispatchProphet::class],
                [PreferTypeMethodOverInlineDispatchProphet::class],
                [PreferEnumCaseGroupsProphet::class],
            ]),

            // ENUM-COMPARISON — enum equality should be named and null-safe via the
            // CompareSelf helper family, in its best-anchored shape. Coarse → fine: raw
            // === / !== comparisons → the CompareSelf helper; then an existing static
            // *Any call on a provably non-null subject → anchor on the instance.
            new Doctrine('enum-comparison', [
                [SuggestCompareSelfTraitProphet::class],
                [AnchorEnumComparisonProphet::class],
            ]),

            // DATA-HYDRATION — building typed objects from raw arrays should run through
            // a typed factory, once, not be hand-assembled at call sites. Coarse → fine:
            // stop manual array→object hydration and external `from()` reaching in →
            // give the data class an explicit factory → don't repeat the hydration →
            // collection/transformer hygiene → readonly properties.
            new Doctrine('data-hydration', [
                [NoManualHydrationProphet::class],
                [NoExternalDataFromProphet::class],
                [ExplicitDataFactoryProphet::class],
                [NoRepeatedHydrationProphet::class],
                [PreferDataCollectionOfProphet::class],
                [PreferDataTransformersProphet::class],
                [ReadonlyDataPropertiesProphet::class],
            ]),

            // EXPLICIT-INJECTION — dependencies enter through the constructor, not by
            // reaching into the container / a facade / a singleton. Coarse → fine:
            // a singleton accessor → injection; container/facade resolution inside a
            // service → constructor inject; then declare it as a constructor dependency;
            // then don't pass a dependency straight through without using it.
            new Doctrine('explicit-injection', [
                [PreferInjectionOverSingletonProphet::class],
                [NoContainerResolutionProphet::class, NoFacadesInServicesProphet::class],
                [ConstructorDependencyInjectionProphet::class],
                [PassThroughDependencyProphet::class],
            ]),

            // BEHAVIOUR-ON-OWNER (Tell-Don't-Ask) — behaviour belongs with the data it
            // operates on. Coarse → fine: a method enviously working over another
            // object's fields → move it onto the owner; mutating a model's state from
            // outside → encapsulate the mutation; a long getter-chain reaching through
            // objects → tell the first; a computed property assembled inline → a hook.
            new Doctrine('behaviour-on-owner', [
                [FeatureEnvyProphet::class],
                [EncapsulateModelMutationProphet::class],
                [DemeterEndpointReachProphet::class],
                [ComputedPropertyMustHookProphet::class],
            ]),

            // IDIOMATIC-ITERATION — express iteration with the language/collection
            // idiom, not hand-rolled accumulation. Coarse → fine: an accumulator loop →
            // a generator/yield; a manual loop building a result → a collection pipeline;
            // conditional array spread → a declarative build; then tidy the closures in
            // the chain — a forwarding closure → a first-class callable, a compound
            // filter predicate → one rule per filter; compact() → an explicit array.
            new Doctrine('idiomatic-iteration', [
                [PreferYieldOverAccumulatorProphet::class],
                [PreferCollectionPipelineProphet::class],
                [NoConditionalArraySpreadProphet::class],
                [PreferFirstClassCallableProphet::class, OneRulePerFilterProphet::class],
                [NoCompactProphet::class],
            ]),

            // DECLARED-SOURCE-CONGRUENCE — code must agree with the declared sources of
            // truth (schema / config / lang). Coarse → fine: a model drifting from its
            // migration → reconcile the schema; a hardcoded literal that belongs in
            // config → extract it; then config/translation keys must reference real,
            // congruent keys.
            new Doctrine('declared-source-congruence', [
                [MigrationModelDriftProphet::class],
                [HardcodedLiteralShouldBeConfigProphet::class],
                [ConfigKeyContractProphet::class, TranslationKeyCongruenceProphet::class],
            ]),
        ];
    }

    /**
     * The doctrine + band a prophet belongs to, or null when it is a singleton.
     *
     * @return array{doctrine: string, band: int}|null
     */
    public static function locate(string $prophetClass): ?array
    {
        return self::index()[$prophetClass] ?? null;
    }

    public static function doctrineOf(string $prophetClass): ?string
    {
        return self::locate($prophetClass)['doctrine'] ?? null;
    }

    public static function bandOf(string $prophetClass): ?int
    {
        return self::locate($prophetClass)['band'] ?? null;
    }

    /**
     * @return array<string, array{doctrine: string, band: int}>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::all() as $doctrine) {
            foreach ($doctrine->bands as $band => $members) {
                foreach ($members as $member) {
                    $index[$member] = ['doctrine' => $doctrine->name, 'band' => $band];
                }
            }
        }

        return self::$index = $index;
    }
}
