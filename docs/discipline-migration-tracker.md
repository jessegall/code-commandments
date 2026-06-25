# Discipline migration tracker

We are migrating from many loose, single-purpose prophets to a few **compiler-like
discipline prophets** (see `docs/disciplines.md` and memory `project_discipline_migration`).
Each discipline prophet GROWS to cover its whole rule set, then the existing prophets
whose job it subsumes are **retired** (retire-not-delete).

**Standing rule:** never remove a verdict from a discipline prophet — only ADD and REFINE.

## Testing policy (TDD, every rule)

- **Every rule in `docs/disciplines.md` becomes at least one unit test** — a fire case
  AND a non-fire / FP-guard case — written **test-first (red)** before the verdict is
  implemented, in that discipline's own `tests/Unit/Prophets/Backend/<Discipline>ProphetTest.php`.
- **Broader integration suite:** each discipline gets a `tests/Fixtures/corpus/<slice>/{messy,golden}`
  slice (golden silent across the full registry, messy lights up), asserted the way
  `tests/Feature/DoctrineCorpusTest.php` does — so the disciplines are exercised
  together, not just in isolation. `assistant-patch` is BoundaryTyping's slice.
- **Sequencing (so the suite stays green for the grind):** TDD is done **per active
  discipline** — we do NOT commit hundreds of red tests for unbuilt disciplines at once
  (that would wedge the grind's green-gate). The active discipline's rule-tests go
  red→green within its grind phase; the next discipline's tests are written first when
  its phase begins. `TypeHonestyProphetTest` is the live example.

## Grind queue (active discipline: BoundaryTyping → `TypeHonestyProphet`)

The Stop hook `.claude/hooks/grind-disciplines.sh` drives the first unchecked `- [ ]`
item to completion (implement verdict + TDD tests + run on workflows & smart-farmers),
then it is checked off. Off-switch: `rm .claude/grind-disciplines-active`.

- [x] V1 FAKE-REQUIRED — empty-string coalesce (`?? ''` / `T_String::empty()` / `T_String::EMPTY`) into a required, non-nullable `string` constructor slot (sin)
- [x] V2 PHANTOM-NULLABLE — a boundary DTO (Spatie Data / FormRequest) whose every field is `?T = null`, ≥2 fields (warn)
- [x] V7 NONNULL-GUARD — a `=== null` / `!== null` / `is_null()` guard on a value whose declared type is non-nullable (the NoCoalesceOnNonNullable twin) (warn). Done: 9 tests; 0 FP on workflows + smart-farmers. (`empty()`/`assert` deliberately excluded — falsiness checks are legit on non-nullables.)
- [x] V6 BOOL-UNION — a `T|false` union (literal false, exactly 2 members, T a class) used to encode found-or-not; model presence with Option (warn). Done: 11 tests. Refined to literal `false` only (not `bool` — poly-form), exclude `Closure` (callable poly-form) + `*Response`/`Responsable` (framework render/defer contract). 0 FP on both consumers.
- [x] V3 DTO-OR-ARRAY-SEAM — a private/protected param or return typed `T|array` where T resolves to a Data/boundary class (reflection→AST→index); public methods + `Arrayable|array` + non-Data unions excluded (warn). Done: 7 tests, 0 FP on both consumers.
- [x] V2-REFINE USE-FOLLOWING — gate PHANTOM-NULLABLE on a consumer that consumes a field as a required value (deref / coalesce-to-non-null / cast / call-arg / foreach) vs merely branching on its null. Scans current file + (via callersOf/instantiationsOf) consumer files. Done: verified on workflows — ScheduleSpec (optional VO) DROPPED, RawGraphPayload (TP) KEPT (7→4 V2). Verdict refined, not removed. Full suite green (2454).
- [x] V5 REQUIRED-BUT-NULLABLE — a boundary DTO field typed `?T` that the class's own `rules()` marks unconditionally `required` (bare `required`, not `required_if`, not alongside `nullable`) or carries a `#[Required]` attribute (sin). Done: 7 tests, 0 FP on both consumers. Full suite green (2461).
- [x] V4 MIXED-SEAM — a private/protected param typed exactly `mixed`/`object` where every resolved caller (in-file `$this->m()` + cross-file via callersOf) passes the same single concrete class type. Bails (no fire) on any unresolved/scalar/differing arg — fires only on unanimous agreement (warn). Done: 7 tests, 0 FP on both consumers.
- [x] V8 DISCRIMINATED-PUNT — a boundary DTO with a `mixed` payload + a string/enum discriminator, where a consumer `match`/`switch`-es on the discriminator off a provably-C receiver AND reads the mixed payload inside it (untyped tagged-union). Scans current file + consumer files via the index (reuses V2 receiver resolution). Done: 4 tests, 0 FP on both consumers. Full suite green (2471).

When every box above is checked, BoundaryTyping's new `[GAP]` coverage is complete and
the grind hook self-clears. Re-arm for the next discipline by creating a new queue +
`touch .claude/grind-disciplines-active`.

## Retirement map — existing prophets each discipline will REPLACE

Source of truth: the coverage map in `docs/disciplines.md`. Status:
`ACTIVE` = still its own prophet; `RETIRE→<Discipline>` = fold/retire once the discipline
prophet covers its rule. None are retired yet — retirement happens per discipline once
its discipline prophet is complete and validated on the consumers.

### BoundaryTyping → `TypeHonestyProphet` (active build)
- PreferTypedBoundaryProphet — ACTIVE (anchor; fold) → RETIRE→BoundaryTyping
- WideUnionTypeProphet — RETIRE→BoundaryTyping (Option membership defers to AbsenceOption)
- NoCoalesceOnNonNullableProphet — RETIRE→BoundaryTyping
- NoNullCoalesceToNullProphet — RETIRE→BoundaryTyping
- PreferNullCoalescingProphet — RETIRE→BoundaryTyping
- PreferTypeCoalesceProphet — RETIRE→BoundaryTyping
- PreferNativeTypedAccessorProphet — RETIRE→BoundaryTyping
- PreferCoercionHelperProphet — RETIRE→BoundaryTyping
- MixedConfigValueUsedTypedProphet — RETIRE→BoundaryTyping
- PreferCoalesceFactoryProphet — RETIRE→BoundaryTyping
- PreferCoalescingFactoryProphet — RETIRE→BoundaryTyping
- PreferCoalesceForProphet — RETIRE→BoundaryTyping
- RepeatedFallbackProphet — RETIRE→BoundaryTyping (coalesce-chain owns it)
- NoConditionalArraySpreadProphet — RETIRE→BoundaryTyping
- NoArrayBagProphet — RETIRE→BoundaryTyping (root cause of NoArrayStringIndexing)
- NoArrayStringIndexingProphet — RETIRE→BoundaryTyping (symptom)

### AbsenceOption → `OptionDisciplineProphet` (extend)
- OptionDisciplineProphet (seed) · PreferTotalOverNullable · PreferDefaultOverNullable
  · PreferDefaultFallback · PreferEmptyOverNull · PreferNullObjectDefaults
  · NoOptionInUnion · NoOptionToNull — all RETIRE→AbsenceOption

### ErrorException → `ErrorExceptionProphet` (new)
- NoSwallowedNotFound (anchor) · PreferNamedExceptions — RETIRE→ErrorException

### EnumDispatch → `EnumDispatchProphet` (new)
- ThrowOnUnhandledCase · PreferEnumForClosedSetField · StringsThatShouldBeEnums
  · PreferNativeEnum · StringMatchMirrorsEnum · PreferTypeMethodOverInlineDispatch
  · BehaviouralEnumDispatch · PreferEnumCaseGroups · AnchorEnumComparison
  · SuggestCompareSelfTrait · PreferConfigDrivenRegistry — RETIRE→EnumDispatch

### RegistrySetResolver → `RegistrySetResolverProphet` (new)
- RegistryPattern · RegistryNamingHonesty · RegistryPurity · RegistryReturnContract
  · RegistryBaseBypass · EagerRegistry · SetNamingHonesty · SetReturnContract
  · ResolverPattern · ResolverNamingHonesty · PreferClassifierComposition
  · PreferNamedBranchFactory · PreferInterfaceOverTypeList — RETIRE→RegistrySetResolver

### CohesionStructure → `CohesionStructureProphet` (new)
- OutOfPurpose (anchor) · FeatureEnvy · DemeterEndpointReach · PassThroughDependency
  · DeadProducer · PreferYieldOverAccumulator · DuplicateCode · LongMethod
  · ShortClosure · TooManyParameters · ControllerPrivateMethods · DataClumpToValueObject
  — RETIRE→CohesionStructure

### DataConstruction → `DataConstructionProphet` (new)
- DataClassFromArrayOnly · ExplicitDataFactory · NoExternalDataFrom · NoManualHydration
  · NoRepeatedHydration · PreferDataCollectionOf · PreferDataTransformers
  · NoRequestDataPassthrough · NoAuthUserInDataClasses — RETIRE→DataConstruction

### CollectionIteration / ImmutabilityValueObject / ControlFlowTotality / InjectionDependency / RequestInput
- See `docs/disciplines.md` coverage map for the full per-discipline membership.

### SINGLETONS — never folded
Security (SecretToLogOrResponse, TaintedInputToSink), framework congruence
(MigrationModelDrift, ConfigKeyContract, TranslationKeyCongruence,
HardcodedLiteralShouldBeConfig, EncapsulateModelMutation, NoInlineBootLogic,
QueryModelsThroughQueryMethod, NoJsonResponse, KebabCaseRoutes), and doc/layout/style
cosmetics (EnumCaseMustBeDocumented, LongDocblock, NoInlineParamDoc, PushGenericToSource,
ConstantsAndPropertiesFirst, ComputedPropertyMustHook, NoCompact, NoRawLiteral,
PreferSprintf, PreferFirstClassCallable, PreferStaticOverInvokableConstruct,
NoRedundantDefaultArgument) — stay atomic.
