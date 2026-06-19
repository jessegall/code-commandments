# Example 03 — private nullable helper every caller de-nulls → Null Object

**Domain:** a shopping cart with an optional discount.

## The smell (`initial/`)

`Cart::activeDiscount(): ?Discount` is **private**, and every caller in the class immediately de-nulls it
(`?->applyTo(...) ?? $subtotal`, `?->label() ?? 'No discount'`). The nullability is ceremony repeated at
each use site, and the "no discount" behaviour (identity on the amount, a fixed label) is re-implemented
inline every time.

## What must be flagged, and in what order

On `Cart::activeDiscount()` (private `?Discount`, every caller de-nulls):

1. **ROOT CAUSE → `PreferTotalOverNullableProphet`** (Convention; private-method-scoped, so "every
   caller" is provable from the class alone): make the method **total** — always return a value — or
   throw.
2. **SYMPTOM → `PreferNullObjectDefaultsProphet`** (auto-fixable / `SinRepenter`) and
   **`PreferOptionOverNullProphet`**: model the absence as an `Option`/Null Object. Applied first in
   isolation, an agent might wrap the helper in `Option` and leave the two `?->… ?? …` sites — missing
   that the real cleanup is a **total** method returning a Null Object.

**Required order:** `PreferTotalOverNullable` leads. The symptom prophets are deferred/annotated so the
fix is "make it total via a Null Object," after which both call sites collapse to plain calls.

## The fix (`final/`)

Make `activeDiscount(): Discount` total by returning a **Null Object** (`NoDiscount`) when there is none.
The two callers become `->applyTo(...)` and `->label()` with no `?->` and no `??`. The "no discount"
behaviour now lives in exactly one place (the `NoDiscount` class) instead of being re-inlined per caller.

**Class change: ADD a class** — `NoDiscount implements Discount` (a Null Object). The constructor can
still accept `?Discount` from the outside world; it is normalised once, at the boundary.

## Detector note (how this fires in the shipped tool)

The prophet that actually fires here is **`PreferNullObjectDefaultsProphet`** (its class-scope
pattern-B: a *private nullable accessor* — `activeDiscount(): ?Discount` — whose result is de-nulled
via `?->… ?? <default>` at ≥2 sites across the class). That is the precise, correct signal, and its
guidance *is* "return a Null Object." `PreferTotalOverNullableProphet` deliberately treats a `?->`
caller as "absence handled, leave it" (that is its documented LEAVE-WHEN), so it is **not** the
firing rule despite the prose above framing the root cause as totalisation — the Null Object fix is
the same either way.
