# option-justified-transform-chain

**Verdict: `Option<CouponCode>` is the correct design here. The nullable `?CouponCode` is the wrong one.**

## Scenario

`CouponRepository::findActive()` does a lookup whose result is then **transformed
through several steps** — `parse` (numeric percent?) → `stillRedeemable`
(0 < percent ≤ 100?) → `normalise` (uppercase/trim) — and absence can occur at
*any* step. The result is then consumed by three distinct call sites.

## Why Option wins — driven by the call sites

The verdict is not about the producer's internal staircase (that's a symptom). It's
about how the value is **used**, and absence here is genuinely juggled by multiple
callers, each handling it differently — exactly the case where forcing explicit
handling prevents bugs:

1. **Producer (`CouponRepository::findActive`)** — the value is *threaded through a
   transform pipeline*. In `messy/` each of three steps needs its own
   `if ($x === null) return null;` re-check (a guard staircase). In `golden/` the
   pipeline is `Option::fromNullable(...)->flatMap(parse)->flatMap(stillRedeemable)->map(normalise)`
   — absence short-circuits automatically and no step can forget a guard.

2. **`CheckoutController::apply`** — *transforms the value further* (discount math,
   rounding) then unwraps to a default. Option chains
   `->map(discount)->map(round)->getOrElse($cart->total())`. The nullable version
   must wrap the same math in an `if/else`, and a later edit can drop the guard and
   divide on a null `percentOff`.

3. **`CouponPreviewService::preview`** — *re-coalesces the same value twice* (once
   for `valid`, once for `label`). With a nullable that's two independent
   `!== null` checks that can drift out of sync; with Option it's one value mapped
   two ways (`isSome()` + `map()->getOrNull()`).

4. **`RedeemCouponJob::handle`** — absence is a **domain event** (must throw).
   `->getOrThrow(fn () => new CouponNotRedeemableException(...))` makes the throw
   unskippable at the type level; the nullable version relies on the author
   remembering the guard, and a miss is a null-property fatal instead of the
   intended domain exception.

Three callers, three *different* dispositions of absence (default / preview-flag /
throw), plus a multi-step transform in the producer. That is precisely the profile
where `Option` earns its keep — not ceremony, but the thing that keeps every call
site honest. Had there been a single local `?? $default`, the nullable would have
been right; here there isn't.

## Files

- `golden/` — `Option<CouponCode>`: clean `flatMap`/`map` chains, absence enforced.
- `messy/` — `?CouponCode`: guard staircase in the producer, repeated/forgettable
  null checks across the call sites.
