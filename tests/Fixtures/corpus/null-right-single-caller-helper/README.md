# null-right-single-caller-helper

## Verdict: NULLABLE `?T` is correct. An `Option<Carbon>` here is over-engineering.

`CheckoutSummary::trialEndsOn()` is a thin, private helper returning the trial
end date or "absent". The whole question is decided by **how the single caller
consumes it** — and the call site does exactly one thing:

```php
$billingStartsOn = $this->trialEndsOn($plan) ?? $this->now;   // golden
```

### Why null wins here

- **Exactly one caller, one local coalesce.** There is a single consumer doing
  a single clean `?? $default`. Absence has one obvious meaning ("billing starts
  today") resolved on the spot — nothing propagates.
- **The value is never threaded.** No `map`/`flatMap`, no second transform, no
  re-coalescing downstream. Forcing explicit absence handling buys nothing
  because there is nothing to chain or to forget.
- **Absence is "not set", not a domain event.** A plan simply may have no trial.
  That is the textbook case for `?T`.
- **The helper is private and thin.** No public contract benefits from the
  "you cannot accidentally use a missing value" guarantee — the only caller is
  ten lines away.

### Why the messy version is wrong

The messy `trialEndsOn(): Option<Carbon>` builds `Option::some(...)` /
`Option::none()` and the **same single caller immediately `->getOrElse($this->now)`
unwraps it straight back to a `Carbon`** on the very next line. The Option is
constructed and destructed in two adjacent statements and does nothing the bare
`?? $this->now` didn't already do — it is pure ceremony.

### When the verdict would flip

If the trial date were threaded through several transforms, juggled by 3+
callers, or a nullable kept propagating and getting re-coalesced over and over,
`Option` would start earning its place by forcing explicit handling. None of
that is true here — so nullable is the honest, correct design.

## Files
- `golden/SubscriptionPlan.php`, `golden/CheckoutSummary.php` — nullable `?Carbon`, single clean `?? default`.
- `messy/SubscriptionPlan.php`, `messy/CheckoutSummary.php` — needless `Option<Carbon>` built then immediately `->getOrElse`-d.
