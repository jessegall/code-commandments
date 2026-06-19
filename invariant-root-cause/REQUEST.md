# A general **Root-Cause precedence** system for prophets

*(`supersedes` ⇄ `rootCauses` + an auto-fix guard, driven by one map — with the invariant/absence
family as its first consumer.)*

> Status: **DRAFT — iterating.** Not yet filed.
>
> **Implementer: do not start coding from this prose. Jump to [Implementation playbook](#implementation-playbook--start-here--do-not-skip)
> first — you MUST read every example before planning.**

## Goal

Make it impossible for an agent (or `repent`) to fix a **symptom** while its **root cause** sits
unresolved in the same place — regardless of run mode (`judge`, `--prophet=…` filtered, or `repent`).
The motivating case is invariant violations laundered into `Option`, but the mechanism should be
**general** to any symptom↔cause pair.

## TL;DR

1. The package already has half of this: **`supersedes()`** + region-scoped deferral in `FindingQueue`.
   It's generic, but only 3 unrelated prophets use it and it only works in a full default `judge` run.
2. Add the missing, **generic** pieces:
   - **`rootCauses()`** — the inverse, symptom-side declaration that *triggers* a root-cause check so the
     relationship survives `--prophet=` filtering and degrades to an annotation when deferral is too strong.
   - **An auto-fix guard** — `repent` must never auto-fix a finding that has an unresolved in-region root
     cause (three absence prophets are `SinRepenter`, so this laundering path is real today).
   - **One `RootCauseMap`** — a single source of truth; `supersedes`, `rootCauses`, and the auto-fix guard
     all derive from it, so the two directions can't drift.
3. Wire the **invariant/absence family** as the first consumer of the map, and add the one detector no
   existing prophet owns: **`NoSwallowedNotFoundProphet`**.

---

## Why (the motivating bug)

`JudgeCommand` presents **one finding at a time** (`FindingQueue::order($findings)` → `$ordered[0]`),
ordered by `Tier::weight()` (Structural = 0 first). Current tiers put the *symptom* above the *cause*:

| Prophet | Tier (weight) | Role |
|---|---|---|
| `PreferOptionOverNullProphet` | **Structural (0)** | model a *genuine* absence as `Option`/Null Object |
| `RegistryReturnContractProphet` | Structural (0) | registry returns item **or throws** (invariant) |
| `ThrowOnUnhandledCaseProphet` | Correctness (1) | unhandled closed-set case → **throw** (invariant) |
| `PreferTotalOverNullableProphet` | Convention (2) | every caller de-nulls → **totalise/throw** (invariant) |

Take a `null` that is actually an invariant violation:

```php
private function priorityFor(Status $s): ?int
{
    return match ($s) {
        Status::Open   => 3,
        Status::Urgent => 9,
        default        => null,   // invariant: every real status has a priority
    };
}
```

Both `ThrowOnUnhandledCase` (Correctness) and `PreferOptionOverNull` (Structural) fire. The Structural
symptom is presented first, the agent wraps it:

```php
private function priorityFor(Status $s): Option   // satisfied PreferOptionOverNull
{
    return match ($s) {
        Status::Open   => Option::some(3),
        Status::Urgent => Option::some(9),
        default        => Option::none(),   // invariant violation, now LAUNDERED
    };
}
```

The `null` is gone, the prophet is green, and the should-have-thrown bug is now permanent and harder to
spot. **No detector quality fixes this — the wrong fix simply leads.** Worse: `PreferNullObjectDefaults`,
`NoOptionToNull`, and `NoNullCoalesceToNull` are `SinRepenter` (auto-fixable), so `repent` can apply a
laundering fix with no human/agent in the loop at all.

---

## This must be GENERAL, not invariant-specific

Root cause → symptom is a relationship that recurs all over a linting catalog (a raw-array bag underneath
a naming nit; a god-object underneath a long-method finding; etc.). `supersedes` is *already* generic —
`PreferCoalesceFor`, `NoRawLiteral`, and `NoArrayBag` use it for unrelated reasons. So the new pieces
(`rootCauses`, the auto-fix guard, the map) should live in the **core** (`BaseCommandment` / `FindingQueue`
/ `RepentCommand` / a `RootCauseMap` registry), available to every prophet. The invariant/absence family is
just the first, highest-value consumer.

---

## What already exists

- **`Commandment::supersedes(): array`** (`BaseCommandment`, `Contracts\Commandment`, captured on `Finding`).
- **`FindingQueue`** — `isSuperseded()` defers a symptom when a superseding prophet's finding sits in the
  same file within `DEFER_WINDOW = 60` lines; then orders the rest by tier → file → line → prophet.
- **`JudgeCommand`** — walks `FindingQueue::order(...)` one finding at a time.
- **`RepentCommand`** — auto-fixes "sins and [AUTO-FIXABLE] warnings."
- Only 3 prophets declare `supersedes`; none of the invariant-enforcers do.

**Gaps:** (a) `supersedes` needs the cause prophet to be *active and firing* — useless under `--prophet=`
filtering; (b) it only *hides*, with no "still report but annotate" middle ground; (c) `repent` doesn't
consult it at all.

---

## The mechanism (3 parts + 1 map)

### 1. `supersedes()` — cause defers symptom in-region *(exists; just wire it)*
Default `judge` runs. No code change to the mechanism, only `rootCauses`/map wiring (below).

### 2. `rootCauses()` — symptom declares + triggers its cause *(new, generic, inverse)*
```php
// on a symptom prophet
public function rootCauses(): array { return [/* cause prophet classes */]; }
```
When a symptom finding is produced, the engine:
1. **Triggers the declared cause prophets on the same node/region — even if filtered out** (`--prophet=
   PreferOptionOverNull` still runs a `ThrowOnUnhandledCase` check on that site). Bounded — see *Guards*.
2. If a cause matches there:
   - cause is in the active set → hand to `supersedes` (defer as today); else
   - cause was filtered out → **still emit the symptom, annotated with a root-cause hint:**
     ```
     PreferOptionOverNull — do not return null from a decision method (Foo.php:42)
       ↳ Root cause: this null looks like an invariant violation, not a genuine absence.
         Fix via ThrowOnUnhandledCaseProphet (throw) — wrapping it in Option only hides it.
     ```
3. If no cause matches → genuine absence → emit the symptom clean. (`PreferOptionOverNull` is exactly
   right here; nothing changes.)

### 3. Auto-fix guard — `repent` must not launder *(new, generic)*
`RepentCommand` must skip any finding that currently has an **unresolved in-region root cause** (reuse the
same deferral + the `rootCauses` trigger, since a filtered `repent --prophet=NoOptionToNull` won't have the
cause prophet active). Instead of auto-fixing, surface the root-cause hint. Without this, the three
`SinRepenter` absence prophets auto-launder silently.

### 4. One `RootCauseMap` — single source of truth
`supersedes` (cause→symptoms), `rootCauses` (symptom→causes), and the auto-fix guard all derive from **one**
declared relation, so the directions can't fall out of sync:
```php
RootCauseMap::edges() === [
    ThrowOnUnhandledCaseProphet::class    => [PreferOptionOverNullProphet::class, PreferEmptyOverNullProphet::class, PreferNullObjectDefaultsProphet::class, NoOptionToNullProphet::class, NoNullCoalesceToNullProphet::class],
    PreferTotalOverNullableProphet::class => [/* same symptom set */],
    RegistryReturnContractProphet::class  => [/* same symptom set */],
    NoSwallowedNotFoundProphet::class     => [/* same symptom set */],
];
// supersedes() reads it directly; rootCauses() reads the flipped index; repent reads it for the guard.
```

---

## First consumer: the invariant / absence family

**Causes (invariant must hold → throw / totalise):**
`ThrowOnUnhandledCaseProphet`, `PreferTotalOverNullableProphet`, `RegistryReturnContractProphet`, and the
new `NoSwallowedNotFoundProphet`.

**Symptoms (model a genuine absence — laundering risk when the absence is actually a bug):**
- core: `PreferOptionOverNullProphet`, `PreferEmptyOverNullProphet`, `PreferNullObjectDefaultsProphet`
- secondary (operate on Option/`??` that *produced* the null): `NoOptionToNullProphet`,
  `NoNullCoalesceToNullProphet` — include, but see *Open questions* on membership.

### New detector — `NoSwallowedNotFoundProphet`
The one shape no existing prophet owns: a not-found exception caught and replaced with a sentinel.
```php
// BAD — a declared/contracted value swallowed into null
try { $v = $ctx->get($id, $name); } catch (\OutOfBoundsException) { $v = null; }
// GOOD — let it throw (the contract says it exists)
$v = $ctx->get($id, $name);
```
AST (real `nikic/php-parser`, no regex): a `Stmt\TryCatch` whose catch type ∈ a configurable not-found set
(`OutOfBoundsException`, `OutOfRangeException`, `RuntimeException`, `*NotFound*`) and whose catch body only
assigns/returns a `null`/`false`/`[]` sentinel. Tier: `Correctness`.

---

## Behavior matrix (for a `null`-that-should-throw)

| Run | Today | With this system |
|---|---|---|
| `judge` (full) | symptom (Structural) shown first → laundered | cause leads via `supersedes`; symptom deferred |
| `judge --prophet=PreferOptionOverNull` | symptom only, no hint → laundered | symptom emitted **+ root-cause hint** (via `rootCauses` trigger) |
| `repent` (auto-fix) | `SinRepenter` symptom auto-applied → laundered silently | auto-fix **skipped**; root-cause hint surfaced |
| genuine absence (no cause matches) | symptom shown | symptom shown (unchanged — correct) |

---

## Guards & constraints

- **Depth 1** — a triggered cause prophet does not trigger *its* causes.
- **Region-scoped** — triggered evaluation runs on the symptom's node/region (reuse `DEFER_WINDOW = 60`),
  not the whole file.
- **No double-report** — if the cause is already active, defer via `supersedes` and skip the hint.
- **Codebase-index dependency** — `PreferTotalOverNullable` and `RegistryReturnContract` (and the symptom
  `PreferOptionOverNull`/`PreferEmptyOverNull`) implement `NeedsCodebaseIndex`. Triggering an index-needing
  cause under a filtered/`repent` run requires the index to be available. Decide: build the index on demand
  when a triggered cause needs it, or degrade to a static "a root cause may apply here — run a full
  `judge`" hint without executing the index-heavy prophet. *(see Open questions.)*

---

## Regression self-test
A self-test (the package already dogfoods via `commandments.self.php`) asserting the `RootCauseMap` is
symmetric — every symptom in a cause's list resolves back to that cause via the flipped index — so the
family stays consistent as prophets are added.

---

## Naming (proposed)

- **New detector:** `NoSwallowedNotFoundProphet` *(recommended)* — fits the `No…Prophet` convention.
  Alts: `RethrowNotFoundProphet`, `NoCatchToNullProphet`, `ThrowOnSwallowedLookupProphet`.
- **Mechanism API:** `rootCauses(): array` (inverse of `supersedes(): array`); registry `RootCauseMap`;
  finding annotation = a "root-cause hint."

---

## Open questions

1. **Secondary symptom membership** — do `NoOptionToNull` / `NoNullCoalesceToNull` belong in the map, or
   are they independent enough to leave out? (They act on Option/`??` code, not a raw null decision return.)
2. **Index under triggered/filtered runs** — build on demand vs. degrade to a static hint (see Constraints).
3. **`NoSwallowedNotFoundProphet` scope** — ship in this issue, or split into its own once the mechanism lands?
4. **Annotation strength** — always emit symptom + hint, or make "defer vs annotate" configurable per edge?
5. **API name** — `rootCauses()` vs `causedBy()` vs `deferTo()`.

---

## Implementation sketch (one PR)
`RootCauseMap` registry · `rootCauses()` on `BaseCommandment`/`Contracts` (default `[]`, derived from the
map) · `FindingQueue` trigger + annotation · `RepentCommand` guard · wire the absence family into the map ·
add `NoSwallowedNotFoundProphet` · symmetry self-test.

---

## Worked examples (this pack)

`examples/` holds 5 self-contained, framework-agnostic cases. Each has an `initial/` (the smell) and a
`final/` (cleaned up), plus a per-example `README.md` that states **exactly what should be flagged, by
which prophet, and in what order** (root cause leading the symptom), and whether the fix **adds or removes
a class**.

| # | Folder | Scenario | Root cause prophet (leads) | Symptom prophet(s) (deferred / annotated) | Fix shape |
|---|---|---|---|---|---|
| 01 | `example-01-enum-dispatch` | closed-set enum `match` with `default => null` | `ThrowOnUnhandledCaseProphet` | `PreferOptionOverNullProphet` (+ caller `?? 0`) | **remove** the `default` arm → total `match` |
| 02 | `example-02-registry-contract` | registry `find(): ?T` with `?? null`; every caller de-nulls | `RegistryReturnContractProphet` | `PreferOptionOverNullProphet`, `NoNullCoalesceToNullProphet` *(auto-fixable!)* | **add** a named exception + `has()` companion |
| 03 | `example-03-null-object` | private `?Discount` helper every caller de-nulls | `PreferTotalOverNullableProphet` | `PreferNullObjectDefaultsProphet`, `PreferOptionOverNullProphet` | **add** a Null Object class |
| 04 | `example-04-swallowed-notfound` | not-found exception caught → `null` | `NoSwallowedNotFoundProphet` *(new)* | `PreferOptionOverNullProphet` (+ caller `?? 'Guest'`) | **remove** the `try/catch` (let it throw) |
| 05 | `example-05-genuine-vs-invariant` | one invariant lookup + one genuine-absence lookup, side by side | `RegistryReturnContractProphet` (on the invariant one only) | `PreferOptionOverNullProphet` — **correct** on the genuine one, left alone | invariant → throw; genuine → `Option`; **remove** a redundant `MaybeUser` wrapper |
| 06 | `example-06-notifications-subsystem` | a **multi-file** subsystem combining all four smells across files + correct vs. smelly `Option<T>` | `ThrowOnUnhandledCase`, `RegistryReturnContract`, `PreferTotalOverNullable`, `NoOptionToNull` | the matching symptom prophets per file | **add** two named exceptions; keep the genuine `Option`, add a throwing `require()` |

Example 05 is the important negative test: it shows the root-cause trigger firing, finding **no** invariant
cause on the genuine-absence method, and therefore leaving `PreferOptionOverNull` to do its (correct) job —
the system must not over-correct a real optional into a throw. Example 06 is the "see it in a real
codebase" case, and shows **`Option<T>` on both sides of the line**: a genuine `lookup(): Option<Template>`
that is correct and left untouched, vs. a `lookup(...)->getOr(null)` on a *required* template that is the
smell (the fix adds a throwing `require()` and leaves the real `Option` alone).

> `Option` in the samples = the project's Option monad (the commandments docs use `getOr` / `getOrThrow` /
> `some` / `none` / `map`); shown illustratively, not meant to compile against a specific package.

### Using the examples as test fixtures (suggested)

The `initial/` ⇄ `final/` pairs are designed to double as a **before/after regression suite** for the
prophets — please wire them into PHPUnit where practical (a suggestion, not a hard requirement):

- **`initial/` = expected findings.** Run the relevant prophet(s) over each `initial/` folder and assert
  the finding set: the right prophet on the right file/line, the right `Tier`, and — once the Root-Cause
  system lands — the **ordering** (root cause leads; symptom deferred in a full run, or emitted **with a
  root-cause hint** under `--prophet=` filtering). The per-example `README.md` is the human-readable spec
  for these assertions.
- **`final/` = clean.** Run the same prophet(s) over each `final/` folder and assert **zero findings** —
  the cleaned-up code must be righteous. This catches false positives and "the fix re-trips another rule".
- **Auto-fix guard regression.** For example 02 and 06 (which contain `SinRepenter` symptoms like
  `NoNullCoalesceToNull`), assert that `repent` over `initial/` does **not** auto-apply the symptom fix
  while the root cause is unresolved — i.e. the laundering path stays closed.
- **Negative test.** Example 05's genuine-absence method must produce its symptom finding **with no
  root-cause hint** (no invariant cause matched) — the assertion that proves the system doesn't
  over-correct.

A data-provider over `examples/*/` (glob the folders, map each to its expected-findings fixture) keeps
this cheap to extend: dropping in `example-07/initial|final` plus a small expectation file adds a case
with no new test code. This also dogfoods the package the way `commandments.self.php` already does.

---

## Implementation playbook — START HERE — DO NOT SKIP

This is mandatory and ordered. Treat the examples as the executable specification, not decoration.

### Step 0 — Read everything. ALL of it. Especially the examples.
You **MUST and SHOULD read every example before writing a single line of code or plan.** That means:
- this `REQUEST.md` in full, **and**
- **all six** `examples/*/README.md`, **and**
- **every** `initial/` and `final/` PHP file in **all six** examples.

The examples pin down what this prose can only gesture at: which prophet is the *root cause* vs the
*symptom*, the required **ordering**, the **annotation** behaviour under `--prophet=` filtering, the
**auto-fix guard**, and — critically — the **genuine-absence vs invariant** distinction (example 05) and
the correct-vs-smelly `Option<T>` use (example 06). If your plan contradicts any example, the plan is
wrong, not the example. Do not proceed to Step 1 until you have read them all.

### Step 1 — Enter plan mode. Do not edit code yet.
Produce a **comprehensive written plan**, covering at least:
- the `RootCauseMap` registry (shape, where it lives, how `supersedes()` / `rootCauses()` derive from it);
- the `rootCauses()` contract addition (`BaseCommandment` / `Contracts`, default `[]`);
- the `FindingQueue` changes: symptom-side **triggering** of declared causes (incl. under `--prophet=`
  filtering) and the **root-cause hint** annotation;
- the `RepentCommand` **auto-fix guard** (never auto-fix a finding with an unresolved in-region cause);
- the **codebase-index** handling for triggered `NeedsCodebaseIndex` causes (build-on-demand vs degrade);
- the concrete map entries for the invariant/absence family + the new `NoSwallowedNotFoundProphet`;
- a resolution for **every** item in [Open questions](#open-questions);
- the test plan that asserts each `examples/*/` `initial/` (findings + order) and `final/` (clean).

### Step 2 — Have the plan critiqued by experts.
Before implementing, submit the plan for **independent expert critique** (e.g. spawn separate reviewer
agents, or ask senior reviewers) from *at least* these distinct angles, each as its own pass:
- **API / naming** design (`rootCauses` vs alternatives; the map shape);
- **Precision / false positives** (does triggering over-fire? does the guard hide real symptoms?);
- **Performance** (the trigger + on-demand index build; the `DEFER_WINDOW` region scan);
- **Backward compatibility** (existing `supersedes` users; existing prophet tiers/behaviour);
- **DX / teaching** (is the hint actionable? does scripture teach the relationship up front?).

### Step 3 — Refine the plan with the critique.
Fold in **every** point raised. Resolve disagreements explicitly. The refined plan must leave **nothing
ambiguous** — no "TBD", no open question, no behaviour that isn't pinned by an example or a stated
decision. Re-circulate if the critique forced material changes.

### Step 4 — Implement (one PR), to the refined plan.
`RootCauseMap` · `rootCauses()` on the base/contract · `FindingQueue` trigger + annotation ·
`RepentCommand` guard · wire the absence family into the map · add `NoSwallowedNotFoundProphet` ·
symmetry self-test.

### Step 5 — Validate against the fixtures (definition of done).
Green means: every `examples/*/initial/` yields the expected findings **in the expected order** (cause
leads; symptom deferred or hinted), every `examples/*/final/` yields **zero** findings, the auto-fix guard
keeps the laundering path closed (examples 02 & 06), the example-05 negative test produces a symptom with
**no** hint, and the `RootCauseMap` symmetry self-test passes.
