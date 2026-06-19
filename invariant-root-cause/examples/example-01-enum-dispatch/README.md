# Example 01 — closed-set enum `match` with `default => null`

**Domain:** order fulfilment SLA priorities.

## The smell (`initial/`)

`OrderStatus::slaPriority()` dispatches a closed-set enum with a `match`. Every *real* case returns an
`int` — the only way to get `null` is the `default =>` arm, which fires for a case nobody wired
(`Cancelled` was added to the enum and forgotten). So the `null` is **not** "this status has no
priority" — it is an **unhandled-case bug** disguised as absence. `SlaReporter` then papers over it with
`?? 0`, so the forgotten status silently counts as "never urgent."

## What must be flagged, and in what order

On `OrderStatus::slaPriority()` (the `?int` + `default => null`):

1. **ROOT CAUSE → `ThrowOnUnhandledCaseProphet`** (Correctness): a closed-set `match` whose `default`
   returns `null` while every real arm yields a value = an invariant violation modelled as absence.
2. **SYMPTOM → `PreferOptionOverNullProphet`** (Structural): "don't return `null` from a decision
   method." Because it is `Structural` (weight 0) it would otherwise be presented **first** — and an
   agent would wrap the result in `Option`, laundering the forgotten case into `Option::none()`.

**Required order:** the root cause leads. In a full `judge` run `ThrowOnUnhandledCase` supersedes
`PreferOptionOverNull` in-region (it is deferred). Under `--prophet=PreferOptionOverNullProphet` the
symptom is still emitted **with a root-cause hint** pointing at `ThrowOnUnhandledCaseProphet`.

Downstream, `SlaReporter::urgentCount()`'s `?? 0` is compensation for the bad contract; once the root
cause is fixed it becomes dead and is removed.

## The fix (`final/`)

**Remove the `default` arm** and handle `Cancelled` explicitly, returning a non-nullable `int`. Now the
`match` is total: adding a future enum case becomes a **compile-time `UnhandledMatchError`**, not a silent
`null`. The caller drops its `?? 0`.

**Class change:** none — the fix is *removing* the `default` arm (and the dead caller compensation).
