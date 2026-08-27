---
name: detector-fixtures
description: The self-checking Shop fixture that proves every detector — #[Sinful] markers ARE the test spec, SinfulMarkerVerifier checks missed/unexpected, and every detector must fire on ≥3 DIVERSE scenarios (different files, <60% class overlap, max-clique) plus have a righteous twin it must NOT flag. Read this when adding/adjusting a detector's fixtures or debugging a BackendFixtureTest failure.
---

# The self-checking fixture — markers ARE the spec

`tests/Fixtures/backend/app` is a coherent Laravel-style "Shop" app that is
**never run** — only parsed (its frontend twin is `tests/Fixtures/frontend`). A
`#[Sinful(Sin::class)]` attribute on a class or method declares "the detector for
this sin MUST flag here." Absence of a marker means the spot is righteous.
**Adding a test = adding an attribute.**

```php
#[Sinful(EnumValueMatch::class)]   // name the SIN class; method- or class-scope; repeatable
public function badge(Order $order): string
{
    return match ($order->status->value) { /* … */ };   // the sin
}
```

## What the harness enforces (`tests/Detectors/Backend/BackendFixtureTest`, via `FixtureTestCase`)

`SinfulMarkerVerifier` runs every detector in `Catalog::backend()` over the backend
fixture (and `Catalog::frontend()` over `tests/Fixtures/frontend`) and, per
detector, reports:
- **missed** — a `#[Sinful]` it did NOT flag (a hole), and
- **unexpected** — code it flagged that is NOT marked (a false positive, OR an
  unmarked sin you forgot to mark).

Both must be empty. So the whole *unmarked* fixture is the false-positive guard.

## The ≥3-diverse-scenarios floor

Every detector must fire on at least **three mutually-DIVERSE** findings:
different files **and** <60% class-source overlap (a **max-clique** over the
diversity graph — order-independent, so a look-alike "hub" finding can't mask a
real trio, and copy-paste/renames don't count). The 60% threshold is calibrated
from measured data (genuinely-different scenarios cluster 38–59%; renamed copies
66–100%).

**Making three the same sin look different:** vary the genuine *shape*, not the
names. Renaming `$prices`→`$rates` scores ~80%; a structurally different method
scores ~40%. Three forms of one sin: a string state-machine vs int counters vs a
keyed map; a match-return vs match-assign vs `switch`; an HTTP client vs a file
reader. For whole-class sins (DTOs), give each class genuinely different
behaviour methods so the shared shell is a small fraction.

## Righteous twins — non-negotiable

Every detector needs a deliberate **look-alike that it must NOT flag**, so the
"no unexpected" path is meaningfully exercised: e.g. `OrderData::from()` next to
`new OrderData()`, a `findOrFail()` next to a de-nulled `?T` finder, `::collect()`
next to `::from()`-in-a-loop. A file can be righteous for one detector and sinful
for another.

## `#[Fixed]` — the RESOLUTION, and not the same thing as righteous

`#[Righteous]` is a claim about the **detector**: a look-alike it must not flag,
which in practice is usually a documented *exemption* — code that legitimately
dodges the rule. `#[Fixed]` is a claim about the **fix**: the `#[Sinful]` code
repaired the way that sin's rule says to repair it.

```php
#[Sinful(NullableCallback::class)]
public function run(Closure $work, ?Closure $onRetry = null): mixed   // null-normalised in the body

#[Fixed(NullableCallback::class)]
public function runWith(Closure $work, Invokable $onRetry = new NoOp): mixed   // the rule's own fix
```

They coincide only by accident, and publishing the first as the second taught the
exemption instead of the fix — a whole audit's worth of skills said "make the
parameter required" where the rule said "default it to a Null Object". So the
generated `## Bad → good` prefers `#[Fixed]` and falls back to `#[Righteous]`
only where none exists; **the fallback is a stopgap, not a design**.

Frontend twin: `<!-- @fixed Name -->` above a template element, `// @fixed Name`
above a module declaration. Same claim, same preference over `@righteous`, same
coverage gate.

### A fix that MOVES behaviour spans declarations — mark BOTH ends

A repair is rarely one declaration. `$customer->suspend($reason)` is only the call
site that got thinner; the fix is `Customer::suspend()`. Mark **every** declaration
carrying the substance, and the generated good half publishes them together:

```php
#[Fixed(TypeSwitch::class)]                  // the caller that stopped asking what it IS
public function priceTold(PricedFreight $freight): int { … }

#[Fixed(TypeSwitch::class)]                  // the contract it now asks
interface PricedFreight { … }

#[Fixed(TypeSwitch::class)]                  // and each type that answers
public function priceCents(): int { … }
```

Which of them belong to ONE fix is decided by the file: the counterpart's own file,
plus any file holding no `#[Sinful]` of that sin (a shared collaborator — a model,
a Null Object, a generated type). That is what keeps a sin fixed three separate
times from publishing all three repairs as one.

Rules of thumb:

- Put the counterpart `#[Fixed]` in the **same class** as its `#[Sinful]` — the
  renderer pairs on the class, so the reader gets one coherent before/after — and
  the collaborators wherever they honestly live.
- **One scenario per sin per file.** Two classes in one file each holding a sin AND
  its own repair is undecidable, and `FixedIsTheResolutionTest` fails it.
- Repair *that* scenario. A fix for a different method teaches nothing.
- Show the construct the sin's own `rule`/`suggestion` names — if the rule says
  `#[WithCast]`, the fix must contain `#[WithCast]`.
- Never let a resolution name something the fixture does not declare. A published
  fix calling a method nothing defines teaches a repair a reader cannot follow.
- A resolution must also go unflagged, so `#[Fixed]` implies `#[Righteous]`, never
  the reverse. `FixedIsTheResolutionTest` fails if a detector flags its own fix.

## Diagnostics

A quick per-detector diversity/FP probe: scan the fixture (or workflows) with
`Catalog::all()`, print each finding's `location()` + `scope()` + the source line,
and compute the max-clique. (See the throwaway scripts under the scratchpad while
authoring.) `bin/commandments judge tests/Fixtures/backend --sin=X` also works.

## Related

- [[writing-detectors]] · [[detector-engine]]
