# Example 02 — registry that returns `?T` instead of throwing

**Domain:** payment gateways resolved by key.

## The smell (`initial/`)

`PaymentGatewayRegistry::find()` returns `?PaymentGateway` and resolves a miss with `?? null`. But a miss
means a gateway key was **never registered** — a wiring bug at boot — not a valid "no gateway". Every
caller proves it by immediately de-nulling (`?? throw …`). The nullable contract is unearned, restated at
every call site instead of enforced once.

## What must be flagged, and in what order

On `PaymentGatewayRegistry::find()`:

1. **ROOT CAUSE → `RegistryReturnContractProphet`** (Structural): a registry must return the item **or
   throw**, with a `has()` companion — not `Option`/`?T`.
2. **SYMPTOM → `NoNullCoalesceToNullProphet`** (auto-fixable / `SinRepenter`): the `?? null` is a no-op.
   ⚠️ This is the dangerous one — under `repent` it can be **auto-rewritten before any human sees the
   root cause**, so the auto-fix guard must skip it while the `RegistryReturnContract` finding is
   unresolved in-region.
3. **SYMPTOM → `PreferOptionOverNullProphet`** (Structural): "return an `Option`/Null Object instead of
   `null`" — would launder the wiring bug into an `Option::none()`.

`CheckoutService::charge()`'s `?? throw` is the evidence that every caller de-nulls.

**Required order:** `RegistryReturnContractProphet` leads; both symptoms are deferred (full run) or
annotated with a root-cause hint (filtered run); the auto-fixable `NoNullCoalesceToNull` is **excluded
from `repent`** until the root cause is resolved.

## The fix (`final/`)

Replace `find(): ?PaymentGateway` with `get(): PaymentGateway` that **throws a named exception**, and add
a `has(): bool` companion for the rare legitimate "is it registered?" check. The caller just calls
`get()`.

**Class change: ADD a class** — `UnknownPaymentGatewayException` (a named exception via a static factory).
The nullable contract is deleted; the invariant ("a charged key is a registered key") is enforced in one
place.

## Detector note — marked vs markerless (how this fires in the shipped tool)

`PaymentGatewayRegistry` carries no `Registry` marker, so `RegistryReturnContractProphet` fires via its
**markerless, shape-detected path** — and that path is an advisory **WARNING** (a heuristic must never
block a commit), raised on raw `?T` getters only. `find` is a finder name, so it fires only because the
cross-file census proves every caller de-nulls it (`CheckoutService` does `?? throw`). The two symptom
findings (`NoNullCoalesceToNull` on the `?? null`, `PreferOptionOverNull`) are deferred behind it in a
full run and hinted under `--prophet=` filtering; `repent` withholds the auto-fixable `?? null` strip
while the contract is unresolved.

> Marked-vs-markerless asymmetry: once a class IS marked (extends a `Registry` base / `#[Registry]` /
> a `Registry` interface), the contract is a **sin** and is enforced on *every* nullable/`Option`
> getter. Unmarked shape detection is the gentler nudge (warning, raw `?T` only) — mark it (or rename
> + extend the scaffolded base) to opt into full enforcement.
