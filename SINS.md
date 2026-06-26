# Sin coverage — the detector roadmap

The source of truth for **what we detect** vs **what we still owe**. Every distinct
sin taught by a skill in `skills/` is listed here, whether or not it has a detector
yet. A skill teaches the rule; a **detector** finds the sin and points the agent
back at the skill. The fixture (`tests/Fixtures/shop`) proves each detector both
ways (fires on a `#[Sinful]`, silent on a righteous twin), with ≥3 diverse
scenarios enforced by `FixtureDetectorTest`.

**Legend:** ✅ implemented · 🔜 planned (next) · ⬜ missing · 🧠 needs the call
graph · 〰️ hard to detect structurally (process/positive rule — may stay
skill-only).

Keep this current: when a detector ships, flip its row to ✅ with the class name.

**Status: 47 detectors shipping.**

Every cleanly + low-FP detectable sin now has a detector. The rows below still
marked 🧠/〰️ were each evaluated against real consumer codebases (workflows,
smart-farmers) and deferred for a concrete reason — they need the call graph,
over-fire structurally, or have no real-world signal to validate the
false-positive side against. They stay skill-only until that changes.

---

## absence
| Sin | Status |
|---|---|
| Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it) | ✅ `DeNulledFinderDetector` (call-graph blast radius: de-nulled at ≥2 sites) |
| `Option<T>` used as a nullable costume — `?Option`, `Option \| null`, `unwrapOr(null)` | ✅ `OptionAsNullableDetector` |
| "Nothing" with a natural empty form returned as `null` (`array \| null` → should be `[]`) | ✅ `NullableCollectionReturnDetector` |
| `?? <empty literal>` filling a required slot (manufactured fake) | ✅ `ManufacturedFakeFillDetector` (fix-at-the-source) |
| Nullable callback normalised in the body instead of a Null Object default | ✅ `NullableCallbackDetector` |

## concurrent-state
| Sin | Status |
|---|---|
| Class `extends Concurrent` instead of composing `Concurrent<self>` | ✅ `ConcurrentSubclassDetector` |
| `Cache::get/put` with a hand-built key for cross-process state (should be a `::for()` domain object) | 〰️ the raw `Cache::` facade is already caught by `FacadeCallDetector`; isolating the hand-built-key refinement adds noise over signal |
| Pure accessor on the handle not marked `#[ReadonlyMethod]` | 🧠 needs handle-type resolution (which class is the Concurrent handle) to know which methods are accessors |
| `$c->count++` / `$c->items[] = …` on the handle (lost-update race) | 🧠 needs handle-type resolution |

## documentation
| Sin | Status |
|---|---|
| History/archaeology comments ("previously / used to / refactored / changed from", task refs) | ✅ `ArchaeologyCommentDetector` |
| Inline comment that just restates the code | 〰️ restatement-vs-explanation can't be told apart structurally — any heuristic over-fires on legitimate intent comments |
| Multi-paragraph class docblock (class too big) | ✅ `BloatedDocblockDetector` |
| Docblock not present-tense "what it is now" + tags | 〰️ |
| Docblock that only restates the typed signature (`@param Type $x`, no description) | ✅ `CeremonyDocblockDetector` |

## enums-with-behaviour
| Sin | Status |
|---|---|
| `match`/`switch` over an enum's `->value` at a call site (homeless method) | ✅ `EnumValueMatchDetector` |
| Closed set as raw string literals / a `const` class of scalars (not a native enum) | ✅ `ConstClassEnumDetector` |
| `match` over string literals that mirror an existing enum's cases | ✅ `StringMatchMirrorsEnumDetector` |
| `match` `default` that returns `null`/`''`/`[]` instead of throwing | ✅ `MatchDefaultReturnsNullDetector` |
| `in_array($x, [literals])` whose literals mirror an existing enum's cases | ✅ `InArrayMirrorsEnumDetector` |
| `$x === Enum::A \|\| $x === Enum::B` — a hand-rolled case-group test | ✅ `EnumCaseOrChainDetector` |

## exceptions
| Sin | Status |
|---|---|
| `throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type | ✅ `GenericExceptionDetector` |
| Message string built at the throw site (no domain values / named factory) | ✅ `MessageAtThrowDetector` |
| `catch` whose only effect is `return null/false/[]/none()`; empty catch (silent swallow) | ✅ `SwallowCatchDetector` |
| Wrapping a caught exception without passing it as `previous`/cause | ✅ `WrappingWithoutCauseDetector` |

## fix-at-the-source
| Sin | Status |
|---|---|
| `?? <default>` / `?? ''` / nullable / repeated guard papering over an absent value at the consumer | ✅ `ManufacturedFakeFillDetector` (the argument-fill form) |
| Copy-pasted code — two+ functions with an identical AST (formatting/comments aside) | ✅ `DuplicateFunctionDetector` (structural hash of the whole function) |
| Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone) | ✅ `NearDuplicateFunctionDetector` (name/literal-blind shape hash) |
| The process — name the symptom, trace upstream, fix at the origin, delete the symptom | 〰️ taught, not detected (the parent move behind the other detectors) |

## guard-clauses-and-flow
| Sin | Status |
|---|---|
| `?? throw` / `=== null ? …` feeding further work on the same line (inline throw mid-expression) | ✅ `InlineThrowDetector` |
| if/elseif ladder of 4+ branches (should be match/dispatch) | ✅ `IfElseLadderDetector` |
| `if` nested 3-deep (a pyramid — hoist guards / extract) | ✅ `DeepNestingDetector` |
| Loop body (multi-statement) wrapped in an `if` instead of `continue` guard | ✅ `LoopInvertedGuardDetector` |
| `else` after an `if` branch that already returns/throws (redundant) | ✅ `RedundantElseDetector` |
| Nested/chained ternary `$a ? $b : ($c ? $d : $e)` (hidden control flow) | ✅ `NestedTernaryDetector` |
| Precondition checked inline/buried instead of an early guard at the top | 〰️ |

## laravel-idioms
| Sin | Status |
|---|---|
| Raw `->input()/->get()/->query()` on a Request | ✅ `RawRequestInputDetector` |
| `app()`/`resolve()` reach inside a container-resolved class | ✅ `ContainerReachDetector` |
| Laravel facade call (`Cache::`, `Log::`, `Mail::` …) | ✅ `FacadeCallDetector` |
| `config('…')` read inside a class | ✅ `ConfigReadDetector` |
| `new <Service>` inside a class instead of constructor injection | 〰️ prototyped + dropped: "instantiated AND injected somewhere" is necessary-but-not-sufficient and over-fired badly (196 hits — value objects, `::for` factories, DTOs); no clean structural service-vs-value signal |
| Untyped `->get()` on a Fluent/ValueBag (should be a typed accessor) | 🧠 needs receiver-type resolution to know the `->get()` target is a Fluent/ValueBag and not an unrelated collection |
| Raw `->where('col', …)` expressing a concept repeated at call sites (should be a scope) | 🧠 the sin IS the repetition across call sites — needs the call graph to count, else a one-off `->where` is a false positive |
| Set-property-then-`save()` at a call site (should be an intention method) | ✅ `ModelMutationAtCallSiteDetector` |
| Bare `$model->update([...])` mass-array update at a call site | ✅ `MassUpdateAtCallSiteDetector` |

## role-vocabulary
| Sin | Status |
|---|---|
| A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw) | ✅ `NullableRegistryLookupDetector` |
| Hand-rolled keyed store / set / first-match chain not named/based as `*Registry`/`*Set`/`*Resolver` | 〰️ role inference from shape alone (a private array + add/get) collides with countless legitimate classes; no low-FP structural signal |
| A `*Set` exposing a keyed `get(string)` (that's a Registry) | 〰️ cleanly detectable (name + `get(string)` shape) but zero real-world signal across both consumers (all `*Set`s are properly unkeyed) — nothing to validate the FP side against; revisit if a case appears |
| A `*Resolver` doing `\|\|`/`&&` predicate chains instead of `anyOf`/`allOf` first-match | 〰️ a bare `\|\|`/`&&` in a `*Resolver` is overwhelmingly ordinary boolean logic, not a predicate-dispatch chain — no structural signal separates the sin from legitimate conditionals |
| Classification by a `const [...]` list of class-name strings instead of a marker interface/type | 〰️ to avoid firing on legitimate registration arrays it must be gated on an `in_array($x::class, self::CONST)` membership test — zero such usages across both consumers, so no FP-side to validate |
| A role class doing two jobs (resolution/assembly smuggled into a registry/data class) | 〰️ |

## tell-dont-ask
| Sin | Status |
|---|---|
| Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object: iterate its collection, query it (`array_reduce`/`in_array` over `$obj->coll()`, any chain depth), or read-then-mutate it | ✅ `FeatureEnvyDetector` (semantic, no name lists; `ChainResolver` follows nested objects; flat-scalar Strategies, mappers, polymorphic components & request boundaries exempt; mutation co-fires with `ModelMutationAtCallSite` — both real) |
| Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator (`$this->registry->get($node->key)->reservedOutputNames`) | ✅ `KeyedLookupEnvyDetector` (`Support\LookupEnvy`: one owned param used only as a lookup key, returns a fact, fetch-and-read via a `$this` collaborator, no construction — the indirect form the direct checks miss) |

## type-honesty
| Sin | Status |
|---|---|
| Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet" | ✅ `MaskedInvariantDetector` (`Support\OwnStateMask`: private nullable assigned outside `__construct`, reached via nullsafe on `$this`, defaulted to a non-null literal; injected-optional collaborators are `absence`, not this) |
| Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input | ✅ `ScratchStateRestoreDetector` (save-then-restore of the same field via a local; methods bracketing a `callable`/`Closure` parameter are exempt — that's the dynamic-scope / Context pattern, not a smuggled input) |

## spatie-data
| Sin | Status |
|---|---|
| `new <Data subclass>` instead of `::from()` / a `fromX()` factory | ✅ `NewDataObjectDetector` |
| All-nullable "god" DTO — every field `?T`/defaulted (type doesn't tell the truth) | ✅ `AllNullableDataDetector` |
| Collections hydrated with `::from()` in a loop instead of `#[DataCollectionOf]` + `::collect()` | ✅ `ManualHydrationLoopDetector` |
| Data class not `final` / props not `readonly` promoted | ✅ `NonFinalDataDetector` (final; readonly TBD) |
| `fromX()` object factory missing its `@method static static from(T)` (or the array shape wrongly documented) | 〰️ |
| snake_case boundary without one class-level `#[MapInputName]` | 🧠 needs to know the boundary's input keys are snake_case (the wire shape), which isn't visible from the Data class declaration alone |

## value-objects
| Sin | Status |
|---|---|
| String-indexing (`$arr['key']`) a structured array param (an unborn type) | ✅ `ArrayBagDetector` |
| Returning a multi-field string-keyed array literal (a bag that should be a value object) | ✅ `ArrayReturnBagDetector` |
| Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` (also from a closure/arrow) — bundling independent values as a keyless list the caller destructures by position | ✅ `PositionalTupleReturnDetector` (≥3 items, ≥2 distinct variable roots; single-source projections/literal lists exempt) |
| Returning a raw decoded boundary array (`json_decode(...)`) untyped | ✅ `RawDecodedArrayReturnDetector` |
| 3+ values threaded as separate params (a data clump → one object) | ✅ `DataClumpDetector` |
| A primitive carrying hidden rules/validation (primitive obsession) | 〰️ "this string has hidden rules" is a semantic judgement, not a structural fact — any heuristic (regex on a string, length checks) over-fires on ordinary primitives |
| Type introduced downstream after the loose data has been threaded around | 〰️ (this is `fix-at-the-source` applied) |
