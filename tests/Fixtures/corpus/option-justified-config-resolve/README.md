# option-justified-config-resolve

**Verdict: `Option<T>` is the better design here.** The call sites justify it.

## Scenario

A feature flag resolves through a fallback chain: per-user override → per-team
policy → system default. Each layer either has an opinion on the flag or
**defers** to the next one ("absent"). The producer composes these three
maybe-values; three different callers consume the result, each with its **own**
absence policy.

## Why Option wins — driven by the call sites

1. **The value is a `bool`, so `null` collides with a real value.** The messy
   `?? ` ladder treats a layer that explicitly returns `false` as if it were
   absent and falls through to the next layer — a genuine correctness bug.
   `Option` keeps "present-and-false" (`some(false)`) distinct from "absent"
   (`none`), so `orElse` only falls through on true absence. This is the
   load-bearing reason: nullable literally cannot encode this domain correctly.

2. **Absence is juggled by SEVERAL callers, each differently.**
   - `CheckoutController` treats absence as a domain error → `getOrThrow(...)`.
   - `BannerRenderer` transforms then defaults → `map(...)->getOrElse(...)`.
   - `ReportScheduler` wants a plain default → `getOrElse(false)`.
   With nullable, every caller re-invents the guard by hand (`if ($x === null)`,
   `?? false`, `($x ?? false)`), and each is one typo from a silent bug. Option
   **forces** each caller to state its policy and makes the unwrap explicit.

3. **The value is threaded through a transform.** `BannerRenderer` maps the flag
   to copy. Option's `map` runs only when present and keeps the chain flat;
   nullable forces an intermediate variable + re-coalesce (the producer already
   coalesced three layers, and the caller coalesces a fourth time).

4. **The resolve genuinely composes maybe-values.** `orElse` *is* the fallback
   chain expressed honestly — three sources, each `Option`, short-circuiting on
   the first present one — versus a `??` ladder that conflates "no opinion" with
   "false".

This is **not** the single-local-`?? default` case where Option would be empty
ceremony: there are three independent callers, a real transform, and a
value type (`bool`) that `null` cannot safely share space with.

## Files

- `golden/` — Option-based design (correct for this verdict).
  - `Option.php` — the `Option<T>` value type (stand-in for `JesseGall\PhpTypes\Option`).
  - `FeatureFlagResolver.php` — producer; composes layers with `->orElse(...)`.
  - `Stores.php` — the three source stores + the `UnknownFeatureFlag` error.
  - `CallSites.php` — the three consuming call sites (throw / map / default).
- `messy/` — nullable `?bool` design (wrong for this verdict).
  - `FeatureFlagResolver.php` — `?? ` ladder that buries `false` as "absent".
  - `Stores.php` — same sources.
  - `CallSites.php` — each caller re-invents absence handling, with the
    `?? false` bug where a missing flag and an off flag render identically.
