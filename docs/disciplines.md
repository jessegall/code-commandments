<!--
Provenance: synthesized from a 6-agent expansion panel (4 group-expanders +
new-groups proposer + existing-prophet coverage mapper) over the real review of
an LLM-action decoder. See tests/Fixtures/corpus/assistant-patch for the grounding slice.

BUILD STATUS:
- BoundaryTypingDiscipline is the first discipline being built, via `TypeHonestyProphet`.
  It implements two of this section's [GAP] rules:
    * V1 FAKE-REQUIRED  -> "Never coalesce a nullable to its type's EMPTY literal
      to fill a required non-nullable slot" (sin)
    * V2 PHANTOM-NULLABLE -> "Do not declare a boundary DTO whose every field is
      ?T = null" (warn)
- Everything else here is the living spec / roadmap. Rules tagged [covers: X] re-home
  an existing prophet (retire-not-delete); [GAP] is a new detector to build.
-->

# Code Disciplines — Rule Set

## The vision: rules as a compiler, not a linter

A *discipline* is a single-concern, compiler-like prophet that owns ONE question
("how is absence modelled?", "how are values typed at the boundary?") and answers
it with **disjoint verdicts** over many rules. The seed is `OptionDisciplineProphet`,
which already emits at most one of `ADOPT / NEVER-NONE / WRAP-THEN-UNWRAP` per
method. v3 generalises that shape to every concern.

Three invariants govern the whole set:

1. **Coarse → fine.** Within a discipline, structural/root-cause rules fire and
   are presented before correctness, convention, and cosmetic ones. A symptom is
   *deferred* (never duplicated) while its root cause sits in the same region —
   wired through exactly **one** `RootCauseMap` edge, never hand-overridden on both
   prophets.
2. **Retire, don't delete.** Existing prophets are not removed; each is *re-homed*
   under a discipline (or kept `SINGLETON`). The discipline becomes the verdict
   surface; the legacy prophet's detector logic is absorbed as a rule branch.
3. **AST-grounded or unshippable (the CARDINAL RULE).** Every rule classifies from
   the AST / real type semantics (reflection over constructor / signature /
   inheritance, the `CodebaseIndex`, `RegistryShape` / `SetShape` / `RoleInference`).
   A rule whose only handle is a name / suffix / hardcoded base-list is **not
   shippable** — it is listed under *Rejected* with the reason.

**Single-owner rule.** When two disciplines could both claim a rule (a coalesce, an
enum dispatch, an absence shape), the discipline owning the **coarser verdict** owns
it; the other defers via `supersedes`/`RootCauseMap`. No rule appears in two
disciplines.

Severity tags below: **(sin)** blocks the gate, **(warn)** is advisory. `[covers: X]`
re-homes an existing prophet; `[GAP]` is a new detector to build.

---

## 1. BoundaryTypingDiscipline

*One concern: at a deserialization / internal seam, is each value typed to its real
domain, or punted as nullable / mixed / union / empty-literal for downstream code to
re-coerce?* Anchored on `PreferTypedBoundaryProphet`.

- **Type every boundary field to its domain — never leave a boundary DTO field
  `mixed`/untyped that a downstream reader re-coerces.** (boundary class via effective
  ctor reflection; field typed `mixed`; index shows ≥1 consumer applying a coercion
  token `is_*`/`match`/cast/`??`) [covers: PreferTypedBoundary] (warn) — *root cause*
- **Do not pass an `array<string,mixed>` bag around — give it a Fluent value class.**
  (param/return/property typed `array<string,mixed>` or untyped array used as a keyed
  bag) [covers: NoArrayBag] (warn) — *root cause; supersedes NoArrayStringIndexing*
- **Prefer typed DTOs over string-indexed structured arrays.** (`$arr['literal']`
  access on a structured array; `OriginTracer` walks to the DTO introduction point)
  [covers: NoArrayStringIndexing] (warn) — *symptom of NoArrayBag*
- **Do not declare a boundary DTO whose every field is `?T = null`.** (effective ctor
  resolves to a Data/FormRequest boundary AND every promoted param + declared property
  is nullable-with-null-default; ratio == 1.0, field count ≥ 2) [GAP] (warn) — *the
  all-nullable boundary; pushes validation downstream — `TypeHonestyProphet` V2*
- **Do not declare a boundary field `?T` that the same class's `rules()` marks
  `required`.** (Data/FormRequest field typed `?T`/`T|null` while the class's own
  `rules()` array or `#[Required]` attribute lists it `required`; AST read of one
  class) [GAP] (sin)
- **Never coalesce a nullable to its type's EMPTY literal to fill a required
  non-nullable slot.** (arg to a NON-nullable scalar/array param is `$x ?? <empty
  identity>` — `''`/`0`/`[]`/`::empty()` matched to the target type — with a nullable
  left) [GAP] (sin) — *manufactures a fake-valid value, drops the absence signal —
  `TypeHonestyProphet` V1*
- **Do not accept/return both `T` and its raw `array` form on an internal seam.**
  (private/protected method param or return typed `T|array` where T is a project
  Data/value class — distinct from the blessed `Arrayable|array`) [GAP] (warn) —
  *re-hydration seam; hydrate once at the boundary*
- **Do not type an internal seam `mixed`/`object` when one concrete project type
  flows through it.** (private/protected param typed exactly `mixed`/`object`; index
  shows every in-project caller passes one resolvable project type) [GAP] (warn)
- **Do not re-coerce one boundary field into different shapes per sibling
  discriminator downstream.** (boundary DTO with an enum/string discriminator + a
  `mixed`/wide payload field; ≥1 consumer `match($dto->type)`-es and coerces the SAME
  payload field differently per arm) [GAP] (warn) — *an untyped tagged-union*
- **Coalesce a nullable typed value to its type via `::coalesce()`, not its empty
  literal.** (`?? <empty literal>` where left is a known php-types `T_*` nullable)
  [covers: PreferTypeCoalesce] (warn)
- **Use the receiver's native typed accessor, not a cast around `->get($k)`.**
  (cast/coercion wrapping `->get($k)` where the class declares a typed accessor for
  `$k`, via reflection) [covers: PreferNativeTypedAccessor] (warn)
- **Hoist a repeated inline cast-with-fallback into a named coercion helper.**
  (repeated `is_x($v) ? (cast)$v : default` across sites) [covers: PreferCoercionHelper]
  (warn) — *root-caused by PreferNativeTypedAccessor*
- **Cast a mixed config/env value before a typed comparison.** (`config()`/`env()`
  result `===` a numeric literal with no intervening cast) [covers:
  MixedConfigValueUsedTyped] (warn)
- **Hoist `new ValueObject($nullableOrLoose)` ceremony into a total `::coalesce()`
  factory.** (`new T($x)` where `$x` is nullable/loose and T has/could have a total
  coalesce factory) [covers: PreferCoalesceFactory] (warn)
- **Build a wrapper via a coalescing factory, not `cond ? new T : null` + null-guards.**
  (ternary producing `new T`/null with downstream null-guards) [covers:
  PreferCoalescingFactory] (warn)
- **Use `T_Array::coalesceFor($a,$k)` instead of double-coalescing a dynamic lookup.**
  (nested `$a[$k] ?? … ?? default` on a dynamic key) [covers: PreferCoalesceFor] (warn)
- **Avoid a wide / over-broad type union — model value-or-nothing as Option,
  one-concept-many-shapes as a shared interface.** (native/docblock union, top-level
  members ≥ 2 warn / ≥ 3 sin, excluding nullables, `Arrayable|array`, render-or-redirect,
  poly-form callable/class-string) [covers: WideUnionType] (sin) — *defers Option
  membership to AbsenceOption per single-owner*
- **Do not return `T|false` / `T|bool` to encode found-or-not — model presence with
  Option.** (2-member union, one member the `false` literal type / `bool`, alongside a
  non-bool, non-scalar T) [GAP] (warn)
- **Do not `??`-coalesce a value the type proves is never null.** (`??`, incl. inside a
  numeric/string cast, whose left resolves to a non-nullable typed param /
  always-initialised non-nullable property / index-resolved non-nullable object
  property) [covers: NoCoalesceOnNonNullable] (sin)
- **Do not guard a non-nullable typed value with `=== null` / empty / `assert`.** (read
  of a NON-nullable typed property/param guarded by a null/empty check the declared
  type already excludes — the `??`-form's twin) [GAP] (warn)
- **Strip the no-op `?? null` on an always-defined left.** (`?? null` whose left is a
  call/`new`/literal/const — NOT array-access/property/bare-var where it suppresses a
  notice) [covers: NoNullCoalesceToNull] (sin) — *auto-fixable*
- **Use `??` (or `Option::unwrapOr`) instead of a self-fallback ternary.**
  (`$x !== null ? $x : $y` / `isset($a[$k]) ? $a[$k] : $d` collapsible to `$x ?? $y`)
  [covers: PreferNullCoalescing] (warn) — *cosmetic*
- **Assemble conditional array shapes with a builder, not `...(cond ? [...] : [])`.**
  (array literal containing a spread of a ternary with an empty arm) [covers:
  NoConditionalArraySpread] (warn)

*Rejected:* "boundary field typed `string` should be an enum when its value set is
closed" — the closed-set signal is real but its home is **EnumDispatchDiscipline**
(`PreferEnumForClosedSetField`); duplicating it here as a "boundary-scoped" variant
violates single-owner. The boundary discriminator concern stays as the per-arm
re-coercion rule above, which is genuinely distinct.

---

## 2. AbsenceOptionDiscipline

*One concern: when may a value be absent, and is that absence modelled correctly —
Option vs null vs Null-Object vs empty vs throw?* The seed compiler; disjoint verdicts
`ADOPT / NEVER-NONE / WRAP-THEN-UNWRAP` already shipped, plus the de-null family.

- **Model absence at the SOURCE, not at every caller.** (`OriginTracer`: a nullable
  value flows from one producer to ≥ `min_callers` distinct callers that EACH
  independently de-null/guard it; the finding points at the producer)
  [covers: PreferTotalOverNullable (private) / GAP (cross-file public)] (warn) — *root cause*
- **Adopt Option for a genuine value-or-nothing instead of a bare `?T` every caller
  de-nulls.** (explicit `return null;` beside ≥1 value return; return `?T`; index
  resolves ≥ `min_callers` sites branching on the null) [covers: OptionDiscipline (A)]
  (warn) — *root cause*
- **Make a private nullable method total (or throw at source) when every caller
  de-nulls it.** (private non-static `?T` return, ≥1 in-class site, EVERY site
  de-nulls via `?? throw` / `->unwrap()` / plain `->` deref; no `?->`, `?? $d`,
  `=== null`, `unwrapOr`) [covers: PreferTotalOverNullable] (warn)
- **Give the method a `$default` param when every caller substitutes the SAME constant
  for absence.** (private `?T`/`Option<T>` return; every in-class site does `?? <same
  literal>` or `->unwrapOr(<same literal>)`) [covers: PreferDefaultOverNullable] (warn)
- **Push a call-site presence-check-then-fallback into the callee as a default param.**
  (caller wraps a callee result in an `isset`/`??` guard; callee param has no default)
  [covers: PreferDefaultFallback] (warn)
- **Prefer a Null Object default over a nullable param normalized in the body.**
  (`T|null $x = null` whose first use is `$x ??= …` / `$x = $x ?? …` / `if($x===null)
  $x=…` to a concrete non-null) [covers: PreferNullObjectDefaults (A)] (sin)
- **Replace a symbol read via `?->` twice+ in one scope with a Null Object default.**
  (`T|null` symbol read via nullsafe ≥ 2× in one scope, no explicit null branch)
  [covers: PreferNullObjectDefaults (B)] (warn)
- **Return an empty collection instead of null when T has an empty identity.** (return /
  typed property / null-defaulted param `?T` where T resolves to `array` or a
  Countable/Traversable/Arrayable/Collection class; suppress if a caller distinguishes
  absent-from-empty) [covers: PreferEmptyOverNull] (warn)
- **Do not type a method `: Option` when every return is `Option::some(...)`.** (return
  type short-name == Option; every return is `some()`; not overriding an Option-typed
  parent/interface) [covers: OptionDiscipline (B)] (warn) — *also covers the bare
  `?T`-never-null variant*
- **Never union Option with another type or with null — Option is the whole type.**
  (union/nullable type one member of which is Option and which has ≥1 other member)
  [covers: NoOptionInUnion] (warn)
- **Do not `unwrapOr(null)` an Option back to the nullable it replaced.** (`->unwrapOr`
  on an Option receiver with a `null` argument, result consumed by a null-check)
  [covers: NoOptionToNull] (warn)
- **Consume an Option in one move — not a manual `isNone()`-then-`unwrap()` two-step.**
  (one scope: `->isNone()`/`->isSome()` test on a var AND a bare `->unwrap()`/`->expect()`
  on the SAME var in the matching branch) [GAP] (warn)
- **Do not `map()`/`andThen()` an Option then manually presence-test + unwrap.** (same
  Option subject has a non-terminal combinator, then `->isSome()` + unwrap in a branch
  instead of a terminal `->unwrapOr()`/`->match()`) [GAP] (warn)
- **Never construct an Option only to unwrap it in the same breath.** (unwrap method
  call whose receiver is `Option::some(...)`/`none()`) [covers: OptionDiscipline (D)]
  (warn) — *cosmetic*
- **Do not reimplement a total lookup as `tryFrom()`/array-lookup + `=== null` + throw.**
  (`Enum::tryFrom($x)` or map index immediately `?? throw` / `=== null` then `throw`,
  where the owner exposes a total `from()`/`get()` that throws on miss, via reflection)
  [GAP] (warn)

*Rejected:* "Do not return Option whose `none()` means an exception was thrown" — kept,
but **re-homed to ErrorExceptionDiscipline** (the `catch → Option::none()` shape is a
catch-swallow, owned there). "Do not maintain two parallel absence channels" likewise
lives in ErrorException (it straddles, and the error channel is the coarser signal).

---

## 3. ErrorExceptionDiscipline

*One concern: when something fails, is the failure surfaced, attributed, and named —
or swallowed, broadened, duplicated, or relabelled?* Anchored on
`NoSwallowedNotFound` + `PreferNamedExceptions`.

- **Never swallow a failure into an absence value (null/false/[]/`Option::none()`)
  without recovery, rethrow, or logging.** (try/catch whose catch stmts are
  exclusively sentinel assignments/returns — `null`/`false`/empty `[]` or a resolved
  `Option::none()` — no throw, no logger sink, no other side effect)
  [covers: NoSwallowedNotFound (NotFound/SPL→null/false/[]) + GAP (Option::none() &
  arbitrary caught types)] (warn) — *root cause; ABSENCE_SYMPTOMS edge*
- **Don't catch a not-found / must-exist exception only to return a sentinel.** (caught
  type resolves to NotFound/OutOfBounds/RuntimeException-ancestor; catch body ONLY
  returns/assigns `null`/`false`/`[]`) [covers: NoSwallowedNotFound] (warn)
- **Don't return Option whose `none()` means "an exception was thrown".** (try whose
  success returns `Option::some(...)`; catch's only effect is `return Option::none()`)
  [GAP] (sin) — *conflates failure with absence; ABSENCE_SYMPTOMS edge*
- **A guard that establishes an invariant must throw, not return a sentinel.** (early
  `if(cond) return null|false|[];` on a value the later body unconditionally
  dereferences) [GAP] (warn) — *generalises ThrowOnUnhandledCase beyond match-default*
- **Don't signal one outcome through two parallel error channels.** (same method, same
  predicate: a `throw` path AND a `return null`/sentinel path not disjoint by error
  kind, OR a nullable return + a by-ref `bool &$found` flag) [GAP] (warn)
- **Don't catch a broad top-type (`\Throwable`/`\Exception`/`\Error`) when the try has a
  single identifiable failure mode.** (catch includes a top type; try contains only
  calls with a narrower declared/`@throws` exception or one throw site; no re-narrowing
  inside) [GAP] (warn)
- **Don't catch a broad type then branch on it by `instanceof`.** (single broad catch
  whose top-level body is an `if/elseif` chain of `$e instanceof X`, esp. with no final
  rethrow of the unmatched case) [GAP] (warn)
- **A caught exception must be bound and used.** (catch with null var, OR a bound var
  never referenced in the catch — not rethrown, chained, logged, or `instanceof`-tested)
  [GAP] (warn)
- **When wrapping a caught exception, pass the original as `previous`/cause.** (inside a
  catch with a bound var, `throw new X(...)` whose args never reference it, where X's
  ctor declares a Throwable param via reflection) [GAP] (warn)
- **All paths handling the same failure must observe it consistently.** (multiple
  catches on the same try/operation where one logs the bound exception and a sibling
  catch of a comparable failure neither logs nor rethrows) [GAP] (warn)
- **An empty catch (or no-op/comment-only catch) is forbidden.** (catch whose stmts are
  empty or only `Nop` nodes) [GAP] (sin)
- **Don't throw or `return` from a `finally` block.** (TryCatch with a finally
  containing a `Throw`/`Return_`) [GAP] (sin) — *masks the in-flight exception*
- **A batch/loop must not silently drop failed items.** (`foreach` whose body/wrapping
  try has a catch whose only effect is `continue`/skip — no error accumulation, log, or
  rethrow) [GAP] (warn)
- **Don't catch only to coerce a failure to a boolean/empty success flag, discarding
  the cause.** (catch whose stmts are solely `return false` / `Option::none()` /
  `Result::fail()` with no args, bound exception unused) [GAP] (warn)
- **A catch that returns a default must use an intentional default, not the type's
  empty literal as "we gave up".** (catch returning the enclosing method's return-type
  empty literal — `''`/`0`/`[]` via reflection — no log/flag) [GAP] (warn) — *inverse
  of PreferEmptyOverNull*
- **Don't reimplement a total lookup as partial-lookup + `=== null` + throw.**
  (`tryFrom`/`->find`/index immediately followed by `if($x===null) throw`) [GAP] (warn)
  — *same triad as the AbsenceOption tryFrom rule; ErrorException owns the throw-form,
  AbsenceOption the coalesce-form, related via one RootCauseMap edge*
- **Throw a named domain exception, not a generic SPL/base.** (`throw new X` where X
  resolves to a built-in SPL base, not a project/vendor subclass) [covers:
  PreferNamedExceptions] (sin)
- **Don't assemble the exception message at the throw site — pass domain values to a
  factory.** (`throw` arg is a Concat/interpolation/`sprintf`/multi-word literal)
  [covers: PreferNamedExceptions] (sin)
- **Don't translate one generic exception into another generic one.** (catch binding
  `$e`, throwing `new Y` where both caught type and Y resolve to SPL bases) [GAP] (warn)
- **Don't catch a type the try body cannot throw.** (catch type E where no call in the
  try declares/`@throws` E, resolved via index/reflection; E not a broad base) [GAP]
  (warn) — *cosmetic / dead clause*
- **Don't catch-and-rethrow the same exception unchanged.** (catch whose single stmt is
  `throw $e;` on the bound var, no logging/wrapping) [GAP] (warn) — *cosmetic*
- **Don't double-handle one exception type across a caller/callee pair.** (index: method
  M propagates E inside a try catching E, while M's own body also catches E on the same
  path) [GAP] (warn)
- **Don't log-and-rethrow into a caller that also logs the same type.** (index: catch
  logs the bound exception AND rethrows; a caller also catches+logs that type) [GAP]
  (warn)
- **Don't use exceptions for ordinary control flow.** (try whose throw + catch are in
  the same method scope, the catch sets a normal result and continues, no external
  failure source in the try) [GAP] (warn)

---

## 4. CohesionStructureDiscipline

*One concern: does each unit do one job, own its own data, and avoid leaking or
duplicating it?* SRP anchor `OutOfPurpose`; bridges the Registry/Set/Resolver family.

- **A role-marked class showing a structural second-engine signal is doing a second
  job — extract it.** (`RoleInference` detects a role shape — Registry/Data/Resolver —
  plus a foreign engine cluster: reflection in a registry, assembler in a DTO, store in
  a resolver) [covers: OutOfPurpose] (warn) — *root cause*
- **A class with too many instance fields/deps is doing too many jobs.** (count
  ctor-promoted object properties + declared non-const instance properties; fire above
  ~8; exempt array-constructible Data classes via reflection, and enums) [GAP] (warn)
- **Keep a method's cyclomatic complexity bounded.** (count decision points — `if`,
  `elseif`, each match arm, `case`, `catch`, loops, `&&`/`||`/`??` in conditions,
  ternary; fire above ~10) [GAP] (warn) — *true SRP signal, orthogonal to LongMethod*
- **A class that is nothing but pass-through methods to one held collaborator is a
  redundant wrapper.** (one object property; every public method — except `__construct`
  — is a middleman delegating to it; no other state, not a sole interface impl) [GAP]
  (warn)
- **A dependency only forwarded to one collaborator, never used itself, should be
  injected there.** (ctor-injected dep used solely as an arg forwarded to one other
  dependency) [covers: PassThroughDependency] (warn)
- **A method that only forwards its own args unchanged to one collaborator is a
  middleman — inline it.** (method body a single return of a call whose args are
  exactly the method's params in order, no transformation, receiver a `$this->prop` or
  param) [GAP] (warn) — *method-level, distinct from PassThroughDependency*
- **Don't `new` a project service with its own dependencies inside a non-factory
  method.** (`new X(...)` in a non-ctor/non-factory body where X's ctor takes object/
  service params, via index/reflection) [GAP] (warn) — *distinct from
  NoContainerResolution / ConstructorDependencyInjection*
- **Don't mix static helpers with constructor-injected instance state.** (non-empty
  `__construct` with object properties AND ≥1 public static method that is not a
  factory — does not return `self`/`static`) [GAP] (warn)
- **Behaviour that operates only on one foreign type's data belongs on that type, not
  as a free/static helper taking it as the sole argument.** (function/static method with
  exactly one project-owned-type param whose entire body's member accesses root at that
  param; owner class extendable) [GAP] (warn)
- **A method that uses another object's API more than its own belongs on that object.**
  (within a method, group member accesses by receiver root; fire when one foreign
  project-owned receiver's count > the `$this` count and ≥ 3; not ctor/serializer)
  [covers: FeatureEnvy (access-ratio extension)] (warn)
- **Reach a collaborator's data through at most one hop.** (property/method chain depth
  ≥ 3 rooted at `$this->prop`/param whose terminal value feeds a condition/comparison/
  call arg; intermediate types project-owned via index) [covers: DemeterEndpointReach
  (generic multi-hop extension)] (warn)
- **Don't branch on a collaborator's getter to choose which of ITS methods to call.**
  (`if`/match whose condition reads `$obj->state`/`$obj->getX()` and whose branches both
  call methods on the SAME `$obj` — tell-don't-ask) [GAP] (warn)
- **A getter must not hand out a live mutable internal collection.** (public method
  returning `$this->prop` of array/mutable-collection type where the class elsewhere
  mutates that prop; index: a caller writes to the result) [GAP] (warn) — *root cause;
  the cross-file reference-leak variant folds in here*
- **Don't guard the same precondition in both a method and the collaborator it calls.**
  (index: method A guards param `$p` then passes it to B whose body opens with a
  structurally-equal guard on the matching param) [GAP] (warn) — *the localized
  double-guard across a call edge*
- **Don't duplicate a multi-statement guard/normalisation prologue across sibling
  methods.** (one class, ≥2 methods whose first K≥2 statements hash structurally-equal,
  name-insensitive) [GAP] (warn)
- **Extract duplicated code fragments instead of copy-pasting a method body.** (index:
  token/AST-normalised fragments ≥ `MIN_LINES` repeated across the codebase) [covers:
  DuplicateCode] (warn)
- **Don't duplicate the same literal log/message/route/config string at ≥3 sites.**
  (cross-scroll census: the same non-trivial `String_` literal at ≥ N=3 distinct arg
  sites) [GAP] (warn) — *distinct from RepeatedFallback (`??` chains) and single-site
  HardcodedLiteral*
- **3+ values that always travel together should be a value object.** (index: same
  param cluster repeated across ≥3 call sites) [covers: DataClumpToValueObject] (warn)
- **A non-static public method that never touches `$this` should be static or moved.**
  (non-static, non-abstract public method — not `__construct`/intentional `__invoke` —
  with zero `$this` references, longer than a trivial constant return) [GAP] (warn)
- **A private method returning a value nobody uses should be void.** (index: private
  method returns non-void, every caller discards the result) [covers: DeadProducer]
  (warn)
- **Return/yield typed results instead of threading a write-only accumulator.**
  (accumulator param mutated and passed through methods, never read by the producer)
  [covers: PreferYieldOverAccumulator] (warn)
- **Group related parameters into an object when the list is long.** (param count over
  threshold) [covers: TooManyParameters] (warn)
- **Keep methods short.** (method statement/line count over threshold) [covers:
  LongMethod] (warn) — *size-as-SRP proxy; pairs with the complexity rule above*
- **Extract a big closure to a named private method.** (closure body size over
  threshold) [covers: ShortClosure] (warn)
- **Extract controller private methods to services past the limit.** (controller
  private-method count over threshold) [covers: ControllerPrivateMethods] (warn)
- **Inline a trivial single-call private helper that only names a fragment — but never
  a guard/named-intent extraction.** (private method, exactly one in-class caller, ≤2
  statements, no branching/`If_`/`throw`/early-return) [GAP] (warn) — *cosmetic*

*Rejected:* "Call a method through a consistent dispatch — don't invoke it statically in
some sites and on an instance in others." The signal (same FQCN+method as both
`StaticCall` and `MethodCall`) is detectable, but in PHP a method is either declared
static or not — a genuine both-ways call is a parse/type error already caught by the
runtime, and the "static helper that secretly reads instance state via a wrapper"
sub-case has no reliable AST signal distinguishing it from legitimate static use.
Folded the *defensible* part (static-helpers-alongside-injected-state) into the
mixed-dispatch rule above; dropped the dispatch-consistency rule itself.

---

## 5. EnumDispatchDiscipline (accepted new group)

*One concern: a closed value set — is it sealed as an enum, dispatched on the type, and
exhaustively handled?* A strong cluster (12 prophets) the proposal correctly splits
out from Boundary/Cohesion.

- **An unhandled closed-set case must throw a named exception — not return
  null/`Option::none()`.** (match/switch over a closed-set enum where every real arm
  yields a value and the `default`/missing arm returns null/none) [covers:
  ThrowOnUnhandledCase] (warn) — *root cause; single home here, defers in
  AbsenceOption & ErrorException via RootCauseMap*
- **Suggest an enum for a string/int field whose value space is a closed set.**
  (string/int field whose assignments/comparisons enumerate a fixed literal set, via
  AST/reflection) [covers: PreferEnumForClosedSetField] (warn)
- **Use enum cases instead of raw string literals for closed-set values.** (repeated
  string-literal set used as discrete values across comparisons) [covers:
  StringsThatShouldBeEnums] (warn)
- **Prefer a native enum over a hand-rolled constant class.** (class of only const
  scalars used as a closed value set) [covers: PreferNativeEnum] (warn)
- **A match/switch over strings that mirror an enum's cases should dispatch on the
  enum.** (arm labels equal an existing enum's case values, via reflection) [covers:
  StringMatchMirrorsEnum] (warn)
- **Move per-case dispatch / type-constant mappings onto the type.** (call-site
  match/switch on a type's identity/constant the type could own as a method) [covers:
  PreferTypeMethodOverInlineDispatch] (warn)
- **Extract a wide per-case behavioural dispatch into strategy objects + a map.**
  (match/switch over enum cases with N+ behavioural arms) [covers:
  BehaviouralEnumDispatch] (warn)
- **Name reused subsets of an enum on the enum.** (same set of enum cases
  compared/`in_array`'d at multiple sites) [covers: PreferEnumCaseGroups] (warn)
- **Anchor a `CompareSelf` set comparison on the non-null enum instance, not the static
  form.** (`EnumName::is(...)` static call where an instance is in scope) [covers:
  AnchorEnumComparison] (warn)
- **Use a `CompareSelf`-style helper instead of chained enum equality.** (chained
  `===`/`in_array` enum equality) [covers: SuggestCompareSelfTrait] (warn)
- **An enum whose cases mirror a config-registered set should be config-driven.** (enum
  cases 1:1 with a `config()`-registered key set) [covers: PreferConfigDrivenRegistry]
  (warn) — *bridges RegistrySetResolver via one RootCauseMap edge*

---

## 6. RegistrySetResolverDiscipline (accepted new group)

*One concern: keyed-store, membership-set, and first-match-dispatch shapes — is each
shaped, named, kept pure, and given a total return contract?* Driven entirely by
`RegistryShape`/`SetShape`/`ResolverShape` AST detectors (shape, never name).

- **A class hand-rolling the registry shape should extract a shared base.**
  (`RegistryShape`: register + keyed store + lookup across classes) [covers:
  RegistryPattern] (warn) — *root cause*
- **A registry-shaped class should be named `*Registry` and extend a base.**
  (`RegistryShape` match + name/base mismatch; shape drives it, name only the verdict)
  [covers: RegistryNamingHonesty] (warn)
- **A registry stays a pure keyed store; resolution/query belongs on a collaborator.**
  (`RegistryShape` match + resolution/query methods beyond store/lookup) [covers:
  RegistryPurity] (warn)
- **A registry returns the item or throws — never `Option<T>`/`?T`.** (`RegistryShape`
  match + non-finder getter typed Option/nullable; `find`/`search`/`try`-prefixed
  finders exempt) [covers: RegistryReturnContract] (warn) — *ABSENCE_SYMPTOMS edge to
  AbsenceOption*
- **A Registry subclass must not override `all()` to a private store, orphaning
  `register()`.** (subclass of a registry base overrides `all()` with a private store,
  `register()` unused, via reflection/AST) [covers: RegistryBaseBypass] (warn)
- **An eager read-only registry must not lazily build / populate-on-miss.** (lookup
  method that conditionally builds/writes the store) [covers: EagerRegistry] (warn)
- **A set-shaped class (add + iterate, no keyed lookup) should be named `*Set`.**
  (`SetShape`: add+iterate, no keyed get) [covers: SetNamingHonesty] (warn)
- **A set is total + iterate-only: `has(): bool`, no Option/nullable leak, no
  `get(string)`.** (`SetShape` match + return-contract violation) [covers:
  SetReturnContract] (warn)
- **Drive first-match dispatch into the resolver + Predicate kernel.** (`ResolverShape`:
  first-match if/elseif chain over predicates) [covers: ResolverPattern] (warn)
- **A `*Resolver` must do first-match dispatch via the kernel — else rename.**
  (`ResolverShape` shaped/named resolver lacking a first-match body) [covers:
  ResolverNamingHonesty] (warn)
- **Compose classifier checks with `anyOf()`/`allOf()`, not a `||`/`&&` chain of
  `->matches()`.** (`||`/`&&` chain of predicate calls) [covers:
  PreferClassifierComposition] (warn)
- **Extract a non-trivial `->then()` branch into a named factory returning a callable.**
  (inline non-trivial closure passed to a resolver `->then()`) [covers:
  PreferNamedBranchFactory] (warn)
- **Classify via a marker interface or the AST, not a hardcoded list of type names.**
  (a const array of class-name strings used as a classifier list) [covers:
  PreferInterfaceOverTypeList] (warn) — *the cardinal-rule prophet itself*

---

## 7. ImmutabilityValueObjectDiscipline (accepted new group)

*One concern: a value carrier with no identity — is it immutable, data-only, and
constructed once through its constructor?* All-AST/reflection, gated on "not a
persistable record".

- **A no-identity value object must declare its data properties `readonly`.**
  (reflection: not a persistable record — no `save()`/`persist()`/ORM base — no scalar
  `id`; ≥1 public non-static property lacking `MODIFIER_READONLY`; exempt
  array-constructible framework-hydrated Data) [GAP] (warn) — *root cause; distinct from
  ReadonlyDataProperties which handles the value-injecting-attribute inverse*
- **A pure value object must not hold a constructor-injected service dependency.**
  (reflection: all data props readonly except one whose type is an interface or a class
  whose own ctor takes services — a behaviour collaborator in a data carrier) [GAP]
  (warn)
- **A `withX()` on a readonly value object must construct a new instance, not
  clone-and-mutate.** (in an all-readonly class, an `Assign` to a `PropertyFetch` on a
  local holding `clone $this`, or on `$this` outside `__construct`) [GAP] (warn)
- **Don't expose a void single-field setter on an otherwise-constructed object — fold it
  into the constructor.** (method body exactly `$this->prop = $param;`, void return, not
  `__`-prefixed, prop not assigned in the ctor) [GAP] (warn)
- **Remove `readonly` from a Data property carrying a value-injecting attribute.**
  (reflection: Data property is `readonly` AND has a `#[WithCast]`-style attribute)
  [covers: ReadonlyDataProperties] (warn)

---

## 8. CollectionIterationDiscipline (accepted new group)

*One concern: how are collections produced, traversed, and shaped?* Pulls the
iterable-specific rules out of Absence/Cosmetic where they were diluted.

- **A collection-returning method must not return null/false for "no elements" —
  return an empty collection.** (return type `?array`/`?iterable`/`?Collection` or
  `array|false` with one branch yielding null/false and another a collection) [covers:
  PreferEmptyOverNull (iterable-return specialisation)] (warn) — *cross-references the
  AbsenceOption empty-over-null rule; iterable-return scoping is the distinguishing
  signal*
- **Don't mutate the collection being iterated.** (inside a `foreach` over `$x`, `$x[]
  =`, `unset($x[$k])`, or a mutating call on the same `$x`) [GAP] (sin)
- **Don't `count()`/empty-check then `[0]`/re-iterate the same collection — fold the
  existence check into one traversal.** (guard on `count($x)`/`!empty($x)` whose branch
  accesses `$x[0]` or a second `foreach` over the same `$x`, no reassignment) [GAP]
  (warn)
- **A push-only accumulator loop should use a declarative transform.** (`foreach` whose
  body is a single `$acc[] = f($v)` — optionally guarded — where `$acc=[]` was just
  initialised and is returned/used right after, no other side effect) [GAP] (warn) —
  *distinct from PreferYieldOverAccumulator (params) and PreferCollectionPipeline
  (nested array_*)*
- **Prefer a Collection chain over nested `array_*` compositions.** (nested
  `array_map`/`array_filter`/`array_reduce`) [covers: PreferCollectionPipeline] (warn)
- **A `filter()`/`reject()` closure should hold ONE rule.** (filter/reject closure body
  with an `&&`-chain or negation) [covers: OneRulePerFilter] (warn)

---

## 9. ControlFlowTotalityDiscipline (accepted new group — small but distinct)

*One concern: does every control path produce the value its type promises?*

- **A non-nullable, non-void method must not fall off the end implicitly returning
  null.** (declared return non-nullable+non-void; a control path — `if` w/o `else`,
  `foreach` w/o post-loop return — reaches the closing brace with no return/throw) [GAP]
  (sin)
- **A match/switch over a closed set must be exhaustive or end in a throwing default.**
  (scrutinee resolves to an enum/class-constant set; arms < all cases AND default
  returns null/''/[]/false, or no default for a switch) [covers: ThrowOnUnhandledCase
  (exhaustiveness arm — defers to EnumDispatch's owning verdict; this is the
  default-throw specialisation)] (sin)
- **A `bool`-returning method must return a real bool, not a falsy/truthy proxy.**
  (declared return `bool` but a return yields a non-bool — `array_filter` result,
  `?object`, `int` count — coerced by the type) [GAP] (warn)
- **Don't guard the same precondition with both an early-return and a later re-check in
  one method.** (two syntactically-equal or provably-complement conditions over the same
  variable, the first short-circuiting via return/throw/continue) [GAP] (warn) —
  *intra-method twin of the Cohesion cross-edge double-guard*

*Rejected (deferred to ErrorException, not duplicated here):* the invariant-guard-must-
throw rule — it lives in ErrorExceptionDiscipline; this group owns only totality vs the
declared return type, not the throw-vs-sentinel choice.

---

## Coverage & retire map

`SINGLETON` = stays atomic (security, framework-congruence, layout/doc cosmetics, or a
pervasive cross-cutting convention) — deliberately NOT folded.

| Existing prophet | Discipline (home) |
|---|---|
| **OptionDisciplineProphet** | AbsenceOption *(seed compiler)* |
| PreferTotalOverNullableProphet | AbsenceOption |
| PreferDefaultOverNullableProphet | AbsenceOption |
| PreferDefaultFallbackProphet | AbsenceOption |
| PreferEmptyOverNullProphet | AbsenceOption *(iterable arm cross-refs CollectionIteration)* |
| PreferNullObjectDefaultsProphet | AbsenceOption |
| NoOptionInUnionProphet | AbsenceOption |
| NoOptionToNullProphet | AbsenceOption |
| **PreferTypedBoundaryProphet** | BoundaryTyping *(anchor)* |
| WideUnionTypeProphet | BoundaryTyping *(Option membership defers to AbsenceOption)* |
| NoCoalesceOnNonNullableProphet | BoundaryTyping |
| NoNullCoalesceToNullProphet | BoundaryTyping |
| PreferNullCoalescingProphet | BoundaryTyping |
| PreferTypeCoalesceProphet | BoundaryTyping |
| PreferNativeTypedAccessorProphet | BoundaryTyping |
| PreferCoercionHelperProphet | BoundaryTyping |
| MixedConfigValueUsedTypedProphet | BoundaryTyping |
| PreferCoalesceFactoryProphet | BoundaryTyping |
| PreferCoalescingFactoryProphet | BoundaryTyping |
| PreferCoalesceForProphet | BoundaryTyping |
| RepeatedFallbackProphet | BoundaryTyping *(overlaps Cohesion duplication; coalesce-chain owns it)* |
| NoConditionalArraySpreadProphet | BoundaryTyping |
| NoArrayBagProphet | BoundaryTyping *(root cause of NoArrayStringIndexing)* |
| NoArrayStringIndexingProphet | BoundaryTyping *(symptom)* |
| **NoSwallowedNotFoundProphet** | ErrorException *(anchor; ABSENCE_SYMPTOMS edge)* |
| **PreferNamedExceptionsProphet** | ErrorException *(anchor)* |
| **ThrowOnUnhandledCaseProphet** | EnumDispatch *(single home; defers in AbsenceOption + ErrorException)* |
| DataClassFromArrayOnlyProphet | DataConstruction |
| ExplicitDataFactoryProphet | DataConstruction |
| NoExternalDataFromProphet | DataConstruction |
| NoManualHydrationProphet | DataConstruction |
| NoRepeatedHydrationProphet | DataConstruction |
| PreferDataCollectionOfProphet | DataConstruction |
| PreferDataTransformersProphet | DataConstruction |
| ReadonlyDataPropertiesProphet | ImmutabilityValueObject *(attribute-inverse arm)* |
| NoRequestDataPassthroughProphet | DataConstruction *(overlaps RequestInput)* |
| NoAuthUserInDataClassesProphet | DataConstruction |
| DataClumpToValueObjectProphet | CohesionStructure |
| PreferEnumForClosedSetFieldProphet | EnumDispatch |
| StringsThatShouldBeEnumsProphet | EnumDispatch |
| StringMatchMirrorsEnumProphet | EnumDispatch |
| PreferNativeEnumProphet | EnumDispatch |
| BehaviouralEnumDispatchProphet | EnumDispatch |
| PreferTypeMethodOverInlineDispatchProphet | EnumDispatch |
| PreferEnumCaseGroupsProphet | EnumDispatch |
| AnchorEnumComparisonProphet | EnumDispatch |
| SuggestCompareSelfTraitProphet | EnumDispatch |
| PreferConfigDrivenRegistryProphet | EnumDispatch *(bridges RegistrySetResolver)* |
| RegistryPatternProphet | RegistrySetResolver |
| RegistryNamingHonestyProphet | RegistrySetResolver |
| RegistryPurityProphet | RegistrySetResolver |
| RegistryReturnContractProphet | RegistrySetResolver *(ABSENCE_SYMPTOMS edge)* |
| RegistryBaseBypassProphet | RegistrySetResolver |
| EagerRegistryProphet | RegistrySetResolver |
| SetNamingHonestyProphet | RegistrySetResolver |
| SetReturnContractProphet | RegistrySetResolver |
| ResolverPatternProphet | RegistrySetResolver |
| ResolverNamingHonestyProphet | RegistrySetResolver |
| PreferClassifierCompositionProphet | RegistrySetResolver |
| PreferNamedBranchFactoryProphet | RegistrySetResolver |
| PreferInterfaceOverTypeListProphet | RegistrySetResolver |
| **OutOfPurposeProphet** | CohesionStructure *(SRP anchor)* |
| FeatureEnvyProphet | CohesionStructure |
| DemeterEndpointReachProphet | CohesionStructure |
| PassThroughDependencyProphet | CohesionStructure |
| DeadProducerProphet | CohesionStructure |
| PreferYieldOverAccumulatorProphet | CohesionStructure |
| DuplicateCodeProphet | CohesionStructure |
| LongMethodProphet | CohesionStructure |
| ShortClosureProphet | CohesionStructure |
| TooManyParametersProphet | CohesionStructure |
| ControllerPrivateMethodsProphet | CohesionStructure |
| PreferCollectionPipelineProphet | CollectionIteration |
| OneRulePerFilterProphet | CollectionIteration |
| NoContainerResolutionProphet | InjectionDependency |
| NoFacadesInServicesProphet | InjectionDependency |
| PreferInjectionOverSingletonProphet | InjectionDependency |
| ConstructorDependencyInjectionProphet | InjectionDependency |
| NoDirectRequestInputProphet | RequestInput |
| NoRawRequestProphet | RequestInput |
| NoValidatedMethodProphet | RequestInput |
| NoInlineValidationProphet | RequestInput |
| FormRequestTypedGettersProphet | RequestInput |
| SecretToLogOrResponseProphet | SINGLETON *(security)* |
| TaintedInputToSinkProphet | SINGLETON *(security)* |
| MigrationModelDriftProphet | SINGLETON *(schema congruence)* |
| ConfigKeyContractProphet | SINGLETON *(config congruence)* |
| TranslationKeyCongruenceProphet | SINGLETON *(lang congruence)* |
| HardcodedLiteralShouldBeConfigProphet | SINGLETON *(config-source congruence)* |
| EncapsulateModelMutationProphet | SINGLETON *(Eloquent encapsulation)* |
| NoInlineBootLogicProphet | SINGLETON *(Eloquent lifecycle)* |
| QueryModelsThroughQueryMethodProphet | SINGLETON *(Eloquent convention)* |
| NoJsonResponseProphet | SINGLETON *(Inertia stack)* |
| KebabCaseRoutesProphet | SINGLETON *(routing cosmetic)* |
| EnumCaseMustBeDocumentedProphet | SINGLETON *(doc cosmetic — NOT EnumDispatch)* |
| LongDocblockProphet | SINGLETON *(doc cosmetic)* |
| NoInlineParamDocProphet | SINGLETON *(doc cosmetic)* |
| PushGenericToSourceProphet | SINGLETON *(type-doc placement)* |
| ConstantsAndPropertiesFirstProphet | SINGLETON *(layout cosmetic)* |
| ComputedPropertyMustHookProphet | SINGLETON *(PHP 8.4 hook convention)* |
| NoCompactProphet | SINGLETON *(anti-pattern)* |
| NoRawLiteralProphet | SINGLETON *(literal-naming, cross-cutting)* |
| PreferSprintfProphet | SINGLETON *(string-style cosmetic)* |
| PreferFirstClassCallableProphet | SINGLETON *(callable cosmetic)* |
| PreferStaticOverInvokableConstructProphet | SINGLETON *(construction cosmetic)* |
| NoRedundantDefaultArgumentProphet | SINGLETON *(call-site cosmetic)* |

### Disciplines that DERIVE from the existing roster but were not in the four core seeds

- **DataConstructionDiscipline** — the Spatie-Data construction family (8 prophets);
  cohesive and already shipping. Accept as a discipline.
- **InjectionDependencyDiscipline** & **RequestInputDiscipline** — small Laravel-flavoured
  bundles (4–5 prophets each); accept as disciplines, framework-scoped.

### Cross-discipline RootCauseMap edges (add ONE edge each, never hand-override both ends)

- `NoArrayBag` → `NoArrayStringIndexing` *(within BoundaryTyping)*
- `PreferTypedBoundary` → `PreferNativeTypedAccessor` → `PreferCoercionHelper`
- `OutOfPurpose` → role-second-engine symptoms *(Cohesion → Registry/Data/Resolver)*
- `ThrowOnUnhandledCase` (EnumDispatch) → ABSENCE_SYMPTOMS *(AbsenceOption,
  ErrorException defer)*
- `NoSwallowedNotFound` / catch→`Option::none()` (ErrorException) → ABSENCE_SYMPTOMS
  *(AbsenceOption defers)*
- `RegistryReturnContract` (RegistrySetResolver) → ABSENCE_SYMPTOMS *(AbsenceOption defers)*
- `WideUnionType` (BoundaryTyping) ↔ Option-adoption (AbsenceOption) — BoundaryTyping
  owns the union shape, AbsenceOption owns the Option fix.
- `tryFrom + ===null + throw`: ErrorException owns the throw-form, AbsenceOption owns the
  coalesce-form — one shared edge, two presentation arms.

### Rules dropped entirely (no shippable AST signal)

- **Consistent static-vs-instance dispatch of one method** — PHP forbids calling a
  non-static method statically (and vice versa) at the type level, so the "same method
  both ways" census has no legitimate-vs-violation discriminator. The defensible part
  (static helpers alongside injected state) survives as a CohesionStructure rule; the
  dispatch-consistency rule itself is dropped.
- **Boundary-scoped "closed-set string field → enum"** — not dropped for lack of signal
  but for single-owner: it is `PreferEnumForClosedSetField` in EnumDispatch; a
  boundary-scoped clone would duplicate the rule.
