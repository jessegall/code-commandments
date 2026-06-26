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

**Status: 30 detectors shipping.**

---

## absence
| Sin | Status |
|---|---|
| Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it) | ✅ `DeNulledFinderDetector` (call-graph blast radius: de-nulled at ≥2 sites) |
| `Option<T>` used as a nullable costume — `?Option`, `Option \| null`, `unwrapOr(null)` | ✅ `OptionAsNullableDetector` |
| "Nothing" with a natural empty form returned as `null` (`array \| null` → should be `[]`) | ✅ `NullableCollectionReturnDetector` |
| `?? <empty literal>` filling a required slot (manufactured fake) | ✅ `ManufacturedFakeFillDetector` (fix-at-the-source) |
| Nullable callback normalised in the body instead of a Null Object default | ⬜ |

## concurrent-state
| Sin | Status |
|---|---|
| Class `extends Concurrent` instead of composing `Concurrent<self>` | ✅ `ConcurrentSubclassDetector` |
| `Cache::get/put` with a hand-built key for cross-process state (should be a `::for()` domain object) | ⬜ (today only the raw `Cache::` facade is caught) |
| Pure accessor on the handle not marked `#[ReadonlyMethod]` | ⬜ |
| `$c->count++` / `$c->items[] = …` on the handle (lost-update race) | 🧠 needs handle-type resolution |

## documentation
| Sin | Status |
|---|---|
| History/archaeology comments ("previously / used to / refactored / changed from", task refs) | ✅ `ArchaeologyCommentDetector` |
| Inline comment that just restates the code | ⬜ |
| Multi-paragraph class docblock (class too big) | ✅ `BloatedDocblockDetector` |
| Docblock not present-tense "what it is now" + tags | 〰️ |

## enums-with-behaviour
| Sin | Status |
|---|---|
| `match`/`switch` over an enum's `->value` at a call site (homeless method) | ✅ `EnumValueMatchDetector` |
| Closed set as raw string literals / a `const` class of scalars (not a native enum) | ✅ `ConstClassEnumDetector` |
| `match` over string literals that mirror an existing enum's cases | ✅ `StringMatchMirrorsEnumDetector` |
| `match` `default` that returns `null`/`''`/`[]` instead of throwing | ✅ `MatchDefaultReturnsNullDetector` |

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
| The process — name the symptom, trace upstream, fix at the origin, delete the symptom | 〰️ taught, not detected (the parent move behind the other detectors) |

## guard-clauses-and-flow
| Sin | Status |
|---|---|
| `?? throw` / `=== null ? …` feeding further work on the same line (inline throw mid-expression) | ✅ `InlineThrowDetector` |
| if/elseif/else ladder or ≥2-deep nesting (should hoist a guard / extract) | ⬜ `DeepNestingDetector` |
| Loop body (multi-statement) wrapped in an `if` instead of `continue` guard | ✅ `LoopInvertedGuardDetector` |
| Precondition checked inline/buried instead of an early guard at the top | 〰️ |

## laravel-idioms
| Sin | Status |
|---|---|
| Raw `->input()/->get()/->query()` on a Request | ✅ `RawRequestInputDetector` |
| `app()`/`resolve()` reach inside a container-resolved class | ✅ `ContainerReachDetector` |
| Laravel facade call (`Cache::`, `Log::`, `Mail::` …) | ✅ `FacadeCallDetector` |
| `config('…')` read inside a class | ✅ `ConfigReadDetector` |
| `new <Service>` inside a class instead of constructor injection | ⬜ `NewServiceInClassDetector` |
| Untyped `->get()` on a Fluent/ValueBag (should be a typed accessor) | ⬜ |
| Raw `->where('col', …)` expressing a concept repeated at call sites (should be a scope) | ⬜ `RawWhereShouldBeScopeDetector` |
| Bare `update([...])` / set-property-then-`save()` at a call site (should be an intention method) | ✅ `ModelMutationAtCallSiteDetector` |

## role-vocabulary
| Sin | Status |
|---|---|
| A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw) | ✅ `NullableRegistryLookupDetector` |
| Hand-rolled keyed store / set / first-match chain not named/based as `*Registry`/`*Set`/`*Resolver` | ⬜ (role inference) |
| A `*Set` exposing a keyed `get(string)` (that's a Registry) | ⬜ |
| A `*Resolver` doing `\|\|`/`&&` predicate chains instead of `anyOf`/`allOf` first-match | ⬜ |
| Classification by a `const [...]` list of class-name strings instead of a marker interface/type | ⬜ |
| A role class doing two jobs (resolution/assembly smuggled into a registry/data class) | 〰️ |

## spatie-data
| Sin | Status |
|---|---|
| `new <Data subclass>` instead of `::from()` / a `fromX()` factory | ✅ `NewDataObjectDetector` |
| All-nullable "god" DTO — every field `?T`/defaulted (type doesn't tell the truth) | ✅ `AllNullableDataDetector` |
| Collections hydrated with `::from()` in a loop instead of `#[DataCollectionOf]` + `::collect()` | ✅ `ManualHydrationLoopDetector` |
| Data class not `final` / props not `readonly` promoted | ✅ `NonFinalDataDetector` (final; readonly TBD) |
| `fromX()` object factory missing its `@method static static from(T)` (or the array shape wrongly documented) | 〰️ |
| snake_case boundary without one class-level `#[MapInputName]` | ⬜ |

## value-objects
| Sin | Status |
|---|---|
| String-indexing (`$arr['key']`) a structured array param (an unborn type) | ✅ `ArrayBagDetector` |
| Returning a multi-field string-keyed array literal (a bag that should be a value object) | ✅ `ArrayReturnBagDetector` |
| Returning a raw decoded boundary array (`json_decode(...)`) untyped | ✅ `RawDecodedArrayReturnDetector` |
| 3+ values threaded as separate params (a data clump → one object) | ⬜ `DataClumpDetector` |
| A primitive carrying hidden rules/validation (primitive obsession) | ⬜ |
| Type introduced downstream after the loose data has been threaded around | 〰️ (this is `fix-at-the-source` applied) |
