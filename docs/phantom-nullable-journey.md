# PhantomNullable — the journey, and where we are

A narrative companion to `phantom-nullable-design.md`. That file is the *design*; this one is the
*story* — the decisions we made, the walls we hit, and the exact state to resume from. Read this first
if you're picking the work back up cold.

## The one-line goal

Flag a field typed `?T` (or `T|null`, `T|Optional|null`) whose value, in practice, is **never once
null** — the `?` is a lie. Point it at the `backend/type-honesty` skill. The fix is to make it required
(so construction fails hard on a real miss) or, where a null is genuinely meaningful, a Null Object
default.

## Why it needed a whole engine

The naïve version — "look at the field's own reads, are they all un-guarded?" — **false-positives**,
because the deciding null-guard often lives *downstream* in the value's provenance, not on the field's
own reads. The proof that killed v0:

> `workflows` `ContextInput::$options` (`array|string|null`) looked like a phantom — but its value
> flows into `InputSocket::$options`, which **is** `=== null` guarded there. The guard is two hops away.

So correctness demands tracing the value **forward through the whole program** until a *consumption*
decides nil-vs-non-nil, and treating a downstream guard as a guard on the origin. That is the
`ValueFlow` provenance graph — the third whole-program index alongside the AST and the call graph.

## The cardinal rule that shaped every decision

**Zero false positives, always — asymmetric bias.** Missing a guard *invents* an FP (unacceptable);
missing an assume just *misses* a phantom (acceptable). So: a guard **anywhere** in the value's forward
closure clears the finding; an unresolvable edge/receiver is **dropped, never guessed**. Incompleteness
makes us miss a phantom, never fabricate one. Every ambiguous call went the FP-safe way.

## The build (phases, all shipped local, unpushed)

1. **`TypeResolver`** (`src/Ast/Support/TypeResolver.php`) — the receiver *chain*: type an expression by
   following receiver-of-receiver hops (a local typed from its `::from`/`new`/`$this->prop`/method-return
   origin, property/method chains, closure/arrow capture, `??`/ternary, `#[DataCollectionOf]` foreach
   element typing, inheritance walk via `parentOf`/`declaringClassOf`). Memoised per codebase.
2. **`ValueFlow`** (`src/Ast/ValueFlow.php`) — forward walk over a worklist with a visited set. Edges:
   assignment, arg→param (named-arg-by-name), return→callsite, field-write (incl. `new`/`::from`),
   array in/out (key-tracked), the `::from([...])` vendor shortcut. Terminals classified as **guard**
   (`?->`/`??`/`=== null`/truthy/`is_*`/`instanceof`/`??=`/isset/empty) or **assume** (un-guarded deref
   OR flows into a non-nullable param). `verdict(fqcn, field)` → `{assume, guard}`; `explain()` and
   `chainPath()` for calibration.
3. **`PhantomNullableDetector`** + **`ChainDetector`** interface — a chain detector's fixture must prove
   3 findings that each cross several files along genuinely different routes AND different *kinds*
   (arg / field-write / return). Realistic Shop fixtures: Billing (arg chain), Fulfillment (field-write
   chain), Loyalty (return chain).
4. **Calibration to zero FPs** on `../workflows` + `../smart-farmers-pos`.

## The walls we hit, and what each taught us

- **`mixed` / untyped params were treated as non-null sinks** → `paramAcceptsNull` (mixed/untyped/
  null-default all accept null).
- **`is_string`/`instanceof`/`??=`/truthy tests weren't guards** → added to the guard set.
- **Inheritance: 351 reads dropped** because a field's type was looked up on the receiver class, not the
  class that *declares* it → `declaringClassOf()` walks the ancestor chain; reads bucket under the
  declaring class.
- **"0 flags on smart-farmers" was a COVERAGE gap, not cleanliness** (see below) — the single most
  important realization about recall.

## The v4.111.0 turn — "check EVERY nullable field"

We broadened from constructor-promoted params to **all** nullable fields on any class (declared
`public ?T $x` too). This surfaced real phantoms — *and* two FP families we traced to root and fixed
with principled, FP-safe engine guards (never a name list):

- **Lazy-memo caches** (`isset($this->map[$k])`): the `isset` wraps the *element access*, not the
  property, so the engine never saw the guard. Fix: `AstNode::isNullGuardedUse` recurses through
  `ArrayDimFetch` — a guarded index access guards its base.
- **Event-sourcing aggregates** (`ShopMergeAggregate`, `EanAggregate`): `$this->id` is null-until-event,
  read only after `if (! $this->started) return` — a sibling-flag guard we couldn't connect. Fix:
  `AstNode::isSelfReadGuardedByStateClause` — a **`$this` self-read** dominated by an early-return guard
  clause on `$this` state is guarded. *Only* self-reads count, so a passive carrier read externally
  (`$context->downloaded`) still fires.

Both only ever *add* guards → can only remove findings, never invent one.

**Chain-fixture rule relaxed**: from "every finding crosses ≥5 files" to "the *deepest* finding proves
≥5" — a deep field-write chain legitimately also flags its shorter intermediate hops (so the Fulfillment
intermediate stages are now all `#[Sinful]` too).

## ⚠️ Calibration gotcha — how to actually measure

`Codebase::scan(path)` **over-scans**: it walks `tests/` and everything, and gives inflated counts (41
on smart-farmers, including test fixtures that are *deliberately* built with nulls to exercise
null-casting — those are honest nullables, not phantoms). **The real surface is the consumer's
configured `$config->paths()`.** Always calibrate by running the CLI *from the consumer directory*:

```
cd ../smart-farmers-pos
bin/commandments judge --sin=phantom-nullable --ignore-package-requirements --no-checklist
```

## Where we are right now (as of v4.111.0, local + tagged, NOT pushed)

- **Correctness: solid.** workflows **0**, smart-farmers **20 — all genuine** (pipeline `*Context`
  set-then-assume fields, `WooCommerceProduct::$id/$name` which the API guarantees, `WorkflowNode`).
  The detector discriminates (flags WC `$id`/`$name`, leaves `$slug`/`$permalink`). **528 tests green**,
  incl. 4 ValueFlow unit tests pinning the two new guard behaviors.
- The detector is SOUND (zero fabricated flags) and fires readily on typed code.

## The open frontier — RECALL, not correctness

The remaining gap is coverage. On smart-farmers, of ~850 nullable fields: ~174 genuinely guarded, ~117
carried/neutral, ~181 never-read (frontend DTOs), and **~376 the trace can't even start** because the
receiver won't resolve — dominated by **Eloquent / vendor magic** (`Model::find`, `$q->first()`,
generic resolvers → `mixed`). Sound AST-clean resolves (closure/coalesce/ternary) only moved coverage
by ~2; the mass needs Eloquent modeling.

**Next grind, in priority order:**

1. **Laravel-aware return-type layer** on a `LaravelNode` (the big modelable chunk): Eloquent
   conventions — `find`→`?static`, `findOrFail`→`static`, `first`→`?Model`, `get`/`all`→`Collection`,
   `firstOrFail`→`Model`. This is where the 376 unresolved receivers live.
2. **Trait value-flow**: `index()` / `TypeResolver::index()` scan only `Class_`, so a value flowing
   through a trait method vanishes, and `$this` in a trait doesn't resolve. Needs trait-composition
   modeling (which classes `use` which traits). Agent-verified as sound but *modest* impact.
3. **`self`/`static`/`parent` normalization** in `resolve()`/`typeName()` — currently passed through
   literally. Small, sound.
4. **Values into closures** — follow a value that enters a closure body (partially done via capture;
   verify the forward direction).
5. **Agent A's extra assume signals** — return-into-non-nullable-return-type, non-nullable-property
   -assign, spread `...$x`. Sound but ~0 impact today; the leverage is receiver resolution (#1), not
   more assume kinds. **Each of these ADDS assumes → each needs a fresh 0-FP calibration pass before
   shipping** (unlike the guard additions, which are FP-safe by construction).

## Deferred

- **Phase 5 — consolidation**: converge `DeNulledFinderDetector`, `FeatureEnvy`/`ChainResolver`, and the
  hand-rolled caller/receiver walkers onto the shared `TypeResolver` + `ValueFlow` substrate. Behaviour-
  preserving, guarded by each detector's existing fixture. See the plan in `phantom-nullable-design.md`.

## Standing constraints (don't forget)

Zero-FP is the ship gate. Calibrate from the consumer dir, not a whole-tree scan. Don't self-judge
code-commandments' own code (gate is phpunit). Everything from v4.108.1 onward is **local and unpushed**
— the user is holding pushes.
